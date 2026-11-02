<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReduction;
use App\Models\Refund;
use App\Services\AdvanceCreditAutoApplyService;
use App\Services\OrderStatusNotificationService;
use App\Services\ProductionAssignmentAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moves a specific design+size quantity out of one existing order (the
 * "source") and into another — an existing order on the same catalogue, or a
 * brand-new order for a customer who has none yet (the "target") — in one
 * transaction. Unlike Piece Reassignment (which only ever touches the
 * target), this also shrinks the source order using the same reduce/surplus/
 * auto-cancel logic Log Reduction already has. No OrderReduction row is
 * created — see the "Move Pieces" rule in CLAUDE.md for why.
 */
class OrderPieceMoveController extends Controller
{
    public function create(Order $order)
    {
        abort_if(
            in_array($order->status, ['dispatched', 'cancelled']),
            403,
            'Cannot move pieces out of a dispatched or cancelled order.'
        );

        $order->load(['items.design', 'catalogue', 'customer']);

        $existingTargets = Order::where('catalogue_id', $order->catalogue_id)
            ->where('id', '!=', $order->id)
            ->whereNotIn('status', ['dispatched', 'cancelled'])
            ->with('customer')
            ->get();

        $orderedCustomerIds = Order::where('catalogue_id', $order->catalogue_id)->pluck('customer_id');
        $newCustomerTargets = Customer::whereNotIn('id', $orderedCustomerIds)->orderBy('name')->get();

        return view('orders.move-pieces', compact('order', 'existingTargets', 'newCustomerTargets'));
    }

