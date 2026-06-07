<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Design;
use App\Models\OutsourcedBatchItem;
use App\Models\PressSend;
use App\Models\PressSendItem;
use App\Models\PressReturn;
use App\Models\PressReturnItem;
use App\Models\TarpaiReturnItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PressController extends Controller
{
    public function index(Request $request)
    {
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: null;
        $selectedDesignId    = $request->filled('design_id') ? (int) $request->input('design_id') : null;

        $catalogueDesigns = $selectedCatalogueId
            ? Design::where('catalogue_id', $selectedCatalogueId)
                ->where('manufacturing_type', 'in_house')
                ->orderBy('sort_order')
                ->get()
            : collect();

        $sends = PressSend::with(['catalogue', 'items.design', 'returns.items', 'loggedBy'])
            ->when($selectedCatalogueId, fn($q) => $q->where('catalogue_id', $selectedCatalogueId))
            ->when($selectedDesignId,    fn($q) => $q->whereHas('items', fn($q2) => $q2->where('design_id', $selectedDesignId)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('production.press.index', compact(
            'sends', 'catalogueDesigns', 'selectedCatalogueId', 'selectedDesignId'
        ));
    }

    public function create()
    {
        $catalogueId = (int) session('active_catalogue_id');

        if (!$catalogueId) {
            return redirect()->route('press-sends.index')
                ->with('error', 'Please select a catalogue from the sidebar before logging a press send.');
        }

        $catalogue = Catalogue::with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')->orderBy('name')])
            ->findOrFail($catalogueId);

        $availableQty = $this->computeAvailableQty(collect([$catalogue]));

        $oldQuantities = [];
        foreach (old('designs', []) as $dData) {
            $designId = $dData['design_id'] ?? null;
            if (!$designId) continue;
            $oldQuantities[$designId] = [];
            foreach ($dData['items'] ?? [] as $item) {
                $size = $item['size'] ?? null;
                if ($size) $oldQuantities[$designId][$size] = (int) ($item['qty'] ?? 0);
            }
        }

        return view('production.press.create', compact('catalogue', 'availableQty', 'oldQuantities'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'           => 'required|exists:catalogues,id',
            'sent_date'              => 'required|date',
            'designs'                => 'required|array',
            'designs.*.design_id'    => 'required|exists:designs,id',
            'designs.*.items'        => 'required|array',
            'designs.*.items.*.size' => 'required|in:xs,s,m,l,xl',
            'designs.*.items.*.qty'  => 'nullable|integer|min:0',
        ]);

        $totalPieces = 0;
        foreach ($validated['designs'] as $d) {
            $totalPieces += collect($d['items'])->sum(fn($i) => (int) ($i['qty'] ?? 0));
        }
        if ($totalPieces === 0) {
            return back()->withInput()
                ->withErrors(['designs' => 'Please enter at least one piece quantity to log a press send.']);
        }

        foreach ($validated['designs'] as $designData) {
            $designId = $designData['design_id'];
            $design   = Design::find($designId);

            $tarpaiReturned = $this->getTarpaiReturnedBySize($validated['catalogue_id'], $designId);
            $alreadySent    = $this->getAlreadyPressSentBySize($validated['catalogue_id'], $designId);

            foreach ($designData['items'] as $item) {
                $qty  = (int) ($item['qty'] ?? 0);
                $size = $item['size'];
                if ($qty === 0) continue;

                $available = max(0, ($tarpaiReturned[$size] ?? 0) - ($alreadySent[$size] ?? 0));
                if ($qty > $available) {
                    $name = $design?->name ?? "Design #{$designId}";
                    return back()->withInput()->withErrors([
                        'designs' => "{$name} (" . strtoupper($size) . "): entered {$qty} but only {$available} available from Tarpai.",
                    ]);
                }
            }
        }

        $send = null;
        DB::transaction(function () use ($validated, &$send) {
            $send = PressSend::create([
                'catalogue_id' => $validated['catalogue_id'],
                'sent_date'    => $validated['sent_date'],
                'logged_by'    => Auth::id(),
            ]);

            foreach ($validated['designs'] as $designData) {
                foreach ($designData['items'] as $item) {
                    if ((int) ($item['qty'] ?? 0) > 0) {
                        $send->items()->create([
                            'design_id' => $designData['design_id'],
                            'size'      => $item['size'],
                            'quantity'  => (int) $item['qty'],
                        ]);
                    }
                }
            }
        });

        return redirect()->route('press-sends.show', $send)
            ->with('success', 'Press send recorded.');
    }

    public function show(PressSend $pressSend)
    {
        $pressSend->load(['catalogue', 'items.design', 'returns.items', 'loggedBy']);

        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        $sentByDesign     = $pressSend->items->groupBy('design_id');
        $returnedByDesign = $pressSend->returns->flatMap->items->groupBy('design_id');

        $outstandingByDesign = [];
        foreach ($sentByDesign as $designId => $sentItems) {
            $returnedItems = $returnedByDesign[$designId] ?? collect();
            foreach ($sizes as $size) {
                $sent     = $sentItems->where('size', $size)->sum('quantity');
                $returned = $returnedItems->where('size', $size)->sum('original_quantity');
                $outstandingByDesign[$designId][$size] = max(0, $sent - $returned);
            }
        }

        $designsById = $pressSend->items->pluck('design')->filter()->unique('id')->keyBy('id');

        return view('production.press.show', compact(
            'pressSend', 'sentByDesign', 'outstandingByDesign', 'designsById', 'sizes'
        ));
    }

    public function logReturn(Request $request, PressSend $pressSend)
    {
        $validated = $request->validate([
            'return_date'            => 'required|date',
            'designs'                => 'required|array',
            'designs.*.design_id'    => 'required|exists:designs,id',
            'designs.*.items'        => 'required|array',
            'designs.*.items.*.size' => 'required|in:xs,s,m,l,xl',
            'designs.*.items.*.qty'  => 'nullable|integer|min:0',
        ]);

        $totalReturning = 0;
        foreach ($validated['designs'] as $d) {
            $totalReturning += collect($d['items'])->sum(fn($i) => (int) ($i['qty'] ?? 0));
        }
        if ($totalReturning === 0) {
            return back()->withErrors(['designs' => 'Please enter at least one piece quantity to log a return.']);
        }

        $pressSend->load(['items', 'returns.items']);
        $allReturnedItems = $pressSend->returns->flatMap(fn($r) => $r->items);

        foreach ($validated['designs'] as $designData) {
            $designId = (int) $designData['design_id'];
            $design   = Design::find($designId);

            foreach ($designData['items'] as $item) {
                $qty  = (int) ($item['qty'] ?? 0);
                $size = $item['size'];
                if ($qty === 0) continue;

                $sentQty = (int) $pressSend->items
                    ->where('design_id', $designId)
                    ->where('size', $size)
                    ->sum('quantity');

                $returnedQty = (int) $allReturnedItems
                    ->where('design_id', $designId)
                    ->where('size', $size)
                    ->sum('original_quantity');

                $outstanding = max(0, $sentQty - $returnedQty);

                if ($qty > $outstanding) {
                    $name = $design?->name ?? "Design #{$designId}";
                    return back()->withErrors([
                        'designs' => "{$name} (" . strtoupper($size) . "): entered {$qty} but only {$outstanding} outstanding.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($pressSend, $validated) {
            $pressReturn = PressReturn::create([
                'press_send_id' => $pressSend->id,
                'return_date'   => $validated['return_date'],
                'logged_by'     => Auth::id(),
            ]);

            foreach ($validated['designs'] as $designData) {
                foreach ($designData['items'] as $item) {
                    if ((int) ($item['qty'] ?? 0) > 0) {
                        $pressReturn->items()->create([
                            'design_id'         => $designData['design_id'],
                            'size'              => $item['size'],
                            'quantity'          => (int) $item['qty'],
                            'original_quantity' => (int) $item['qty'],
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Press return logged. Pieces are now in packed inventory.');
    }

    public function inventory()
    {
        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        // Unified inventory: [catalogue_id][design_id][size] => total qty
        $data           = [];
        $catalogueNames = [];
        $designNames    = [];

        // In-house: pieces returned from press
        $pressItems = PressReturnItem::with([
            'pressReturn.send.catalogue',
            'design',
        ])->get();

        foreach ($pressItems as $item) {
            $catId   = $item->pressReturn->send->catalogue_id;
            $designId = $item->design_id;
            $catalogueNames[$catId]  = $item->pressReturn->send->catalogue->name ?? 'Unknown';
            $designNames[$designId]  = $item->design->name ?? '—';
            $data[$catId][$designId][$item->size] = ($data[$catId][$designId][$item->size] ?? 0) + $item->quantity;
        }

        // Outsourced: pieces received directly from external factory
        $outsourcedItems = OutsourcedBatchItem::with([
            'batch.catalogue',
            'design',
        ])->get();

        foreach ($outsourcedItems as $item) {
            $catId    = $item->batch->catalogue_id;
            $designId = $item->design_id;
            $catalogueNames[$catId] = $item->batch->catalogue->name ?? 'Unknown';
            $designNames[$designId] = $item->design->name ?? '—';
            $data[$catId][$designId][$item->size] = ($data[$catId][$designId][$item->size] ?? 0) + $item->quantity;
        }

        return view('production.press.inventory', compact('data', 'catalogueNames', 'designNames', 'sizes'));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function computeAvailableQty(Collection $catalogues): array
    {
        $available = [];
        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        foreach ($catalogues as $cat) {
            $available[$cat->id] = [];
            foreach ($cat->designs as $design) {
                $returned = $this->getTarpaiReturnedBySize($cat->id, $design->id);
                $sent     = $this->getAlreadyPressSentBySize($cat->id, $design->id);

                $perSize = [];
                foreach ($sizes as $size) {
                    $perSize[$size] = max(0, ($returned[$size] ?? 0) - ($sent[$size] ?? 0));
                }
                $available[$cat->id][$design->id] = $perSize;
            }
        }

        return $available;
    }

    private function getTarpaiReturnedBySize(int $catalogueId, int $designId): array
    {
        return TarpaiReturnItem::whereHas(
            'return',
            fn($q) => $q->whereHas('send', fn($q2) => $q2->where('catalogue_id', $catalogueId))
        )
        ->where('design_id', $designId)
        ->selectRaw('size, SUM(quantity) as total')
        ->groupBy('size')
        ->pluck('total', 'size')
        ->map(fn($v) => (int) $v)
        ->toArray();
    }

    private function getAlreadyPressSentBySize(int $catalogueId, int $designId): array
    {
        return PressSendItem::whereHas(
            'send',
            fn($q) => $q->where('catalogue_id', $catalogueId)
        )
        ->where('design_id', $designId)
        ->selectRaw('size, SUM(quantity) as total')
        ->groupBy('size')
        ->pluck('total', 'size')
        ->map(fn($v) => (int) $v)
        ->toArray();
    }
}
