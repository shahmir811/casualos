<?php

namespace App\Http\Controllers;

use App\Models\FabricBatch;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FabricBatchController extends Controller
{
    public function index()
    {
        $batches = FabricBatch::with(['catalogue', 'items'])->latest()->paginate(20);

        $catalogueIds = $batches->pluck('catalogue_id')->unique()->filter()->values()->toArray();

        // Total received per catalogue (all batches, not just current page)
        $receivedPerCatalogue = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->whereIn('fabric_batches.catalogue_id', $catalogueIds)
            ->select('fabric_batches.catalogue_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batches.catalogue_id')
            ->pluck('qty', 'catalogue_id');

        // Per-design received quantities grouped by catalogue_id → [design_id => qty]
        $receivedPerDesignByCatalogue = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->join('designs', 'designs.id', '=', 'fabric_batch_items.design_id')
            ->whereIn('fabric_batches.catalogue_id', $catalogueIds)
            ->select(
                'fabric_batches.catalogue_id',
                'fabric_batch_items.design_id',
                'designs.name as design_name',
                DB::raw('SUM(fabric_batch_items.quantity) as qty')
            )
            ->groupBy('fabric_batches.catalogue_id', 'fabric_batch_items.design_id', 'designs.name')
            ->orderBy('designs.sort_order')
            ->get()
            ->groupBy('catalogue_id');

        return view('production.fabric-batches.index', compact(
            'batches', 'receivedPerCatalogue', 'receivedPerDesignByCatalogue'
        ));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->orderBy('name')
            ->get();
        return view('production.fabric-batches.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id' => 'required|exists:catalogues,id',
            'arrival_date' => 'required|date',
            'notes'        => 'nullable|string',
            'items'        => 'required|array',
            'items.*.design_id' => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'items.*.quantity'  => 'required|integer|min:0',
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

        // Auto-transition all confirmed orders for this catalogue → stitching
        \App\Models\Order::where('catalogue_id', $validated['catalogue_id'])
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

        // ── Total assigned to production for this catalogue ─────────────
        $totalAssigned = (int) DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->sum('production_assignment_items.quantity');

        $availableInFactory = max(0, $totalReceivedAllBatches - $totalAssigned);

        // ── Per-design received & assigned (for the items table) ────────
        $receivedPerDesign = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $catId)
            ->select('fabric_batch_items.design_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batch_items.design_id')
            ->pluck('qty', 'design_id');

        $assignedPerDesign = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        // ── Per-design split: how many went to Naeem Pakki vs Stitching ─
        $npAssignedPerDesign = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->where('production_assignments.destination', 'naeem_pakki')
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        $stitchingAssignedPerDesign = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->where('production_assignments.destination', 'stitching_unit')
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        $totalToNaeemPakki = (int) $npAssignedPerDesign->sum();
        $totalToStitching  = (int) $stitchingAssignedPerDesign->sum();

        // ── Naeem Pakki tracking ────────────────────────────────────────
        $naeemPakkiAssignments = \App\Models\ProductionAssignment::with([
                'design',
                'items',
                'naeemPakkiSend.items',
                'naeemPakkiSend.returns.items',
            ])
            ->where('catalogue_id', $catId)
            ->where('destination', 'naeem_pakki')
            ->get()
            ->map(function ($assignment) {
                $send        = $assignment->naeemPakkiSend;
                $sentQty     = $send?->items->sum('quantity') ?? 0;
                $returnedQty = $send?->returns->flatMap->items->sum('quantity') ?? 0;
                $pending     = max(0, $sentQty - $returnedQty);

                return [
                    'design'       => $assignment->design->name ?? '—',
                    'rate'         => $assignment->naeem_pakki_rate,
                    'assigned_qty' => $assignment->items->sum('quantity'),
                    'sent_qty'     => $sentQty,
                    'returned_qty' => $returnedQty,
                    'pending_qty'  => $pending,
                ];
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
