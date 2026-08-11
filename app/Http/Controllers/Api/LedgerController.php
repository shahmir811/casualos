<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\LedgerEntryResource;
use App\Models\CustomerLedger;
use App\Models\Order;
use App\Models\OrderReduction;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    /**
     * Mirrors \App\Http\Controllers\LedgerController::show()'s reference-resolution
     * pattern (batch-load Order/Payment/OrderReduction/Refund by reference_type,
     * build an id => order_number map) — trimmed to just order_number/payment_id,
     * since that's all the mobile ledger view needs. The query is scoped to
     * $request->user() at the source, so there is no per-row ownership check needed.
     */
    public function index(Request $request)
    {
        $customer = $request->user();

        $entries = CustomerLedger::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $orderNumberMap = [];
        $paymentIdMap   = [];

        $reductionIds = [];
        $paymentIds   = [];
        $refundIds    = [];
        $directIds    = [];

        foreach ($entries as $entry) {
            if (! $entry->reference_id) {
                continue;
            }

            match ($entry->reference_type) {
                OrderReduction::class => $reductionIds[] = $entry->reference_id,
                Payment::class        => $paymentIds[]   = $entry->reference_id,
                Refund::class         => $refundIds[]    = $entry->reference_id,
                Order::class          => $directIds[]    = $entry->reference_id,
                default               => null,
            };
        }

        if ($reductionIds) {
            OrderReduction::whereIn('id', $reductionIds)
                ->with('order')
                ->get()
                ->each(function (OrderReduction $r) use (&$orderNumberMap) {
                    $orderNumberMap[OrderReduction::class . ':' . $r->id] = $r->order?->order_number;
                });
        }

        if ($paymentIds) {
            Payment::whereIn('id', $paymentIds)
                ->with('order')
                ->get()
                ->each(function (Payment $p) use (&$orderNumberMap, &$paymentIdMap) {
                    $orderNumberMap[Payment::class . ':' . $p->id] = $p->order?->order_number;
                    if ($p->order) {
                        $paymentIdMap[Payment::class . ':' . $p->id] = "{$p->order->order_number}p{$p->sequence_number}";
                    }
                });
        }

        if ($refundIds) {
            // Refunds snapshot order_number onto their own row at creation time
            // (rule 5.29) specifically so it survives the parent order being
            // hard-deleted — no need to resolve the order relation here.
            Refund::whereIn('id', $refundIds)
                ->get(['id', 'order_number'])
                ->each(function (Refund $r) use (&$orderNumberMap) {
                    $orderNumberMap[Refund::class . ':' . $r->id] = $r->order_number;
                });
        }

        if ($directIds) {
            Order::whereIn('id', $directIds)
                ->get(['id', 'order_number'])
                ->each(function (Order $o) use (&$orderNumberMap) {
                    $orderNumberMap[Order::class . ':' . $o->id] = $o->order_number;
                });
        }

        $rows = $entries->map(function (CustomerLedger $entry) use ($orderNumberMap, $paymentIdMap) {
            $key = $entry->reference_type . ':' . $entry->reference_id;

            return [
                'id'                      => $entry->id,
                'transaction_type'        => $entry->transaction_type,
                'amount'                  => $entry->amount,
                'running_advance_balance' => $entry->running_advance_balance,
                'order_number'            => $orderNumberMap[$key] ?? null,
                'payment_id'              => $paymentIdMap[$key] ?? null,
                'created_at'              => $entry->created_at,
            ];
        });

        return response()->json([
            'advance_credit_balance' => $customer->advance_credit_balance,
            'ledger'                 => LedgerEntryResource::collection($rows),
        ]);
    }
}
