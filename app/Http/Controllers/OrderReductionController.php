<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\Order;
use App\Models\OrderReduction;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderReductionController extends Controller
{
    public function create(Order $order)
    {
        $order->load(['items.design', 'customer', 'reductions.items']);
        return view('orders.reduce', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        $request->validate([
            'adjustment_type'        => 'required|in:damage,short_supply,price_correction,other',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.design_id'      => 'required|exists:designs,id',
            'items.*.size'           => 'required|in:xs,s,m,l,xl',
            'items.*.qty'            => 'required|integer|min:1',
            'surplus_action'   => 'required|in:none,credit_to_advance,refund',
            'refund_method'    => 'required_if:surplus_action,refund|nullable|in:cash,bank_transfer',
            'refund_reference' => 'nullable|string|max:255',
            'refund_document'  => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
        ]);

        DB::transaction(function () use ($request, $order) {
            $order->loadMissing(['items.design', 'customer']);

            // Calculate reduction amount from order item unit prices
            $orderItemsByDesign = $order->items->keyBy('design_id');
            $totalReduced       = 0;
            $itemData           = [];
            $itemLines          = [];

            foreach ($request->items as $item) {
                $orderItem = $orderItemsByDesign->get($item['design_id']);
                $unitPrice = $orderItem ? (float) $orderItem->unit_price : 0;
                $amount    = $unitPrice * $item['qty'];
                $totalReduced += $amount;

                $designName = $orderItem?->design?->name ?? "Design #{$item['design_id']}";
                $itemLines[] = "{$designName} " . strtoupper($item['size']) . "×{$item['qty']}";

                $itemData[] = [
                    'design_id'      => $item['design_id'],
                    'size'           => $item['size'],
                    'qty_reduced'    => $item['qty'],
                    'unit_price'     => $unitPrice,
                    'amount_reduced' => $amount,
                ];
            }

            $originalTotal  = (float) $order->total_amount;
            $newTotal       = max(0, $originalTotal - $totalReduced);
            $totalPaid      = (float) $order->total_paid;
            $customer       = $order->customer;
            $adjustmentLabel = ucwords(str_replace('_', ' ', $request->adjustment_type));
            $byName          = Auth::user()->name;
            $dateLabel       = today()->format('d M Y');

            // Rich description for order_reduced ledger entry
            $reductionDesc  = "Order #{$order->order_number} · {$adjustmentLabel} · {$dateLabel} · By: {$byName}";
            $reductionDesc .= "\nItems: " . implode(', ', $itemLines);
            if ($request->notes) $reductionDesc .= "\nNote: {$request->notes}";

            // Determine surplus_action — only meaningful if customer has overpaid
            $surplus       = max(0, $totalPaid - $newTotal);
            $surplusAction = $surplus > 0 ? $request->surplus_action : 'none';

            $reduction = OrderReduction::create([
                'order_id'          => $order->id,
                'reduced_by'        => Auth::id(),
                'reduction_date'    => today(),
                'adjustment_type'   => $request->adjustment_type,
                'surplus_action'    => $surplusAction,
                'original_total'    => $originalTotal,
                'new_total'         => $newTotal,
                'adjustment_amount' => $totalReduced,
                'notes'             => $request->notes,
            ]);

            foreach ($itemData as $item) {
                $reduction->items()->create(['order_reduction_id' => $reduction->id] + $item);
            }

            // --- Three-case logic ---

            if ($surplus <= 0) {
                // Case 1 (no payment) or Case 2 (partial payment): just reduce totals
                $order->update([
                    'total_amount'        => $newTotal,
                    'outstanding_balance' => max(0, $newTotal - $totalPaid),
                ]);

                $this->ledgerEntry($order, 'order_reduced', -$totalReduced, $reduction, $customer, $reductionDesc);

            } else {
                // Case 3: customer has overpaid — surplus exists
                $order->update([
                    'total_amount'        => $newTotal,
                    'outstanding_balance' => 0,
                ]);

                $this->ledgerEntry($order, 'order_reduced', -$totalReduced, $reduction, $customer, $reductionDesc);

                if ($surplusAction === 'credit_to_advance') {
                    $customer->increment('advance_credit_balance', $surplus);
                    $customer->refresh();
                    // No separate ledger entry — order_reduced already captures the full balance impact.
                    // The advance credit is visible via order_reductions.surplus_action and the notes above.

                } elseif ($surplusAction === 'refund') {
                    $refundMethodLabel = $request->refund_method === 'bank_transfer' ? 'Bank Transfer' : 'Cash';

                    $documentPath = null;
                    if ($request->hasFile('refund_document')) {
                        $documentPath = $request->file('refund_document')->store('refund-documents', 's3');
                    }

                    $refund = Refund::create([
                        'order_id'           => $order->id,
                        'order_reduction_id' => $reduction->id,
                        'customer_id'        => $customer->id,
                        'amount'             => $surplus,
                        'refund_method'      => $request->refund_method,
                        'refund_reference'   => $request->refund_method === 'bank_transfer' ? $request->refund_reference : null,
                        'refund_document'    => $documentPath,
                        'refund_date'        => today(),
                        'notes'              => $request->notes,
                        'refunded_by'        => Auth::id(),
                    ]);

                    $this->ledgerEntry($order, 'refund_issued', $surplus, $refund, $customer,
                        "Order #{$order->order_number} · Refund PKR " . number_format($surplus, 0) . " via {$refundMethodLabel} · {$dateLabel} · By: {$byName}");
                }
            }

            // Auto-cancel when new total reaches zero
            if ($newTotal == 0 && $order->status !== 'dispatched') {
                $order->update(['status' => 'cancelled']);
            }
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order reduction logged successfully.');
    }

    public function show(Order $order, OrderReduction $reduction)
    {
        abort_if($reduction->order_id !== $order->id, 404);
        $reduction->load(['order.catalogue', 'items.design', 'reducedBy', 'refund.refundedBy']);
        return view('orders.reduction-show', compact('order', 'reduction'));
    }

    private function ledgerEntry(Order $order, string $type, float $amount, $reference, $customer, ?string $notes): void
    {
        CustomerLedger::create([
            'customer_id'             => $order->customer_id,
            'transaction_type'        => $type,
            'amount'                  => $amount,
            'running_advance_balance' => (float) $customer->advance_credit_balance,
            'reference_type'          => get_class($reference),
            'reference_id'            => $reference->id,
            'notes'                   => $notes,
            'created_by'              => Auth::id(),
        ]);
    }
}