    public function store(Request $request, Order $order)
    {
        abort_if(
            in_array($order->status, ['dispatched', 'cancelled']),
            403,
            'Cannot move pieces out of a dispatched or cancelled order.'
        );

        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.design_id'  => 'required|exists:designs,id',
            'items.*.size'       => 'required|in:xs,s,m,l,xl',
            'items.*.qty'        => 'required|integer|min:0',
            'target_type'        => 'required|in:existing_order,new_customer',
            'target_id'          => 'required|integer',
            'surplus_action'     => 'required|in:none,credit_to_advance,refund',
            'refund_method'      => 'required_if:surplus_action,refund|nullable|in:cash,bank_transfer',
            'refund_reference'   => 'nullable|string|max:255',
            'refund_document'    => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
            'notes'              => 'nullable|string',
        ]);

        $nonZeroItems = array_values(array_filter($request->items ?? [], fn($i) => (int) ($i['qty'] ?? 0) > 0));
        if (empty($nonZeroItems)) {
            return back()->withErrors(['items' => 'Please enter at least one quantity to move.'])->withInput();
        }

        $targetType = $request->input('target_type');
        $targetId   = (int) $request->input('target_id');

        $statusBeforeMove = $order->status;
        $byName           = Auth::user()->name;

        try {
            DB::transaction(function () use (
                $request, $order, $nonZeroItems, $targetType, $targetId, $byName
            ) {
                $order = Order::where('id', $order->id)->lockForUpdate()->first();
                $order->load(['items.design', 'customer', 'catalogue']);

                $sourceItemsByDesign = $order->items->keyBy('design_id');

                // Validate against the source's CURRENT quantities (fresh, under lock)
                // and compute the reduction amount from each item's existing unit_price
                // — reductions never re-price, same convention as Log Reduction.
                $totalReduced = 0;
                $moveLines    = [];

                foreach ($nonZeroItems as $row) {
                    $sourceItem = $sourceItemsByDesign->get($row['design_id']);
                    if (! $sourceItem) {
                        throw new \RuntimeException('One of the selected designs is not on this order.');
                    }

                    $col       = 'qty_' . $row['size'];
                    $available = (int) $sourceItem->{$col};
                    $qty       = (int) $row['qty'];

                    if ($qty > $available) {
                        throw new \RuntimeException(
                            "Cannot move {$qty} " . strtoupper($row['size']) . ' of "' . ($sourceItem->design->name ?? 'this design')
                            . "\" — only {$available} available on this order."
                        );
                    }

                    $unitPrice     = (float) $sourceItem->unit_price;
                    $totalReduced += $unitPrice * $qty;

                    $moveLines[] = [
                        'design_id'  => $row['design_id'],
                        'design'     => $sourceItem->design->name ?? "Design #{$row['design_id']}",
                        'size'       => strtoupper($row['size']),
                        'qty'        => $qty,
                        'unit_price' => $unitPrice,
                        'amount'     => $unitPrice * $qty,
                    ];
                }

                // Resolve the target inside the lock, closing the same race
                // Free Pieces already guards against for new-customer targets.
                if ($targetType === 'existing_order') {
                    $targetOrder = Order::where('id', $targetId)->lockForUpdate()->first();

                    if (! $targetOrder
                        || $targetOrder->id === $order->id
                        || $targetOrder->catalogue_id !== $order->catalogue_id
                        || in_array($targetOrder->status, ['dispatched', 'cancelled'])
                    ) {
                        throw new \RuntimeException('Invalid target order for this move.');
                    }

                    $targetOrder->load(['items', 'customer']);
                    $targetCustomer = $targetOrder->customer;
                } else {
                    $targetCustomer = Customer::where('id', $targetId)->lockForUpdate()->first();
                    if (! $targetCustomer) {
                        throw new \RuntimeException('Invalid target customer for this move.');
                    }

                    $alreadyOrdered = Order::where('customer_id', $targetCustomer->id)
                        ->where('catalogue_id', $order->catalogue_id)
                        ->exists();
                    if ($alreadyOrdered) {
                        throw new \RuntimeException("{$targetCustomer->name} already has an order on this catalogue.");
                    }

                    $targetOrder = Order::create([
                        'catalogue_id'        => $order->catalogue_id,
                        'customer_id'         => $targetCustomer->id,
                        'status'              => 'received',
                        'total_amount'        => 0,
                        'total_paid'          => 0,
                        'outstanding_balance' => 0,
                        'submitted_name'      => $targetCustomer->name,
                        'submitted_city'      => $targetCustomer->city,
                        'submitted_email'     => $targetCustomer->email,
                        'submitted_at'        => now(),
                        'notes'               => 'Created from Move Pieces.',
                    ]);
                    $targetOrder->setRelation('items', collect());
                }

                $customer = $order->customer;

                // --- Source decrement ---

                foreach ($nonZeroItems as $row) {
                    $sourceItem = $sourceItemsByDesign->get($row['design_id']);
                    $col        = 'qty_' . $row['size'];
                    $sourceItem->{$col} = max(0, (int) $sourceItem->{$col} - (int) $row['qty']);
                    $sourceItem->save(); // OrderItem::booted() recomputes total_qty/total_amount
                }

                $targetLabel = $targetType === 'existing_order'
                    ? "Order #{$targetOrder->order_number} ({$targetCustomer->name})"
                    : "new Order #{$targetOrder->order_number} ({$targetCustomer->name})";

                // Group moved rows by design (one bullet line per design, sizes
                // comma-joined within it) — the header line is the only thing not
                // already shown by the row's own Date/Order/By columns, so that's
                // all it needs to say; items are rendered as bullets by the view.
                $itemsByDesign = [];
                foreach ($moveLines as $l) {
                    $itemsByDesign[$l['design_id']]['name'] = $l['design'];
                    $itemsByDesign[$l['design_id']]['parts'][] = "{$l['size']}×{$l['qty']}";
                }
                $itemLines = collect($itemsByDesign)
                    ->map(fn($d) => $d['name'] . ': ' . implode(', ', $d['parts']))
                    ->values()
                    ->all();

                $moveDesc = "Moved to {$targetLabel}";
                foreach ($itemLines as $line) {
                    $moveDesc .= "\n" . $line;
                }
                if ($request->notes) $moveDesc .= "\nNote: {$request->notes}";

                $originalTotal = (float) $order->total_amount;
                $newTotal      = max(0, $originalTotal - $totalReduced);
                $totalPaid     = (float) $order->total_paid;

                $surplus       = max(0, $totalPaid - $newTotal);
                $surplusAction = $surplus > 0 ? $request->surplus_action : 'none';

                // A Move is still a reduction on the source order's books, so it
                // gets a real OrderReduction (adjustment_type 'transfer', never
                // selectable from the Log Reduction form) — this is what lets the
                // customer ledger's "View" link open the same reduction-detail
                // modal Log Reduction entries already use, instead of there being
                // nothing behind the entry to view.
                $reduction = OrderReduction::create([
                    'order_id'          => $order->id,
                    'reduced_by'        => Auth::id(),
                    'reduction_date'    => today(),
                    'adjustment_type'   => 'transfer',
                    'surplus_action'    => $surplusAction,
                    'original_total'    => $originalTotal,
                    'new_total'         => $newTotal,
                    'adjustment_amount' => $totalReduced,
                    'notes'             => $request->notes,
                ]);
                foreach ($moveLines as $l) {
                    $reduction->items()->create([
                        'design_id'      => $l['design_id'],
                        'size'           => strtolower($l['size']),
                        'qty_reduced'    => $l['qty'],
                        'unit_price'     => $l['unit_price'],
                        'amount_reduced' => $l['amount'],
                    ]);
                }

                if ($surplus <= 0) {
                    $order->update([
                        'total_amount'        => $newTotal,
                        'outstanding_balance' => max(0, $newTotal - $totalPaid),
                    ]);

                    $this->ledgerEntry($order, 'order_reduced', -$totalReduced, $reduction, $customer, $moveDesc);
                } else {
                    $order->update([
                        'total_amount'        => $newTotal,
                        'outstanding_balance' => 0,
                    ]);

                    $this->ledgerEntry($order, 'order_reduced', -$totalReduced, $reduction, $customer, $moveDesc);

                    if ($surplusAction === 'credit_to_advance') {
                        $customer->increment('advance_credit_balance', $surplus);
                        $customer->refresh();
                    } elseif ($surplusAction === 'refund') {
                        $refundMethodLabel = $request->refund_method === 'bank_transfer' ? 'Bank Transfer' : 'Cash';

                        $documentPath = null;
                        if ($request->hasFile('refund_document')) {
                            $documentPath = $request->file('refund_document')->store('refund-documents', 's3');
                        }

                        $refund = Refund::create([
                            'order_id'           => $order->id,
                            'order_reduction_id' => $reduction->id,
                            'order_number'       => $order->order_number,
                            'catalogue_name'     => $order->catalogue->name,
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
                            "Refund PKR " . number_format($surplus, 0) . " via {$refundMethodLabel} (pieces moved to {$targetLabel})");
                    }
                }

                if ($newTotal == 0 && $order->status !== 'dispatched') {
                    $order->update(['status' => 'cancelled']);
                }

                if ($order->status === 'partially_dispatched') {
                    $order->unsetRelation('items')->load('items');
                    if ($order->isFullyDispatched()) {
                        $order->update(['status' => 'dispatched']);
                    }
                }

                // --- Target increment / create ---

                $requestedByDesign = collect($nonZeroItems)->groupBy('design_id');
                $targetItemsByDesign = $targetOrder->items->keyBy('design_id');
                $benchmark   = $order->catalogue->quantity_benchmark;
                $totalAdded  = 0;
                $targetDesignIds = [];

                foreach ($requestedByDesign as $designId => $rows) {
                    $targetDesignIds[] = $designId;
                    $design = $sourceItemsByDesign->get($designId)->design;

                    $item = $targetItemsByDesign->get($designId);
                    $oldAmount = 0;

                    if (! $item) {
                        $item = new OrderItem([
                            'order_id'   => $targetOrder->id,
                            'design_id'  => $designId,
                            'qty_xs'     => 0, 'qty_s' => 0, 'qty_m' => 0, 'qty_l' => 0, 'qty_xl' => 0,
                            'unit_price' => 0,
                        ]);
                    } else {
                        $oldAmount = (float) $item->total_amount;
                    }

                    foreach ($rows as $row) {
                        $col = 'qty_' . $row['size'];
                        $item->{$col} = (int) $item->{$col} + (int) $row['qty'];
                    }

                    $newDesignTotalQty = $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl;
                    $useDiscount = $benchmark !== null && $newDesignTotalQty > $benchmark;
                    $item->unit_price = (int) round(
                        ($useDiscount && $design->discount_price !== null)
                            ? (float) $design->discount_price
                            : (float) ($design->selling_price ?? $item->unit_price)
                    );

                    $item->save(); // booted() recomputes total_qty/total_amount

                    $totalAdded += (float) $item->total_amount - $oldAmount;
                }

                if ($targetType === 'existing_order') {
                    $targetOrder->increment('total_amount', $totalAdded);
                    $targetOrder->increment('outstanding_balance', $totalAdded);
                } else {
                    $targetOrder->update([
                        'total_amount'        => $totalAdded,
                        'outstanding_balance' => $totalAdded,
                    ]);
                }

                $sourceLabel = "Order #{$order->order_number} ({$customer->name})";
                $targetDesc  = "Pieces moved from {$sourceLabel}";
                foreach ($itemLines as $line) {
                    $targetDesc .= "\n" . $line;
                }
                $this->ledgerEntry(
                    $targetOrder,
                    'order_charged',
                    $totalAdded,
                    $targetOrder,
                    $targetCustomer,
                    $targetDesc
                );

                $autoAppliedPayment = null;
                if ($targetType === 'new_customer') {
                    $autoAppliedPayment = app(AdvanceCreditAutoApplyService::class)->apply($targetOrder, $targetCustomer);
                }

                // --- Alerts + activity log ---

                app(ProductionAssignmentAlertService::class)->checkOrder($order, array_column($moveLines, 'design_id'), 'pieces_moved_out');
                app(ProductionAssignmentAlertService::class)->checkOrder($targetOrder, $targetDesignIds, 'pieces_moved_in');

                activity()
                    ->performedOn($reduction)
                    ->causedBy(Auth::user())
                    ->event('detail')
                    ->withProperties([
                        'order'    => 'Order #' . $order->order_number,
                        'customer' => $customer->name,
                        'moved_to' => $targetLabel,
                        'items'    => collect($moveLines)->map(fn($l) => [
                            'design' => $l['design'], 'size' => $l['size'], 'qty' => $l['qty'],
                            'amount' => 'PKR ' . number_format($l['amount'], 0),
                        ])->toArray(),
                        'amount_moved' => 'PKR ' . number_format($totalReduced, 0),
                        'new_total'    => 'PKR ' . number_format($newTotal, 0),
                    ])
                    ->log("Order #{$order->order_number} had pieces moved to {$targetLabel} by {$byName}");

                activity()
                    ->performedOn($targetOrder)
                    ->causedBy(Auth::user())
                    ->event('detail')
                    ->withProperties([
                        'order'         => 'Order #' . $targetOrder->order_number,
                        'customer'      => $targetCustomer->name,
                        'moved_from'    => $sourceLabel,
                        'amount_added'  => 'PKR ' . number_format($totalAdded, 0),
                        'new_total'     => 'PKR ' . number_format((float) $targetOrder->total_amount, 0),
                    ])
                    ->log("Order #{$targetOrder->order_number} received pieces moved from {$sourceLabel} by {$byName}");

                if ($autoAppliedPayment) {
                    activity()
                        ->performedOn($targetOrder)
                        ->causedBy(Auth::user())
                        ->event('detail')
                        ->withProperties([
                            'order'          => 'Order #' . $targetOrder->order_number,
                            'customer'       => $targetCustomer->name,
                            'amount'         => 'PKR ' . number_format((float) $autoAppliedPayment->amount, 0),
                            'auto_confirmed' => $targetOrder->status === 'confirmed' ? 'Yes' : 'No',
                        ])
                        ->log('Payment #' . $targetOrder->order_number . 'p' . $autoAppliedPayment->sequence_number
                            . ' of PKR ' . number_format((float) $autoAppliedPayment->amount, 0)
                            . ' auto-applied from advance credit on Order #' . $targetOrder->order_number);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        $order->refresh();
        if ($order->status !== $statusBeforeMove && in_array($order->status, ['cancelled', 'dispatched'], true)) {
            $order->loadMissing('customer');
            app(OrderStatusNotificationService::class)->notify($order, $order->status);
        }

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pieces moved successfully.');
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
