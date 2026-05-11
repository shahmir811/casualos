<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $catalogues = Catalogue::orderBy('name')->get(['id', 'name', 'qty_per_design', 'number_of_designs']);

        $query = Order::with(['customer', 'catalogue', 'items'])
            ->latest('submitted_at');

        // Filter by catalogue
        $selectedCatalogueId = $request->input('catalogue_id');
        if ($selectedCatalogueId) {
            $query->where('catalogue_id', $selectedCatalogueId);
        }

        // Filter by status
        if ($request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        // When filtering by catalogue, load all (no pagination) — mirrors the PDF sheet
        $orders = $selectedCatalogueId
            ? $query->get()
            : $query->paginate(50);

        // Summary totals (size columns + grand total)
        $summary = [
            'xs'    => 0, 's'  => 0, 'm'  => 0,
            'l'     => 0, 'xl' => 0, 'total_pieces' => 0, 'total_bill' => 0,
        ];

        $collection = $selectedCatalogueId ? $orders : $orders->getCollection();
        foreach ($collection as $order) {
            // Use first item only — quantities per design are the same across all designs
            $item = $order->items->first();
            if ($item) {
                $summary['xs']           += $item->qty_xs;
                $summary['s']            += $item->qty_s;
                $summary['m']            += $item->qty_m;
                $summary['l']            += $item->qty_l;
                $summary['xl']           += $item->qty_xl;
                $summary['total_pieces'] += $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl;
            }
            $summary['total_bill'] += $order->total_amount;
        }

        $selectedCatalogue = $selectedCatalogueId
            ? $catalogues->firstWhere('id', $selectedCatalogueId)
            : null;

        return view('orders.index', compact(
            'orders', 'catalogues', 'selectedCatalogue', 'selectedCatalogueId', 'summary'
        ));
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

        return back()->with('success', 'Order #' . $order->order_number . ' confirmed.');
    }

    public function markStitching(Order $order)
    {
        if (!in_array(Auth::user()->role, ['admin', 'manager'])) {
            abort(403);
        }

        if ($order->status !== 'confirmed') {
            return back()->with('error', 'Only confirmed orders can be sent to stitching.');
        }

        $order->update(['status' => 'stitching']);

        return back()->with('success', 'Order #' . $order->order_number . ' moved to stitching.');
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
