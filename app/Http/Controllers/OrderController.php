<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Catalogue;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['customer', 'catalogue'])
            ->latest()
            ->paginate(20);
        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'catalogue', 'items.design', 'payments', 'reductions.items']);
        return view('orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        return view('orders.edit', compact('order'));
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'notes'      => 'nullable|string',
            'is_flagged' => 'nullable|boolean',
        ]);

        $order->update($validated);

        return redirect()->route('orders.show', $order)->with('success', 'Order updated.');
    }

    public function confirm(Order $order)
    {
        if ($order->status !== 'received') {
            return back()->with('error', 'Only received orders can be confirmed.');
        }

        $order->update(['status' => 'confirmed']);

        return back()->with('success', 'Order #' . $order->id . ' confirmed.');
    }

    public function markStitching(Order $order)
    {
        if ($order->status !== 'confirmed') {
            return back()->with('error', 'Only confirmed orders can be sent to stitching.');
        }

        $order->update(['status' => 'stitching']);

        return back()->with('success', 'Order #' . $order->id . ' moved to stitching.');
    }

    public function flagged()
    {
        $orders = Order::where('is_flagged', true)
            ->with(['customer', 'catalogue'])
            ->latest()
            ->paginate(20);

        return view('orders.flagged', compact('orders'));
    }
}
