<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Design;
use App\Models\FabricBatch;
use App\Models\ProductionAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FabricBatchController extends Controller
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

        // ── Batches table ────────────────────────────────────────────────
        $query = FabricBatch::with(['catalogue', 'items.design', 'loggedBy'])->latest();

        if ($selectedCatalogueId) $query->where('catalogue_id', $selectedCatalogueId);
        if ($selectedDesignId)    $query->whereHas('items', fn($q) => $q->where('design_id', $selectedDesignId));

        $batches = $query->paginate(20)->withQueryString();

        $catalogueIds = $selectedCatalogueId ? [$selectedCatalogueId] : [];

        // ── Summary card totals (respect design filter) ──────────────────
        $receivedPerCatalogue = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->whereIn('fabric_batches.catalogue_id', $catalogueIds)
            ->when($selectedDesignId, fn($q) => $q->where('fabric_batch_items.design_id', $selectedDesignId))
            ->select('fabric_batches.catalogue_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batches.catalogue_id')
            ->pluck('qty', 'catalogue_id');

        $receivedPerDesignByCatalogue = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->join('designs', 'designs.id', '=', 'fabric_batch_items.design_id')
            ->whereIn('fabric_batches.catalogue_id', $catalogueIds)
            ->when($selectedDesignId, fn($q) => $q->where('fabric_batch_items.design_id', $selectedDesignId))
            ->select(
                'fabric_batches.catalogue_id',
                'fabric_batch_items.design_id',
                'designs.name as design_name',
                'designs.needs_naeem_pakki',
                DB::raw('SUM(fabric_batch_items.quantity) as qty')
            )
            ->groupBy('fabric_batches.catalogue_id', 'fabric_batch_items.design_id', 'designs.name', 'designs.needs_naeem_pakki', 'designs.sort_order')
            ->orderBy('designs.sort_order')
            ->get()
            ->groupBy('catalogue_id');

        return view('production.fabric-batches.index', compact(
            'batches', 'receivedPerCatalogue', 'receivedPerDesignByCatalogue',
            'catalogueDesigns', 'selectedCatalogueId', 'selectedDesignId'
        ));
    }

    public function create()
    {
        $this->denyCreativeHead();
        $catalogueId = (int) session('active_catalogue_id');

        if (!$catalogueId) {
            return redirect()->route('fabric-batches.index')
                ->with('error', 'Please select a catalogue from the sidebar before logging a fabric batch.');
        }

        $catalogue = Catalogue::with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->findOrFail($catalogueId);

        return view('production.fabric-batches.create', compact('catalogue'));
    }

    public function store(Request $request)
    {
        $this->denyCreativeHead();
        $validated = $request->validate([
            'catalogue_id' => 'required|exists:catalogues,id',
            'arrival_date' => 'required|date',
            'notes'        => 'nullable|string',
            'items'        => 'required|array',
            'items.*.design_id' => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'items.*.quantity'  => 'nullable|integer|min:0',
        ]);

        // Only keep designs where fabric was actually received (qty > 0)
        $itemsToSave = array_filter($validated['items'], fn($i) => (int)$i['quantity'] > 0);

        if (empty($itemsToSave)) {
            return back()->withInput()->withErrors(['items' => 'At least one design must have a quantity greater than 0.']);
        }

        $batch = FabricBatch::create([
            'catalogue_id' => $validated['catalogue_id'],
            'arrival_date' => $validated['arrival_date'],
            'notes'        => $validated['notes'] ?? null,
            'logged_by'    => Auth::id(),
        ]);

        foreach ($itemsToSave as $item) {
            $batch->items()->create([
                'design_id' => $item['design_id'],
                'quantity'  => $item['quantity'],
            ]);
        }

        // Auto-transition confirmed orders → stitching; skip partially_dispatched (already in dispatch flow)
        \App\Models\Order::where('catalogue_id', $validated['catalogue_id'])   // Order not imported — using FQCN intentionally
            ->where('status', 'confirmed')
            ->update(['status' => 'stitching']);

        return redirect()->route('fabric-batches.show', $batch)
            ->with('success', 'Fabric batch arrival recorded.');
    }

    public function show(FabricBatch $fabricBatch)
    {
        $fabricBatch->load(['catalogue', 'loggedBy']);

        // Only show in-house design items — outsourced designs are not part of fabric batch tracking
        $fabricBatch->setRelation('items',
            $fabricBatch->items()
                ->with('design')
                ->whereHas('design', fn($q) => $q->where('manufacturing_type', 'in_house'))
                ->get()
        );

        $catalogue = $fabricBatch->catalogue;
        $catId     = $catalogue->id;

        // ── Expected total ──────────────────────────────────────────────
        $inHouseDesigns   = $catalogue->designs()->where('manufacturing_type', 'in_house')->get();
        $inHouseCount     = $inHouseDesigns->count();
        $expectedTotal    = (int) $catalogue->qty_per_design * $inHouseCount;

        // ── Total received across ALL batches for this catalogue ────────
        $totalReceivedAllBatches = (int) DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $catId)
            ->sum('fabric_batch_items.quantity');

        // ── Total assigned (stitching items + new-style NP np_designs) ──
        $stitchingTotalAssigned = (int) DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->sum('production_assignment_items.quantity');

        $npTotalAssigned = (int) DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->sum('production_assignment_np_designs.quantity');

        $totalAssigned      = $stitchingTotalAssigned + $npTotalAssigned;
        $availableInFactory = max(0, $totalReceivedAllBatches - $totalAssigned);

        // ── Per-design received ──────────────────────────────────────────
        $receivedPerDesign = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $catId)
            ->select('fabric_batch_items.design_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batch_items.design_id')
            ->pluck('qty', 'design_id');

        // ── Per-design split: Naeem Pakki vs Stitching ──────────────────
        // Old-style NP: design_id on assignment, qty in items table, size='np'
        $npOldPerDesign = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->where('production_assignments.destination', 'naeem_pakki')
            ->whereNotNull('production_assignments.design_id')
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        // New-style NP: design_id on np_designs, qty in np_designs table
        $npNewPerDesign = DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->select('production_assignment_np_designs.design_id', DB::raw('SUM(production_assignment_np_designs.quantity) as qty'))
            ->groupBy('production_assignment_np_designs.design_id')
            ->pluck('qty', 'design_id');

        // Merge old + new NP per design
        $npAssignedPerDesign = $npOldPerDesign->map(fn($q) => (int) $q)
            ->mergeRecursive($npNewPerDesign->map(fn($q) => (int) $q))
            ->map(fn($v) => is_array($v) ? array_sum($v) : $v);

        $stitchingAssignedPerDesign = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->where('production_assignments.destination', 'stitching_unit')
            ->whereNotNull('production_assignments.design_id')
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        // Combined per-design for the fabric table available column
        $assignedPerDesign = $npAssignedPerDesign
            ->mergeRecursive($stitchingAssignedPerDesign->map(fn($q) => (int) $q))
            ->map(fn($v) => is_array($v) ? array_sum($v) : $v);

        $totalToNaeemPakki = (int) $npAssignedPerDesign->sum();
        $totalToStitching  = (int) $stitchingAssignedPerDesign->sum();

        // ── Naeem Pakki tracking ─────────────────────────────────────────
        $npAssignments = ProductionAssignment::with(['design', 'items', 'npDesigns.design', 'npDesigns.returnItems'])
            ->where('catalogue_id', $catId)
            ->where('destination', 'naeem_pakki')
            ->get();

        $naeemPakkiAssignments = $npAssignments->flatMap(function ($assignment) {
            if ($assignment->npDesigns->isNotEmpty()) {
                return $assignment->npDesigns->map(function ($npDesign) {
                    $assignedQty = (int) $npDesign->quantity;
                    $returnedQty = $npDesign->totalReturned();
                    return [
                        'design'       => $npDesign->design->name ?? '—',
                        'rate'         => $npDesign->per_piece_price,
                        'assigned_qty' => $assignedQty,
                        'returned_qty' => $returnedQty,
                        'pending_qty'  => max(0, $assignedQty - $returnedQty),
                    ];
                });
            }
            // Old-style: design_id on assignment, qty in items table
            $assignedQty = (int) $assignment->items->sum('quantity');
            return [[
                'design'       => $assignment->design->name ?? '—',
                'rate'         => $assignment->naeem_pakki_rate,
                'assigned_qty' => $assignedQty,
                'returned_qty' => 0,
                'pending_qty'  => $assignedQty,
            ]];
        });

        return view('production.fabric-batches.show', compact(
            'fabricBatch', 'naeemPakkiAssignments',
            'expectedTotal', 'inHouseCount', 'totalReceivedAllBatches',
            'totalAssigned', 'availableInFactory',
            'receivedPerDesign', 'assignedPerDesign',
            'npAssignedPerDesign', 'stitchingAssignedPerDesign',
            'totalToNaeemPakki', 'totalToStitching'
        ));
    }
}
