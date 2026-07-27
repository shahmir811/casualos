<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderPriceRecalculationController extends Controller
{
    /**
     * Re-prices every item on the order against the catalogue's CURRENT
     * quantity_benchmark and each design's current selling/discount price,
     * then reconciles total_amount, outstanding_balance, the customer's
     * advance_credit_balance, and the order's original order_charged ledger
     * line — all in place, on the same rows. No ledger line is ever added
     * or removed; the original entry is adjusted by the delta so it always
     * stays in sync with order.total_amount, even if a second order_charged
     * row exists for this order (e.g. from a Piece Reassignment addition).
     */
    public function store(Order $order)
    {
        abort_if($order->status === 'cancelled', 403, 'Cannot recalculate a cancelled order.');

        $result = DB::transaction(function () use ($order) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            $lockedOrder->load(['items.design', 'catalogue']);
            $customer = $lockedOrder->customer()->lockForUpdate()->first();

            $oldTotal  = (float) $lockedOrder->total_amount;
            $totalPaid = (float) $lockedOrder->total_paid;
            $benchmark = $lockedOrder->catalogue->quantity_benchmark;

            $newTotal = 0;

            foreach ($lockedOrder->items as $item) {
                $design      = $item->design;
                $useDiscount = $benchmark !== null && $item->total_qty > $benchmark;

                $correctPrice = ($useDiscount && $design !== null && $design->discount_price !== null)
                    ? (int) round((float) $design->discount_price)
                    : (int) round((float) ($design?->selling_price ?? $item->unit_price));

                $item->unit_price = $correctPrice;
                $item->save(); // booted() recomputes total_amount from unchanged qty

                $newTotal += (float) $item->total_amount;
            }

            $delta = $newTotal - $oldTotal;

            if (abs($delta) < 0.01) {
                return ['changed' => false];
            }

            // Payment-status check: only the portion of the delta that pushes the
            // order across the "fully paid" line becomes a credit-balance change.
            // The rest just moves the outstanding balance up or down.
            $oldSurplus  = max(0, $totalPaid - $oldTotal);
            $newSurplus  = max(0, $totalPaid - $newTotal);
            $creditDelta = $newSurplus - $oldSurplus;

            $lockedOrder->update([
                'total_amount'        => $newTotal,
                'outstanding_balance' => max(0, $newTotal - $totalPaid),
            ]);

            // Adjust the order's original order_charged ledger line by the delta —
            // never set it to $newTotal outright, since a second order_charged row
            // can exist for this order (Piece Reassignment). Adjusting by delta keeps
            // SUM(order_charged rows) == order.total_amount regardless of how many
            // rows exist. Bypasses CustomerLedger's boot-level immutability guard,
            // same as OrderAdjustController already does for this exact table.
            $originalEntry = DB::table('customer_ledger')
                ->where('reference_type', 'App\Models\Order')
                ->where('reference_id', $lockedOrder->id)
                ->where('transaction_type', 'order_charged')
                ->orderBy('id', 'asc')
                ->first();

            if ($originalEntry) {
                DB::table('customer_ledger')
                    ->where('id', $originalEntry->id)
                    ->update(['amount' => (float) $originalEntry->amount + $delta]);
            }

            $shortfall = 0;
            if ($creditDelta > 0) {
                $customer->increment('advance_credit_balance', $creditDelta);
            } elseif ($creditDelta < 0) {
                $available = (float) $customer->advance_credit_balance;
                $toReclaim = min(abs($creditDelta), $available);
                $shortfall = abs($creditDelta) - $toReclaim;

                if ($toReclaim > 0) {
                    $customer->decrement('advance_credit_balance', $toReclaim);
                }
            }

            $customer->refresh();

            $props = [
                'order_number'      => $lockedOrder->order_number,
                'customer'          => $customer->name ?? $lockedOrder->submitted_name,
                'benchmark_used'    => $benchmark,
                'old_total_amount'  => 'PKR ' . number_format($oldTotal, 0),
                'new_total_amount'  => 'PKR ' . number_format($newTotal, 0),
                'recalculated_by'   => Auth::user()->name,
            ];
            if ($creditDelta > 0) {
                $props['advance_credit_change'] = '+PKR ' . number_format($creditDelta, 0);
            } elseif ($creditDelta < 0) {
                $props['advance_credit_change'] = '-PKR ' . number_format(abs($creditDelta) - $shortfall, 0);
            }
            if ($shortfall > 0) {
                $props['credit_shortfall'] = 'PKR ' . number_format($shortfall, 0) . ' could not be reclaimed (already spent elsewhere)';
            }

            activity()
                ->performedOn($lockedOrder)
                ->causedBy(Auth::user())
                ->event('detail')
                ->withProperties($props)
                ->log("Order #{$lockedOrder->order_number} pricing recalculated by " . Auth::user()->name
                    . ' (PKR ' . number_format($oldTotal, 0) . ' → PKR ' . number_format($newTotal, 0) . ')');

            return [
                'changed'   => true,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'shortfall' => $shortfall,
            ];
        });

        if (!$result['changed']) {
            return back()->with('success', 'Order pricing is already correct — no changes needed.');
        }

        $message = 'Order total recalculated: PKR ' . number_format($result['old_total'])
            . ' → PKR ' . number_format($result['new_total']) . '.';

        if ($result['shortfall'] > 0) {
            return back()->with('warning', $message . ' PKR ' . number_format($result['shortfall'])
                . ' of advance credit could not be reclaimed — the customer had already spent it elsewhere.');
        }

        return back()->with('success', $message);
    }
}
