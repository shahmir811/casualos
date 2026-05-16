<?php

namespace App\Http\Controllers;

use App\Models\Design;
use App\Models\DispatchBatch;
use App\Models\DispatchBatchItem;
use App\Models\Order;
use App\Models\OutsourcedBatchItem;
use App\Models\PressReturnItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    public function index(Request $request)
    {
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: null;
        $search = trim($request->input('search', ''));

        $orders = Order::where('status', 'stitching')
            ->with(['customer', 'catalogue', 'items', 'dispatchBatches.items'])
            ->when($selectedCatalogueId, fn($q) => $q->where('catalogue_id', $selectedCatalogueId))
            ->when($search, fn($q) => $q->whereHas('customer', fn($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('production.dispatch.index', compact('orders', 'search'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'catalogue', 'items.design', 'dispatchBatches.items.design']);
        return view('production.dispatch.show', compact('order'));
    }

    public function create(Order $order)
    {
        $sizes = ['xs', 's', 'm', 'l', 'xl'];
        $order->load(['items.design', 'customer']);

        // Quantities already dispatched per design per size across all previous batches
        $dispatchedRows = DispatchBatchItem::whereHas('batch', fn($q) => $q->where('order_id', $order->id))
            ->selectRaw('design_id, size, SUM(quantity) as total')
            ->groupBy('design_id', 'size')
            ->get()
            ->groupBy('design_id')
            ->map(fn($items) => $items->pluck('total', 'size')->map(fn($v) => (int) $v));

        $remaining = [];
        $inStock   = [];

        foreach ($order->items as $item) {
            $designId   = $item->design_id;
            $dispatched = $dispatchedRows[$designId] ?? collect();

            // Remaining from order: ordered - already dispatched
            foreach ($sizes as $size) {
                $ordered = (int) $item->{'qty_' . $size};
                $remaining[$designId][$size] = max(0, $ordered - ($dispatched[$size] ?? 0));
            }

            // Available in packed inventory per size
            if ($item->design?->manufacturing_type === 'outsourced') {
                $stockRows = OutsourcedBatchItem::whereHas('batch', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                    ->where('design_id', $designId)
                    ->selectRaw('size, SUM(quantity) as total')
                    ->groupBy('size')
                    ->pluck('total', 'size')
                    ->map(fn($v) => (int) $v);
            } else {
                $stockRows = PressReturnItem::whereHas('pressReturn.send', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                    ->where('design_id', $designId)
                    ->selectRaw('size, SUM(quantity) as total')
                    ->groupBy('size')
                    ->pluck('total', 'size')
                    ->map(fn($v) => (int) $v);
            }

            foreach ($sizes as $size) {
                $inStock[$designId][$size] = $stockRows[$size] ?? 0;
            }
        }

        return view('production.dispatch.create', compact('order', 'remaining', 'inStock', 'sizes'));
    }

    public function store(Request $request, Order $order)
    {
        $validated = $request->validate([
            'dispatch_date'          => 'required|date',
            'shipping_address'       => 'required|string|max:500',
            'cargo_document'         => 'nullable|file|mimes:pdf,jpeg,jpg,png|max:10240',
            'designs'                => 'required|array',
            'designs.*.design_id'    => 'required|exists:designs,id',
            'designs.*.items'        => 'required|array',
            'designs.*.items.*.size' => 'required|in:xs,s,m,l,xl',
            'designs.*.items.*.qty'  => 'nullable|integer|min:0',
        ]);

        // Require at least one piece
        $totalDispatching = 0;
        foreach ($validated['designs'] as $d) {
            $totalDispatching += collect($d['items'])->sum(fn($i) => (int) ($i['qty'] ?? 0));
        }
        if ($totalDispatching === 0) {
            return back()->withInput()->withErrors(['designs' => 'Please enter at least one piece quantity to record a dispatch.']);
        }

        // Server-side remaining check
        $order->load('items.design');
        $dispatchedRows = DispatchBatchItem::whereHas('batch', fn($q) => $q->where('order_id', $order->id))
            ->selectRaw('design_id, size, SUM(quantity) as total')
            ->groupBy('design_id', 'size')
            ->get()
            ->groupBy('design_id')
            ->map(fn($items) => $items->pluck('total', 'size')->map(fn($v) => (int) $v));

        foreach ($validated['designs'] as $designData) {
            $designId  = (int) $designData['design_id'];
            $orderItem = $order->items->firstWhere('design_id', $designId);
            if (!$orderItem) continue;

            $dispatched = $dispatchedRows[$designId] ?? collect();

            // Stock available in packed inventory per size
            if ($orderItem->design?->manufacturing_type === 'outsourced') {
                $stockRows = OutsourcedBatchItem::whereHas('batch', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                    ->where('design_id', $designId)
                    ->selectRaw('size, SUM(quantity) as total')
                    ->groupBy('size')
                    ->pluck('total', 'size')
                    ->map(fn($v) => (int) $v);
            } else {
                $stockRows = PressReturnItem::whereHas('pressReturn.send', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                    ->where('design_id', $designId)
                    ->selectRaw('size, SUM(quantity) as total')
                    ->groupBy('size')
                    ->pluck('total', 'size')
                    ->map(fn($v) => (int) $v);
            }

            foreach ($designData['items'] as $item) {
                $qty  = (int) ($item['qty'] ?? 0);
                $size = $item['size'];
                if ($qty === 0) continue;

                $name      = $orderItem->design->name ?? "Design #{$designId}";
                $remaining = max(0, (int) $orderItem->{'qty_' . $size} - ($dispatched[$size] ?? 0));
                $stock     = $stockRows[$size] ?? 0;

                if ($qty > $remaining) {
                    return back()->withInput()->withErrors([
                        'designs' => "{$name} (" . strtoupper($size) . "): entered {$qty} but only {$remaining} remaining in the order.",
                    ]);
                }
                if ($qty > $stock) {
                    return back()->withInput()->withErrors([
                        'designs' => "{$name} (" . strtoupper($size) . "): entered {$qty} but only {$stock} available in packed inventory.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($order, $validated, $request) {
            $cargoPath = null;
            if ($request->hasFile('cargo_document')) {
                $cargoPath = $request->file('cargo_document')->store('cargo-documents', 'public');
            }

            $nextBatchNumber = ($order->dispatchBatches()->max('batch_number') ?? 0) + 1;

            $batch = DispatchBatch::create([
                'order_id'         => $order->id,
                'batch_number'     => $nextBatchNumber,
                'dispatch_date'    => $validated['dispatch_date'],
                'shipping_address' => $validated['shipping_address'],
                'cargo_document'   => $cargoPath,
                'logged_by'        => Auth::id(),
            ]);

            foreach ($validated['designs'] as $designData) {
                foreach ($designData['items'] as $item) {
                    if ((int) ($item['qty'] ?? 0) > 0) {
                        $batch->items()->create([
                            'design_id' => $designData['design_id'],
                            'size'      => $item['size'],
                            'quantity'  => (int) $item['qty'],
                        ]);
                    }
                }
            }

            // Deduct dispatched quantities from packed inventory (FIFO)
            foreach ($validated['designs'] as $designData) {
                $designId = (int) $designData['design_id'];
                $design   = Design::find($designId);

                foreach ($designData['items'] as $item) {
                    $toDeduct = (int) ($item['qty'] ?? 0);
                    $size     = $item['size'];
                    if ($toDeduct === 0) continue;

                    if ($design?->manufacturing_type === 'outsourced') {
                        $rows = OutsourcedBatchItem::whereHas('batch', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                            ->where('design_id', $designId)
                            ->where('size', $size)
                            ->where('quantity', '>', 0)
                            ->orderBy('id')
                            ->get();
                    } else {
                        $rows = PressReturnItem::whereHas('pressReturn.send', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                            ->where('design_id', $designId)
                            ->where('size', $size)
                            ->where('quantity', '>', 0)
                            ->orderBy('id')
                            ->get();
                    }

                    foreach ($rows as $row) {
                        if ($toDeduct <= 0) break;
                        $deduct   = min($toDeduct, $row->quantity);
                        $row->decrement('quantity', $deduct);
                        $toDeduct -= $deduct;
                    }
                }
            }

            // Only mark dispatched when all ordered quantities have been sent
            $order->refresh();
            $order->load('items');
            if ($order->isFullyDispatched()) {
                $order->update(['status' => 'dispatched']);
            }
        });

        return redirect()->route('dispatch.index')
            ->with('success', 'Dispatch batch #' . (($order->dispatchBatches()->max('batch_number'))) . ' recorded for order #' . $order->order_number . '.');
    }
}
