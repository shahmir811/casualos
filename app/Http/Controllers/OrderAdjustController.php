<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderAdjustController extends Controller
{
    public function create(Order $order)
    {
        abort_if(
            in_array($order->status, ['dispatched', 'cancelled']),
            403,
            'Cannot adjust a dispatched or cancelled order.'
        );

        $order->load(['items.design', 'catalogue', 'customer']);

        return view('orders.adjust', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        abort_if(
            in_array($order->status, ['dispatched', 'cancelled']),
            403,
            'Cannot adjust a dispatched or cancelled order.'
        );

        $request->validate([
            'qty_xs' => 'nullable|integer|min:0',
            'qty_s'  => 'nullable|integer|min:0',
            'qty_m'  => 'nullable|integer|min:0',
            'qty_l'  => 'nullable|integer|min:0',
            'qty_xl' => 'nullable|integer|min:0',
        ]);

        $qtyXS = max(0, (int) $request->input('qty_xs', 0));
        $qtyS  = max(0, (int) $request->input('qty_s',  0));
        $qtyM  = max(0, (int) $request->input('qty_m',  0));
        $qtyL  = max(0, (int) $request->input('qty_l',  0));
        $qtyXL = max(0, (int) $request->input('qty_xl', 0));

        $piecesPerDesign = $qtyXS + $qtyS + $qtyM + $qtyL + $qtyXL;

        if ($piecesPerDesign === 0) {
            return back()
                ->withErrors(['qty_s' => 'Please enter at least one piece quantity.'])
                ->withInput();
        }

        DB::transaction(function () use ($order, $qtyXS, $qtyS, $qtyM, $qtyL, $qtyXL, $piecesPerDesign) {
            $order->loadMissing(['items.design', 'catalogue']);

            // Mirror the exact pricing logic from PublicOrderController
            $benchmark   = $order->catalogue->quantity_benchmark;
            $useDiscount = $benchmark !== null && $piecesPerDesign > $benchmark;

            $newTotal = 0;

            foreach ($order->items as $item) {
                $design       = $item->design;
                $correctPrice = ($useDiscount && $design !== null && $design->discount_price !== null)
                    ? (int) round((float) $design->discount_price)
                    : (int) round((float) ($design?->selling_price ?? $item->unit_price));

                $item->unit_price = $correctPrice;
                $item->qty_xs     = $qtyXS;
                $item->qty_s      = $qtyS;
                $item->qty_m      = $qtyM;
                $item->qty_l      = $qtyL;
                $item->qty_xl     = $qtyXL;
                $item->save(); // booted() recomputes total_qty and total_amount using updated unit_price

                $newTotal += (float) $item->fresh()->total_amount;
            }

            $order->update([
                'total_amount'        => $newTotal,
                'outstanding_balance' => max(0, $newTotal - (float) $order->total_paid),
            ]);

            activity()
                ->performedOn($order)
                ->causedBy(Auth::user())
                ->event('detail')
                ->withProperties([
                    'order_number'      => $order->order_number,
                    'customer'          => $order->customer->name ?? $order->submitted_name,
                    'new_sizes'         => "XS:{$qtyXS} S:{$qtyS} M:{$qtyM} L:{$qtyL} XL:{$qtyXL}",
                    'pieces_per_design' => $piecesPerDesign,
                    'pricing_tier'      => $useDiscount ? 'discount' : 'normal',
                    'new_total_amount'  => 'PKR ' . number_format($newTotal, 0),
                    'adjusted_by'       => Auth::user()->name,
                ])
                ->log("Order #{$order->order_number} quantities adjusted by " . Auth::user()->name);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order quantities adjusted successfully.');
    }
}
