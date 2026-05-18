<?php

namespace App\Http\Controllers;

use App\Models\ProductionAssignment;
use App\Models\ProductionAssignmentNpDesign;
use App\Models\Design;
use App\Models\Catalogue;
use App\Models\StitchingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: '';
        $selectedDestination = $request->get('destination', '');
        $selectedUnit        = $request->get('stitching_unit_id', '');

        $query = ProductionAssignment::with(['catalogue', 'design', 'items', 'npDesigns.design', 'stitchingUnit'])->latest();

        if ($selectedCatalogueId) $query->where('catalogue_id', $selectedCatalogueId);
        if ($selectedDestination) $query->where('destination', $selectedDestination);
        if ($selectedUnit)        $query->where('stitching_unit_id', $selectedUnit);

        $assignments = $query->paginate(20)->withQueryString();

        $stitchingUnits = StitchingUnit::orderBy('number')->get();

        return view('production.assignments.index', compact(
            'assignments', 'stitchingUnits',
            'selectedCatalogueId', 'selectedDestination', 'selectedUnit'
        ));
    }

    public function create()
    {
        $catalogues = Catalogue::with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->orderBy('name')
            ->get();

        $catIds = $catalogues->pluck('id')->toArray();

        // ── Fabric received per (catalogue, design) ──────────────────────
        $receivedRows = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->whereIn('fabric_batches.catalogue_id', $catIds)
            ->select(
                'fabric_batches.catalogue_id',
                'fabric_batch_items.design_id',
                DB::raw('SUM(fabric_batch_items.quantity) as qty')
            )
            ->groupBy('fabric_batches.catalogue_id', 'fabric_batch_items.design_id')
            ->get()
            ->groupBy('catalogue_id')
            ->map(fn($rows) => $rows->pluck('qty', 'design_id'));

        // ── Pieces assigned to stitching units only (destination='stitching_unit') ─
        $stitchingAssignedRows = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->whereNotNull('production_assignments.design_id')
            ->where('production_assignments.destination', 'stitching_unit')
            ->select(
                'production_assignments.catalogue_id',
                'production_assignments.design_id',
                DB::raw('SUM(production_assignment_items.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignments.design_id')
            ->get()
            ->groupBy('catalogue_id')
            ->map(fn($rows) => $rows->pluck('qty', 'design_id'));

        // ── Total NP assigned per (catalogue, design) — for NP form "available" ─
        $npAssignedOld = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->whereNotNull('production_assignments.design_id')
            ->where('production_assignments.destination', 'naeem_pakki')
            ->select(
                'production_assignments.catalogue_id',
                'production_assignments.design_id',
                DB::raw('SUM(production_assignment_items.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignments.design_id')
            ->get();

        $npAssignedNew = DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->select(
                'production_assignments.catalogue_id',
                'production_assignment_np_designs.design_id',
                DB::raw('SUM(production_assignment_np_designs.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignment_np_designs.design_id')
            ->get();

        $npAssignedRows = $npAssignedOld->concat($npAssignedNew)
            ->groupBy('catalogue_id')
            ->map(fn($catRows) =>
                $catRows->groupBy('design_id')->map(fn($r) => $r->sum('qty'))
            );

        // ── NP returned per (catalogue, design) ──────────────────────────
        // NP designs: source for stitching is what came back from NP, not raw fabric
        $npReturnedRows = DB::table('naeem_pakki_return_items')
            ->join('production_assignment_np_designs', 'production_assignment_np_designs.id', '=', 'naeem_pakki_return_items.np_design_id')
            ->join('naeem_pakki_returns', 'naeem_pakki_returns.id', '=', 'naeem_pakki_return_items.naeem_pakki_return_id')
            ->join('production_assignments', 'production_assignments.id', '=', 'naeem_pakki_returns.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->select(
                'production_assignments.catalogue_id',
                'production_assignment_np_designs.design_id',
                DB::raw('SUM(naeem_pakki_return_items.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignment_np_designs.design_id')
            ->get()
            ->groupBy('catalogue_id')
            ->map(fn($rows) => $rows->pluck('qty', 'design_id'));

        // ── Attach available_qty and np_available_qty to each design ─────
        // available_qty    → used by stitching unit form
        //   NP designs:     npReturned - stitchingAssigned
        //   Non-NP designs: fabricReceived - stitchingAssigned
        // np_available_qty → used by NP assignment form
        //   NP designs:     fabricReceived - totalNpAssigned (pieces not yet sent to NP)
        $catalogues->each(function ($cat) use ($receivedRows, $stitchingAssignedRows, $npReturnedRows, $npAssignedRows) {
            $catReceived   = $receivedRows[$cat->id] ?? collect();
            $catStitching  = $stitchingAssignedRows[$cat->id] ?? collect();
            $catNpReturned = $npReturnedRows[$cat->id] ?? collect();
            $catNpAssigned = $npAssignedRows[$cat->id] ?? collect();
            $cat->designs->each(function ($design) use ($catReceived, $catStitching, $catNpReturned, $catNpAssigned) {
                $stitchingAssigned = (int) ($catStitching[$design->id] ?? 0);
                $received          = (int) ($catReceived[$design->id] ?? 0);
                if ($design->needs_naeem_pakki) {
                    $npReturned = (int) ($catNpReturned[$design->id] ?? 0);
                    $npAssigned = (int) ($catNpAssigned[$design->id] ?? 0);
                    $design->available_qty    = max(0, $npReturned - $stitchingAssigned);
                    $design->np_available_qty = max(0, $received - $npAssigned);
                } else {
                    $design->available_qty    = max(0, $received - $stitchingAssigned);
                    $design->np_available_qty = 0;
                }
            });
        });

        $stitchingUnits = StitchingUnit::where('is_active', true)->orderBy('number')->get();

        return view('production.assignments.create', compact('catalogues', 'stitchingUnits'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'catalogue_id'    => 'required|exists:catalogues,id',
            'destination'     => 'required|in:naeem_pakki,stitching_unit',
            'assignment_date' => 'required|date',
        ]);

        return $request->destination === 'naeem_pakki'
            ? $this->storeNaeemPakki($request)
            : $this->storeStitchingUnit($request);
    }

    // ── Naeem Pakki: ONE assignment, multiple designs in np_designs ──────
    private function storeNaeemPakki(Request $request)
    {
        $request->validate([
            'np_items'                   => 'required|array',
            'np_items.*.design_id'       => 'required|exists:designs,id',
            'np_items.*.quantity'        => 'nullable|integer|min:0',
            'np_items.*.per_piece_price' => 'nullable|numeric|min:0',
        ]);

        $items = collect($request->np_items)
            ->filter(fn($item) => ((int) ($item['quantity'] ?? 0)) > 0);

        if ($items->isEmpty()) {
            return back()->withInput()->withErrors([
                'np_items' => 'Enter a quantity for at least one design.',
            ]);
        }

        $catalogueId = $request->catalogue_id;

        // ── Availability check per design (before creating anything) ─────
        foreach ($items as $item) {
            $designId = (int) $item['design_id'];
            $qty = (int) $item['quantity'];

            $fabricReceived = (int) DB::table('fabric_batch_items')
                ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
                ->where('fabric_batches.catalogue_id', $catalogueId)
                ->where('fabric_batch_items.design_id', $designId)
                ->sum('fabric_batch_items.quantity');

            $npAssigned = (int) DB::table('production_assignment_np_designs')
                ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
                ->where('production_assignments.catalogue_id', $catalogueId)
                ->where('production_assignment_np_designs.design_id', $designId)
                ->sum('production_assignment_np_designs.quantity');

            $available = max(0, $fabricReceived - $npAssigned);

            if ($qty > $available) {
                $designName = Design::find($designId)?->name ?? "Design #{$designId}";
                return back()->withInput()->withErrors([
                    'np_items' => "Cannot assign {$qty} pcs for '{$designName}' — only {$available} available in factory.",
                ]);
            }
        }
        // ────────────────────────────────────────────────────────────────

        // Create ONE parent assignment for this entire NP batch
        $assignment = ProductionAssignment::create([
            'catalogue_id'     => $catalogueId,
            'design_id'        => null,
            'destination'      => 'naeem_pakki',
            'stitching_unit_id' => null,
            'naeem_pakki_rate' => null,
            'assignment_date'  => $request->assignment_date,
            'logged_by'        => Auth::id(),
        ]);

        // Create one np_designs row per selected design
        foreach ($items as $item) {
            $assignment->npDesigns()->create([
                'design_id'       => (int) $item['design_id'],
                'quantity'        => (int) $item['quantity'],
                'per_piece_price' => (float) ($item['per_piece_price'] ?? 0),
            ]);
        }

        return redirect()->route('production-assignments.show', $assignment)
            ->with('success', $items->count() . ' design(s) assigned to Naeem Pakki.');
    }

    // ── Stitching Unit: single design, per-size quantities ───────────────
    private function storeStitchingUnit(Request $request)
    {
        $activeUnitIds = StitchingUnit::where('is_active', true)->pluck('id')->toArray();

        $validated = $request->validate([
            'design_id'        => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'stitching_unit_id' => ['required', Rule::in($activeUnitIds)],
            'items'            => 'required|array',
            'items.*.size'     => 'required|in:xs,s,m,l,xl',
            'items.*.qty'      => 'nullable|integer|min:0',
        ]);

        $totalItemsQty = collect($validated['items'])->sum(fn($i) => (int) $i['qty']);

        if ($totalItemsQty === 0) {
            return back()->withInput()->withErrors(['items' => 'Please enter at least one piece quantity to create an assignment.']);
        }

        // ── Availability check ───────────────────────────────────────────
        $design = Design::findOrFail($validated['design_id']);

        // Pieces already assigned to stitching units for this design
        $stitchingAssigned = (int) DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $request->catalogue_id)
            ->where('production_assignments.design_id', $validated['design_id'])
            ->where('production_assignments.destination', 'stitching_unit')
            ->sum('production_assignment_items.quantity');

        if ($design->needs_naeem_pakki) {
            // Source is NP returns — raw fabric is not directly available for stitching
            $npReturned = (int) DB::table('naeem_pakki_return_items')
                ->join('production_assignment_np_designs', 'production_assignment_np_designs.id', '=', 'naeem_pakki_return_items.np_design_id')
                ->join('naeem_pakki_returns', 'naeem_pakki_returns.id', '=', 'naeem_pakki_return_items.naeem_pakki_return_id')
                ->join('production_assignments', 'production_assignments.id', '=', 'naeem_pakki_returns.production_assignment_id')
                ->where('production_assignments.catalogue_id', $request->catalogue_id)
                ->where('production_assignment_np_designs.design_id', $validated['design_id'])
                ->sum('naeem_pakki_return_items.quantity');

            $availableInFactory = max(0, $npReturned - $stitchingAssigned);
        } else {
            // Source is raw fabric received in factory
            $fabricReceived = (int) DB::table('fabric_batch_items')
                ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
                ->where('fabric_batches.catalogue_id', $request->catalogue_id)
                ->where('fabric_batch_items.design_id', $validated['design_id'])
                ->sum('fabric_batch_items.quantity');

            $availableInFactory = max(0, $fabricReceived - $stitchingAssigned);
        }

        if ($totalItemsQty > $availableInFactory) {
            return back()->withInput()->withErrors([
                'items' => "Cannot assign {$totalItemsQty} pieces — only {$availableInFactory} available in factory for this design.",
            ]);
        }
        // ────────────────────────────────────────────────────────────────

        $assignment = ProductionAssignment::create([
            'catalogue_id'     => $request->catalogue_id,
            'design_id'        => $validated['design_id'],
            'destination'      => 'stitching_unit',
            'stitching_unit_id' => $validated['stitching_unit_id'],
            'naeem_pakki_rate' => null,
            'assignment_date'  => $request->assignment_date,
            'logged_by'        => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['qty'] > 0) {
                $assignment->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $item['qty'],
                ]);
            }
        }

        return redirect()->route('production-assignments.show', $assignment)
            ->with('success', 'Stitching assignment created.');
    }

    public function show(ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load(['catalogue', 'design', 'items', 'stitchingUnit', 'npDesigns.design', 'npDesigns.returnItems', 'loggedBy']);
        return view('production.assignments.show', compact('productionAssignment'));
    }
}
