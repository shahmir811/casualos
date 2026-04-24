<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\DispatchBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DispatchController extends Controller
{
    public function index()
    {
        $orders = Order::where('status', 'stitching')
            ->with(['customer', 'catalogue'])
            ->latest()
            ->paginate(20);

        return view('production.dispatch.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'catalogue', 'items.design', 'dispatchBatches']);
        return view('production.dispatch.show', compact('order'));
    }

    public function create(Order $order)
    {
        $order->load(['items.design', 'customer']);
        return view('production.dispatch.create', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'dispatch_date'    => 'required|date',
            'shipping_address' => 'nullable|string',
            'cargo_document'   => 'nullable|string|max:100',
            'batch_number'     => 'nullable|string|max:50',
        ]);

        DispatchBatch::create([
            'order_id'         => $order->id,
            'batch_number'     => $validated['batch_number'] ?? ('DISP-' . $order->id . '-' . now()->format('Ymd')),
            'dispatch_date'    => $validated['dispatch_date'],
            'shipping_address' => $validated['shipping_address'] ?? $order->customer->city,
            'cargo_document'   => $validated['cargo_document'] ?? null,
            'logged_by'        => Auth::id(),
        ]);

        $order->update(['status' => 'dispatched']);

        return redirect()->route('dispatch.index')
            ->with('success', 'Order #' . $order->id . ' dispatched successfully.');
    }
}
