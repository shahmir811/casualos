<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Design;
use App\Models\DesignCountryPrice;
use App\Models\DispatchBatch;
use App\Models\DispatchBatchItem;
use App\Models\Order;
use App\Models\OutsourcedBatchItem;
use App\Models\PieceTag;
use App\Models\PressReturnItem;
use App\Services\OrderStatusNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorSVG;

class DispatchController extends Controller
{
    public function index(Request $request)
    {
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: null;
        $search         = trim($request->input('search', ''));
        $paymentStatus  = $request->input('payment_status', '');
        $dispatchStatus = $request->input('dispatch_status', '');
        $dateFrom       = $request->input('date_from', '');
        $dateTo         = $request->input('date_to', '');

        $orders = Order::with(['customer', 'catalogue', 'items', 'dispatchBatches.items'])
            ->when($selectedCatalogueId, fn($q) => $q->where('catalogue_id', $selectedCatalogueId))
            ->when($search, fn($q) => $q->whereHas('customer', fn($q2) => $q2->where('name', 'like', "%{$search}%")))
            ->when($paymentStatus === 'not_paid', fn($q) => $q->where('total_paid', '<=', 0))
            ->when($paymentStatus === 'partially_paid', fn($q) => $q->where('total_paid', '>', 0)->where('outstanding_balance', '>', 0))
            ->when($paymentStatus === 'fully_paid', fn($q) => $q->where('total_paid', '>', 0)->where('outstanding_balance', '<=', 0))
            ->when($dispatchStatus === 'pending', fn($q) => $q->whereNotIn('status', ['partially_dispatched', 'dispatched']))
            ->when($dispatchStatus === 'partial', fn($q) => $q->where('status', 'partially_dispatched'))
            ->when($dispatchStatus === 'complete', fn($q) => $q->where('status', 'dispatched'))
            ->when($dateFrom, fn($q) => $q->whereDate('updated_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('updated_at', '<=', $dateTo))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('production.dispatch.index', compact(
            'orders', 'search', 'paymentStatus', 'dispatchStatus', 'dateFrom', 'dateTo'
        ));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'catalogue', 'items.design', 'dispatchBatches.items.design']);
        return view('production.dispatch.show', compact('order'));
    }

    public function sackLabel(Order $order)
    {
        $order->load(['customer', 'catalogue']);

        $logoDataUri = pdf_logo_data_uri();

        $pdf = Pdf::loadView('production.dispatch.sack-label-pdf', compact('order', 'logoDataUri'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('sack-label-' . $order->order_number . '.pdf');
    }

    public function printTags(Order $order)
    {
        $order->load(['items.design', 'customer']);
        $sizes   = ['xs', 's', 'm', 'l', 'xl'];
        $country = $order->customer->country;

        if (empty($country)) {
            return back()->with('error',
                "This order's customer (" . ($order->customer->name ?? $order->submitted_name) . ") has no country set. Edit the customer to add a country before printing tags."
            );
        }

        // Every design in the order must have a price set for the customer's country first
        $missingDesigns = [];
        foreach ($order->items as $item) {
            $hasQty = $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl > 0;
            if (!$hasQty) continue;

            $hasPrice = DesignCountryPrice::where('design_id', $item->design_id)
                ->where('country', $country)
                ->exists();

            if (!$hasPrice) {
                $missingDesigns[] = $item->design->name ?? "Design #{$item->design_id}";
            }
        }

        if (!empty($missingDesigns)) {
            return back()->with('error',
                "Set a {$country} price in Country Pricing for: " . implode(', ', array_unique($missingDesigns)) . ' before printing tags.'
            );
        }

        $barcodeGenerator = new BarcodeGeneratorSVG();
        $pages = [];

        foreach ($order->items as $item) {
            foreach ($sizes as $size) {
                $qty = (int) $item->{'qty_' . $size};
                if ($qty === 0) continue;

                $tag = PieceTag::firstOrNew([
                    'order_id'  => $order->id,
                    'design_id' => $item->design_id,
                    'size'      => $size,
                ]);

                if (!$tag->exists) {
                    $priceRow      = DesignCountryPrice::where('design_id', $item->design_id)
                        ->where('country', $country)
                        ->first();
                    $tag->country = $country;
                    $tag->price   = $priceRow->price;
                    $tag->save();
                }

                $page = [
                    'size'        => strtoupper($size),
                    'price'       => $tag->price,
                    'symbol'      => Customer::CURRENCY_SYMBOLS[$country] ?? '',
                    'barcode_svg' => base64_encode($this->fittedBarcodeSvg($barcodeGenerator, $tag->barcode, 110, 20)),
                    'barcode_text'=> $tag->barcode,
                ];

                for ($i = 0; $i < $qty; $i++) {
                    $pages[] = $page;
                }
            }
        }

        // One physical 2"x1" label per page, sized for the Zebra label roll
        $pdf = Pdf::loadView('production.dispatch.piece-tags-pdf', compact('pages'))
            ->setPaper([0, 0, 144, 72]);

        return $pdf->download('piece-tags-' . $order->order_number . '.pdf');
    }

    // Renders the barcode at exactly $maxWidth points wide so it always fits inside the 2"x1" label,
    // regardless of how many digits the barcode value has.
    private function fittedBarcodeSvg(BarcodeGeneratorSVG $generator, string $code, float $maxWidth, float $height): string
    {
        $baseline = $generator->getBarcode($code, BarcodeGeneratorSVG::TYPE_CODE_128_C, 1, $height);
        $baseWidth = preg_match('/width="([\d.]+)"/', $baseline, $m) ? (float) $m[1] : $maxWidth;
        $widthFactor = $baseWidth > 0 ? $maxWidth / $baseWidth : 1.0;

        return $generator->getBarcode($code, BarcodeGeneratorSVG::TYPE_CODE_128_C, $widthFactor, $height);
    }

    public function workspace(Order $order)
    {
        $this->denyCreativeHead();
        $sizes = ['xs', 's', 'm', 'l', 'xl'];
        $order->load(['items.design', 'customer', 'catalogue', 'dispatchBatches.items.design']);

        // Per-design, per-size totals already dispatched
        $dispatchedTotals = [];
        foreach ($order->dispatchBatches as $batch) {
            foreach ($batch->items as $item) {
                $dispatchedTotals[$item->design_id][$item->size] =
                    ($dispatchedTotals[$item->design_id][$item->size] ?? 0) + $item->quantity;
            }
        }

        // Remaining from order and stock in packed inventory (same logic as create)
        $remaining = [];
        $inStock   = [];

        foreach ($order->items as $item) {
            $designId   = $item->design_id;
            $dispatched = collect($dispatchedTotals[$designId] ?? []);

            foreach ($sizes as $size) {
                $ordered = (int) $item->{'qty_' . $size};
                $remaining[$designId][$size] = max(0, $ordered - ($dispatched[$size] ?? 0));
            }

            if ($item->design?->manufacturing_type === 'outsourced') {
                $stockRows = \App\Models\OutsourcedBatchItem::whereHas('batch', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
                    ->where('design_id', $designId)
                    ->selectRaw('size, SUM(quantity) as total')
                    ->groupBy('size')
                    ->pluck('total', 'size')
                    ->map(fn($v) => (int) $v);
            } else {
                $stockRows = \App\Models\PressReturnItem::whereHas('pressReturn.send', fn($q) => $q->where('catalogue_id', $order->catalogue_id))
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

        return view('production.dispatch.workspace', compact('order', 'sizes', 'remaining', 'inStock', 'dispatchedTotals'));
    }

    public function create(Order $order)
    {
        $this->denyCreativeHead();
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
        $this->denyCreativeHead();
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

        $dispatchBatch = null;
        DB::transaction(function () use ($order, $validated, $request, &$dispatchBatch) {
            $cargoPath = null;
            if ($request->hasFile('cargo_document')) {
                $cargoPath = $request->file('cargo_document')->store('cargo-documents', 's3');
            }

            $nextBatchNumber = ($order->dispatchBatches()->max('batch_number') ?? 0) + 1;

            $dispatchBatch = DispatchBatch::create([
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
                        $dispatchBatch->items()->create([
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

            // Update order status based on dispatch completeness
            $order->refresh();
            $order->load('items');
            if ($order->isFullyDispatched()) {
                $order->update(['status' => 'dispatched']);
            } else {
                $order->update(['status' => 'partially_dispatched']);
            }
        });

        $order->loadMissing('customer');
        app(OrderStatusNotificationService::class)->notify($order, $order->status);

        $batchNumber = $order->dispatchBatches()->max('batch_number');

        $dispatchBatch->loadMissing(['items.design', 'order.customer', 'order.catalogue']);
        $itemDetails = $dispatchBatch->items->map(fn($i) => [
            'design' => $i->design->name ?? "Design #{$i->design_id}",
            'size'   => strtoupper($i->size),
            'qty'    => $i->quantity,
        ])->toArray();
        activity()
            ->performedOn($dispatchBatch)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'order_number'    => $order->order_number,
                'customer'        => $order->customer->name ?? $order->submitted_name,
                'catalogue'       => $dispatchBatch->order->catalogue->name ?? '—',
                'batch_number'    => $batchNumber,
                'dispatch_date'   => $dispatchBatch->dispatch_date->format('d M Y'),
                'shipping_address'=> $validated['shipping_address'],
                'total_pieces'    => $dispatchBatch->items->sum('quantity'),
                'items'           => $itemDetails,
            ])
            ->log("Dispatch batch #{$batchNumber} recorded for Order #{$order->order_number}");

        return redirect()->route('dispatch.show', $order)
            ->with('success', "Dispatch batch #{$batchNumber} recorded successfully.");
    }

    public function updateCargoDocument(Request $request, DispatchBatch $dispatchBatch)
    {
        $this->denyCreativeHead();

        $request->validate([
            'cargo_document' => 'required|file|mimes:pdf,jpeg,jpg,png|max:10240',
        ]);

        $oldPath = $dispatchBatch->cargo_document;
        $newPath = $request->file('cargo_document')->store('cargo-documents', 's3');

        $dispatchBatch->update(['cargo_document' => $newPath]);

        if ($oldPath) {
            Storage::disk('s3')->delete($oldPath);
        }

        return back()->with('success', "Cargo document for Batch #{$dispatchBatch->batch_number} " . ($oldPath ? 'replaced' : 'attached') . '.');
    }
}
