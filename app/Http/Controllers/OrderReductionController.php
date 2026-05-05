<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReduction;
use App\Models\OrderReductionItem;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderReductionController extends Controller
{
    public function create(Order $order)
    {
        $order->load(['items.design']);
        return view('orders.reduce', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        $request->validate([
            'adjustment_type'  => 'required|in:damage,short_supply,price_correction,other',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.design_id'=> 'required|exists:designs,id',
            'items.*.size'     => 'required|in:xs,s,m,l,xl',
            'items.*.qty'      => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $order) {
            $totalReduced = 0;
            $itemData     = [];

            foreach ($request->items as $item) {
                $design      = \App\Models\Design::findOrFail($item['design_id']);
                $amount      = $design->selling_price * $item['qty'];
                $totalReduced += $amount;

                $itemData[] = [
                    'design_id'          => $item['design_id'],
                    'size'               => $item['size'],
                    'qty_reduced'        => $item['qty'],
                    'unit_price'         => $design->selling_price,
                    'amount_reduced'     => $amount,
                ];
            }

            $originalTotal = $order->total_amount;
            $newTotal      = max(0, $originalTotal - $totalReduced);

            $reduction = OrderReduction::create([
                'order_id'          => $order->id,
                'reduced_by'        => Auth::id(),
                'reduction_date'    => today(),
                'adjustment_type'   => $request->adjustment_type,
                'original_total'    => $originalTotal,
                'new_total'         => $newTotal,
                'adjustment_amount' => $totalReduced,
                'notes'             => $request->notes,
            ]);

            foreach ($itemData as $item) {
                $reduction->items()->create(['order_reduction_id' => $reduction->id] + $item);
            }

            // Update order totals
            $order->update([
                'total_amount'        => $newTotal,
                'outstanding_balance' => max(0, $newTotal - $order->total_paid),
            ]);

            // Credit to customer ledger
            CustomerLedger::create([
                'customer_id'             => $order->customer_id,
                'transaction_type'        => 'order_reduced',
                'amount'                  => -$totalReduced,
                'running_advance_balance' => 0,
                'reference_type'          => 'App\Models\OrderReduction',
                'reference_id'            => $reduction->id,
                'notes'                   => "Order reduction on #{$order->order_number}: {$request->adjustment_type}",
                'created_by'              => Auth::id(),
            ]);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order reduction logged successfully.');
    }
}
