<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\FreePiece;
use App\Models\Order;
use App\Models\OrderReduction;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\ProductionAssignmentAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderDeleteController extends Controller
{
    private function guard(Order $order): void
    {
        if (in_array($order->status, ['dispatched', 'partially_dispatched'], true) || $order->dispatchBatches()->exists()) {
            abort(403, 'Orders that are dispatched or partially dispatched cannot be deleted.');
        }
    }

    private function refundableAmount(Order $order): float
    {
        return (float) DB::table('customer_ledger')
            ->where('reference_type', Payment::class)
            ->whereIn('reference_id', $order->payments->pluck('id'))
            ->where('transaction_type', 'payment_received')
            ->sum(DB::raw('ABS(amount)'));
    }

    public function create(Order $order)
    {
        $this->guard($order);

        $order->load(['items.design', 'customer', 'catalogue', 'payments', 'reductions', 'refunds']);

        $refundableAmount = $this->refundableAmount($order);

        return view('orders.delete', compact('order', 'refundableAmount'));
    }

    public function store(Request $request, Order $order)
    {
        $this->guard($order);

        $order->loadMissing('payments');
        $refundableAmountForValidation = $this->refundableAmount($order);

        $request->validate([
            'refund_choice'    => $refundableAmountForValidation > 0 ? 'required|in:credit_to_advance,refund' : 'nullable|in:credit_to_advance,refund',
            'refund_method'    => 'required_if:refund_choice,refund|nullable|in:cash,bank_transfer',
            'refund_reference' => 'nullable|string|max:255',
            'refund_document'  => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
        ]);

        // Every design in an order carries the same per-size quantity (uniform sizing,
        // per "How Orders Work"), so one design's qty stands in for all of them — this
        // is exactly the per-size quantity that becomes free-pool stock for every design.
        $order->loadMissing('items');
        $firstItem = $order->items->first();
        $orderedBySize = collect(['xs', 's', 'm', 'l', 'xl'])
            ->mapWithKeys(fn($size) => [$size => (int) ($firstItem->{'qty_' . $size} ?? 0)]);

        $orderNumber      = $order->order_number;
        $customerName     = null;
        $refundChoiceMade = null;
        $refundableAmount = 0;

        DB::transaction(function () use ($request, $order, $orderedBySize, &$customerName, &$refundChoiceMade, &$refundableAmount) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            $customer    = $lockedOrder->customer()->lockForUpdate()->first();
            $lockedOrder->loadMissing(['payments', 'items.design', 'reductions', 'refunds']);

            $customerName = $customer->name;

            // A. Erase this order's OWN prior reduction/refund history.
            //    Refunds must be deleted before their parent OrderReductions —
            //    refunds.order_reduction_id stays RESTRICT.
            $oldRefundIds    = $lockedOrder->refunds->pluck('id');
            $oldReductionIds = $lockedOrder->reductions->pluck('id');

            DB::table('customer_ledger')
                ->where('reference_type', Refund::class)
                ->whereIn('reference_id', $oldRefundIds)
                ->delete();
            DB::table('customer_ledger')
                ->where('reference_type', OrderReduction::class)
                ->whereIn('reference_id', $oldReductionIds)
                ->delete();

            Refund::whereIn('id', $oldRefundIds)->delete();
            OrderReduction::whereIn('id', $oldReductionIds)->delete(); // order_reduction_items cascade

            // Delete ALL order_charged rows for this order — there can be more than
            // one (initial creation + any piece-reassignment additions).
            DB::table('customer_ledger')
                ->where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('transaction_type', 'order_charged')
                ->delete();

            // B. Reverse credit_applied entries (PaymentController::applyCredit() — no Payment row)
            $creditAppliedRows = DB::table('customer_ledger')
                ->where('reference_type', Order::class)
                ->where('reference_id', $lockedOrder->id)
                ->where('transaction_type', 'credit_applied')
                ->get();

            foreach ($creditAppliedRows as $row) {
                $customer->increment('advance_credit_balance', abs($row->amount));
                $customer->refresh();
                DB::table('customer_ledger')->where('id', $row->id)->delete();
            }

            // C. Capture refundable amount BEFORE payment_received ledger rows are deleted
            $refundableAmount = (float) DB::table('customer_ledger')
                ->where('reference_type', Payment::class)
                ->whereIn('reference_id', $lockedOrder->payments->pluck('id'))
                ->where('transaction_type', 'payment_received')
                ->sum(DB::raw('ABS(amount)'));

            // D. Reverse + delete every payment, one at a time
            foreach ($lockedOrder->payments as $payment) {
                app(PaymentController::class)->reversePaymentContribution($lockedOrder, $payment, $customer);
                $lockedOrder->total_paid -= $payment->amount; // in-memory only
                $customer->refresh();
                $payment->delete();
            }

            // E. Apply the admin's refund/credit choice to the refundable amount
            if ($refundableAmount > 0) {
                $refundChoiceMade = $request->refund_choice;

                if ($request->refund_choice === 'credit_to_advance') {
                    $runningBalanceBefore = $customer->advance_credit_balance;
                    $customer->increment('advance_credit_balance', $refundableAmount);
                    $customer->refresh();

                    // Unlike rule 5.17's overpayment surplus (still visible via the
                    // payment_received entry that stays in the ledger), this order's own
                    // order_charged/payment_received rows were just deleted (Steps A/D) —
                    // so without this entry, nothing in the ledger would explain where
                    // this credit came from. Mirrors the standalone Advance Payment
                    // pattern (rule 5.23) for the same reason: genuinely new money with
                    // no other transaction already documenting it.
                    CustomerLedger::create([
                        'customer_id'              => $customer->id,
                        'transaction_type'         => 'advance_received',
                        'amount'                   => -$refundableAmount,
                        'running_advance_balance'  => $runningBalanceBefore,
                        'reference_type'           => null,
                        'reference_id'             => null,
                        'notes'                    => "Credited from deleted Order #{$lockedOrder->order_number} on the {$lockedOrder->catalogue->name} catalogue",
                        'created_by'               => Auth::id(),
                    ]);
                } elseif ($request->refund_choice === 'refund') {
                    $documentPath = null;
                    if ($request->hasFile('refund_document')) {
                        $documentPath = $request->file('refund_document')->store('refund-documents', 's3');
                    }

                    // No customer_ledger entry here — unlike Log Reduction's refund_issued
                    // (which cancels out the negative balance an order_reduced entry just
                    // created), this delete flow already zeroed the order's ledger footprint
                    // by deleting its order_charged/payment_received rows outright (Steps A/D).
                    // Adding a positive refund_issued entry on top would double-count and
                    // incorrectly leave the customer looking like they owe the refunded amount.
                    // The Refund row alone is the audit trail for this transaction.
                    Refund::create([
                        'order_id'           => $lockedOrder->id,
                        'order_reduction_id' => null,
                        'order_number'       => $lockedOrder->order_number,
                        'catalogue_name'     => $lockedOrder->catalogue->name,
                        'customer_id'        => $customer->id,
                        'amount'             => $refundableAmount,
                        'refund_method'      => $request->refund_method,
                        'refund_reference'   => $request->refund_method === 'bank_transfer' ? $request->refund_reference : null,
                        'refund_document'    => $documentPath,
                        'refund_date'        => today(),
                        'notes'              => "Refund from deleted Order #{$lockedOrder->order_number}",
                        'refunded_by'        => Auth::id(),
                    ]);
                }
            }

            // F. Push this order's per-size quantities into the free-pieces pool — once
            //    per design (uniform sizing means every design gets the same amounts).
            //    Nothing is reassigned immediately anymore; that now happens later, from
            //    the Free Pieces screen.
            $sourceDesignIds = $lockedOrder->items->pluck('design_id');

            foreach ($sourceDesignIds as $designId) {
                foreach ($orderedBySize as $size => $qty) {
                    if ($qty <= 0) {
                        continue;
                    }

                    $freePiece = FreePiece::firstOrCreate(
                        ['catalogue_id' => $lockedOrder->catalogue_id, 'design_id' => $designId, 'size' => $size],
                        ['quantity' => 0]
                    );
                    $freePiece->increment('quantity', $qty);
                }
            }

            // G. Fire the production alert unconditionally — since pieces are no longer
            //    reassigned inline at delete time, every delete now represents a real,
            //    immediate demand decrease for the catalogue (same as Log Reduction).
            //    Must run BEFORE the order is deleted below — the ProductionAlert row's
            //    order_id FK needs a still-existing order to reference at insert time
            //    (it survives the delete via nullOnDelete, same as a fresh Refund row).
            app(ProductionAssignmentAlertService::class)
                ->checkOrder($lockedOrder, $sourceDesignIds->all(), 'order_deleted');

            // H. Delete the order itself last.
            $lockedOrder->delete();

            $freedSummary = $orderedBySize->filter(fn($qty) => $qty > 0)
                ->map(fn($qty, $size) => strtoupper($size) . ': ' . $qty)
                ->implode(', ') ?: 'None';

            $logProps = [
                'order'             => 'Order #' . $lockedOrder->order_number,
                'customer'          => $customer->name,
                'refund_choice'     => $refundChoiceMade === 'refund' ? 'Refunded'
                                        : ($refundChoiceMade === 'credit_to_advance' ? 'Credited to advance' : 'None (nothing paid)'),
                'refundable_amount' => 'PKR ' . number_format($refundableAmount, 0),
                'freed_pieces'      => $freedSummary . ' × ' . $sourceDesignIds->count() . ' designs',
            ];

            activity()
                ->causedBy(Auth::user())
                ->event('detail')
                ->withProperties($logProps)
                ->log("Order #{$lockedOrder->order_number} permanently deleted (refund/credit + pieces freed for reassignment)");
        });

        return redirect()->route('orders.index')
            ->with('success', 'Order #' . $orderNumber . ' has been permanently deleted.');
    }
}
