<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Design;
use App\Models\StitchingReturnItem;
use App\Models\TarpaiReturn;
use App\Models\TarpaiSend;
use App\Models\TarpaiSendItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TarpaiController extends Controller
{
    public function index(Request $request)
    {
        $house               = $request->input('house', '');
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: '';
        $selectedDesignId    = $request->get('design_id', '');
        $catalogueDesigns = $selectedCatalogueId
            ? Design::where('catalogue_id', $selectedCatalogueId)
                ->where('manufacturing_type', 'in_house')
                ->orderBy('sort_order')
                ->get()
            : collect();

        // ── Sends list ───────────────────────────────────────────────────
        $sends = TarpaiSend::with(['catalogue', 'items.design', 'returns.items'])
            ->when($house,               fn($q) => $q->where('tarpai_house', $house))
            ->when($selectedCatalogueId, fn($q) => $q->where('catalogue_id', $selectedCatalogueId))
            ->when($selectedDesignId,    fn($q) => $q->whereHas('items', fn($q2) => $q2->where('design_id', $selectedDesignId)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // ── Summary: pieces returned from Tarpai per design per size ─────
        $catalogues = Catalogue::when($selectedCatalogueId, fn($q) => $q->where('id', $selectedCatalogueId))
            ->with(['designs' => fn($q) => $q
                ->where('manufacturing_type', 'in_house')
                ->when($selectedDesignId, fn($q2) => $q2->where('id', $selectedDesignId))
                ->orderBy('name')])
            ->orderBy('name')
            ->get();

        $catIds = $catalogues->pluck('id');
        $sizes  = ['xs', 's', 'm', 'l', 'xl'];

        $returnTotals = DB::table('tarpai_return_items')
            ->join('tarpai_returns', 'tarpai_returns.id', '=', 'tarpai_return_items.tarpai_return_id')
            ->join('tarpai_sends', 'tarpai_sends.id', '=', 'tarpai_returns.tarpai_send_id')
            ->whereIn('tarpai_sends.catalogue_id', $catIds)
            ->when($house,            fn($q) => $q->where('tarpai_sends.tarpai_house', $house))
            ->when($selectedDesignId, fn($q) => $q->where('tarpai_return_items.design_id', $selectedDesignId))
            ->select(
                'tarpai_sends.catalogue_id',
                'tarpai_return_items.design_id',
                'tarpai_return_items.size',
                DB::raw('SUM(tarpai_return_items.quantity) as total')
            )
            ->groupBy('tarpai_sends.catalogue_id', 'tarpai_return_items.design_id', 'tarpai_return_items.size')
            ->get()
            ->groupBy(['catalogue_id', 'design_id']);

        $designSummary = $catalogues->map(function ($cat) use ($returnTotals, $sizes) {
            $designs = $cat->designs->map(function ($design) use ($cat, $returnTotals, $sizes) {
                $perSize = [];
                foreach ($sizes as $size) {
                    $perSize[$size] = (int) ($returnTotals[$cat->id][$design->id] ?? collect())
                        ->where('size', $size)->sum('total');
                }
                return ['name' => $design->name, 'sizes' => $perSize, 'total' => array_sum($perSize)];
            });
            return ['catalogue' => $cat->name, 'designs' => $designs];
        });

        return view('production.tarpai.index', compact(
            'sends', 'house', 'designSummary', 'sizes',
            'catalogueDesigns', 'selectedCatalogueId', 'selectedDesignId'
        ));
    }

    public function create()
    {
        $this->denyCreativeHead();
        $catalogueId = (int) session('active_catalogue_id');

        if (!$catalogueId) {
            return redirect()->route('tarpai-sends.index')
                ->with('error', 'Please select a catalogue from the sidebar before logging a Tarpai send.');
        }

        $catalogue = Catalogue::with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')->orderBy('name')])
            ->findOrFail($catalogueId);

        $availableQty = $this->computeAvailableQty(collect([$catalogue]));

        // Restore old quantities after a validation error
        $oldQuantities = [];
        foreach (old('designs', []) as $dData) {
            $designId = $dData['design_id'] ?? null;
            if (!$designId) continue;
            $oldQuantities[$designId] = [];
            foreach ($dData['items'] ?? [] as $item) {
                $size = $item['size'] ?? null;
                if ($size) $oldQuantities[$designId][$size] = (int)($item['qty'] ?? 0);
            }
        }

        return view('production.tarpai.create', compact('catalogue', 'availableQty', 'oldQuantities'));
    }

    public function store(Request $request)
    {
        $this->denyCreativeHead();
        $validated = $request->validate([
            'catalogue_id'           => 'required|exists:catalogues,id',
            'tarpai_house'           => 'required|in:rashid_bhai,yousaf_bhai,in_house',
            'sent_date'              => 'required|date',
            'per_piece_price'        => 'nullable|numeric|min:0',
            'designs'                => 'required|array',
            'designs.*.design_id'    => 'required|exists:designs,id',
            'designs.*.items'        => 'required|array',
            'designs.*.items.*.size' => 'required|in:xs,s,m,l,xl',
            'designs.*.items.*.qty'  => 'nullable|integer|min:0',
        ]);

        // At least one piece across all designs
        $totalPieces = 0;
        foreach ($validated['designs'] as $d) {
            $totalPieces += collect($d['items'])->sum(fn($i) => (int) ($i['qty'] ?? 0));
        }
        if ($totalPieces === 0) {
            return back()->withInput()
                ->withErrors(['designs' => 'Please enter at least one piece quantity to log a Tarpai send.']);
        }

        // Validate no quantity exceeds available kameez pieces
        foreach ($validated['designs'] as $designData) {
            $designId = $designData['design_id'];
            $design   = Design::find($designId);

            $stitchingKameez = $this->getStitchingKameezBySize($validated['catalogue_id'], $designId);
            $alreadySent     = $this->getAlreadySentBySize($validated['catalogue_id'], $designId);

            foreach ($designData['items'] as $item) {
                $qty  = (int) $item['qty'];
                $size = $item['size'];
                if ($qty === 0) continue;
                $available = max(0, ($stitchingKameez[$size] ?? 0) - ($alreadySent[$size] ?? 0));
                if ($qty > $available) {
                    $name = $design?->name ?? "Design #{$designId}";
                    return back()->withInput()->withErrors([
                        'designs' => "{$name} (" . strtoupper($size) . "): entered {$qty} but only {$available} available.",
                    ]);
                }
            }
        }

        $send = null;
        DB::transaction(function () use ($validated, &$send) {
            $send = TarpaiSend::create([
                'catalogue_id'    => $validated['catalogue_id'],
                'tarpai_house'    => $validated['tarpai_house'],
                'sent_date'       => $validated['sent_date'],
                'per_piece_price' => $validated['per_piece_price'] ?? 0,
                'logged_by'       => Auth::id(),
            ]);

            foreach ($validated['designs'] as $designData) {
                foreach ($designData['items'] as $item) {
                    if ((int) $item['qty'] > 0) {
                        $send->items()->create([
                            'design_id' => $designData['design_id'],
                            'size'      => $item['size'],
                            'quantity'  => (int) $item['qty'],
                        ]);
                    }
                }
            }
        });

        return redirect()->route('tarpai-sends.show', $send)
            ->with('success', 'Tarpai send recorded.');
    }

    public function show(TarpaiSend $tarpaiSend)
    {
        $tarpaiSend->load(['catalogue', 'items.design', 'returns.items', 'loggedBy']);

        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        // Group sent items by design_id
        $sentByDesign = $tarpaiSend->items->groupBy('design_id');

        // Group all returned items by design_id across all return batches
        $returnedByDesign = $tarpaiSend->returns->flatMap->items->groupBy('design_id');

        // Outstanding qty per design per size
        $outstandingByDesign = [];
        foreach ($sentByDesign as $designId => $sentItems) {
            $returnedItems = $returnedByDesign[$designId] ?? collect();
            foreach ($sizes as $size) {
                $sent     = $sentItems->where('size', $size)->sum('quantity');
                $returned = $returnedItems->where('size', $size)->sum('quantity');
                $outstandingByDesign[$designId][$size] = max(0, $sent - $returned);
            }
        }

        $designsById = $tarpaiSend->items->pluck('design')->filter()->unique('id')->keyBy('id');

        return view('production.tarpai.show', compact(
            'tarpaiSend', 'sentByDesign', 'outstandingByDesign', 'designsById', 'sizes'
        ));
    }

    public function logReturn(Request $request, TarpaiSend $send)
    {
        $this->denyCreativeHead();
        $validated = $request->validate([
            'return_date'            => 'required|date',
            'designs'                => 'required|array',
            'designs.*.design_id'    => 'nullable|exists:designs,id',
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

        DB::transaction(function () use ($send, $validated) {
            $return = TarpaiReturn::create([
                'tarpai_send_id' => $send->id,
                'return_date'    => $validated['return_date'],
                'logged_by'      => Auth::id(),
            ]);

            foreach ($validated['designs'] as $designData) {
                // Empty string comes from old records where design_id was null
                $designId = ($designData['design_id'] ?? null) ?: null;
                foreach ($designData['items'] as $item) {
                    if ((int) ($item['qty'] ?? 0) > 0) {
                        $return->items()->create([
                            'design_id' => $designId,
                            'size'      => $item['size'],
                            'quantity'  => (int) $item['qty'],
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Tarpai return logged.');
    }

    public function destroy(TarpaiSend $tarpaiSend)
    {
        $this->denyCreativeHead();
        $tarpaiSend->load(['items.design', 'returns.items', 'catalogue']);

        DB::transaction(function () use ($tarpaiSend) {
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'tarpai_send_id'  => $tarpaiSend->id,
                    'catalogue'       => $tarpaiSend->catalogue?->name,
                    'tarpai_house'    => $tarpaiSend->tarpai_house,
                    'sent_date'       => $tarpaiSend->sent_date?->format('Y-m-d'),
                    'per_piece_price' => $tarpaiSend->per_piece_price,
                    'total_pieces'    => $tarpaiSend->items->sum('quantity'),
                    'return_batches'  => $tarpaiSend->returns->count(),
                    'items'           => $tarpaiSend->items->map(fn($i) => [
                        'design'   => $i->design?->name,
                        'size'     => $i->size,
                        'quantity' => $i->quantity,
                    ])->toArray(),
                ])
                ->log("Tarpai send deleted: TP-{$tarpaiSend->id}");

            $tarpaiSend->delete();
        });

        return redirect()->route('tarpai-sends.index')
            ->with('success', 'Tarpai send TP-' . str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) . ' and all its returns have been deleted.');
    }

    public function destroyReturn(TarpaiSend $send, TarpaiReturn $return)
    {
        $this->denyCreativeHead();
        if ($return->tarpai_send_id !== $send->id) {
            abort(403);
        }

        $return->load('items');

        DB::transaction(function () use ($send, $return) {
            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'tarpai_send_id' => $send->id,
                    'return_date'    => $return->return_date?->format('Y-m-d'),
                    'pieces'         => $return->items->sum('quantity'),
                    'items'          => $return->items->map(fn($i) => [
                        'design_id' => $i->design_id,
                        'size'      => $i->size,
                        'quantity'  => $i->quantity,
                    ])->toArray(),
                ])
                ->log("Tarpai return deleted: RTN for TP-{$send->id} (return_id={$return->id})");

            $return->delete();
        });

        return back()->with('success', 'Return entry deleted.');
    }

    public function gatePass(TarpaiSend $tarpaiSend)
    {
        $tarpaiSend->load(['catalogue', 'items.design', 'loggedBy']);
        $designGroups = $tarpaiSend->items->groupBy('design_id');
        $designsById  = $tarpaiSend->items->pluck('design')->filter()->unique('id')->keyBy('id');
        return view('production.tarpai.gate-pass', compact('tarpaiSend', 'designGroups', 'designsById'));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function computeAvailableQty(Collection $catalogues): array
    {
        $available = [];
        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        foreach ($catalogues as $cat) {
            $available[$cat->id] = [];
            foreach ($cat->designs as $design) {
                $kameez = $this->getStitchingKameezBySize($cat->id, $design->id);
                $sent   = $this->getAlreadySentBySize($cat->id, $design->id);

                $perSize = [];
                foreach ($sizes as $size) {
                    $perSize[$size] = max(0, ($kameez[$size] ?? 0) - ($sent[$size] ?? 0));
                }
                $available[$cat->id][$design->id] = $perSize;
            }
        }

        return $available;
    }

    private function getStitchingKameezBySize(int $catalogueId, int $designId): array
    {
        return StitchingReturnItem::whereHas(
            'stitchingReturn',
            fn($q) => $q->where('catalogue_id', $catalogueId)->where('design_id', $designId)
        )
        ->where('component', 'kameez')
        ->selectRaw('size, SUM(quantity) as total')
        ->groupBy('size')
        ->pluck('total', 'size')
        ->map(fn($v) => (int) $v)
        ->toArray();
    }

    private function getAlreadySentBySize(int $catalogueId, int $designId): array
    {
        return TarpaiSendItem::whereHas(
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
