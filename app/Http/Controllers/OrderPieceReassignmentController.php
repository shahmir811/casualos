<?php

namespace App\Http\Controllers;

use App\Models\CustomerLedger;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderPieceReassignmentController extends Controller
{
    public function create(Order $order)
    {
        $order->load(['items.design', 'catalogue', 'customer']);

        // Target orders: same catalogue, not dispatched/cancelled, not this order
        $targetOrders = Order::where('catalogue_id', $order->catalogue_id)
            ->where('id', '!=', $order->id)
            ->whereNotIn('status', ['dispatched', 'cancelled'])
            ->with('customer')
            ->get();

        return view('orders.reassign-pieces', compact('order', 'targetOrders'));
    }

    public function store(Request $request, Order $order)
    {
        $request->validate([
            'target_order_id'   => 'required|exists:orders,id',
            'items'             => 'required|array|min:1',
            'items.*.design_id' => 'required|exists:designs,id',
            'items.*.size'      => 'required|in:xs,s,m,l,xl',
            'items.*.qty'       => 'required|integer|min:1',
            'notes'             => 'nullable|string',
        ]);

        $targetOrder = Order::with(['items', 'customer'])->findOrFail($request->target_order_id);

        if ($targetOrder->catalogue_id !== $order->catalogue_id) {
            return back()->withErrors(['target_order_id' => 'Target order must be from the same catalogue.']);
        }

        DB::transaction(function () use ($request, $order, $targetOrder) {
            $targetItemsByDesign = $targetOrder->items->keyBy('design_id');
            $totalAdded          = 0;

            foreach ($request->items as $item) {
                $designId  = $item['design_id'];
                $size      = $item['size'];
                $qty       = (int) $item['qty'];
                $sizeCol   = 'qty_' . $size;

                $targetItem = $targetItemsByDesign->get($designId);

                if ($targetItem) {
                    $targetItem->increment($sizeCol, $qty);
                    // Re-save to trigger auto-total computation
                    $targetItem->refresh();
                    $targetItem->save();
                    $totalAdded += $targetItem->unit_price * $qty;
                }
            }

            if ($totalAdded > 0) {
                $targetOrder->increment('total_amount', $totalAdded);
                $targetOrder->increment('outstanding_balance', $totalAdded);

                $customer = $targetOrder->customer;

                CustomerLedger::create([
                    'customer_id'             => $targetOrder->customer_id,
                    'transaction_type'        => 'order_charged',
                    'amount'                  => -$totalAdded,
                    'running_advance_balance' => (float) $customer->advance_credit_balance,
                    'reference_type'          => Order::class,
                    'reference_id'            => $targetOrder->id,
                    'notes'                   => "Pieces added from Order #{$order->order_number}" . ($request->notes ? ": {$request->notes}" : ''),
                    'created_by'              => Auth::id(),
                ]);
            }
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pieces reassigned successfully.');
    }
}
