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
        $catalogueId = (int) session('active_catalogue_id');

        if (!$catalogueId) {
            return redirect()->route('production-assignments.index')
                ->with('error', 'Please select a catalogue from the sidebar before creating an assignment.');
        }

        $catalogue = Catalogue::with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->findOrFail($catalogueId);

        // ── Fabric received per design ───────────────────────────────────
        $receivedRows = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $catalogueId)
            ->select('fabric_batch_items.design_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batch_items.design_id')
            ->get()
            ->pluck('qty', 'design_id');

        // ── Pieces assigned to stitching units only ──────────────────────
        $stitchingAssignedRows = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catalogueId)
            ->whereNotNull('production_assignments.design_id')
            ->where('production_assignments.destination', 'stitching_unit')
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->get()
            ->pluck('qty', 'design_id');

        // ── Total NP assigned per design ─────────────────────────────────
        $npAssignedOld = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catalogueId)
            ->whereNotNull('production_assignments.design_id')
            ->where('production_assignments.destination', 'naeem_pakki')
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->get();

        $npAssignedNew = DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catalogueId)
            ->select('production_assignment_np_designs.design_id', DB::raw('SUM(production_assignment_np_designs.quantity) as qty'))
            ->groupBy('production_assignment_np_designs.design_id')
            ->get();

        $npAssignedByDesign = $npAssignedOld->concat($npAssignedNew)
            ->groupBy('design_id')
            ->map(fn($r) => $r->sum('qty'));

        // ── NP returned per design ───────────────────────────────────────
        $npReturnedRows = DB::table('naeem_pakki_return_items')
            ->join('production_assignment_np_designs', 'production_assignment_np_designs.id', '=', 'naeem_pakki_return_items.np_design_id')
            ->join('naeem_pakki_returns', 'naeem_pakki_returns.id', '=', 'naeem_pakki_return_items.naeem_pakki_return_id')
            ->join('production_assignments', 'production_assignments.id', '=', 'naeem_pakki_returns.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catalogueId)
            ->select('production_assignment_np_designs.design_id', DB::raw('SUM(naeem_pakki_return_items.quantity) as qty'))
            ->groupBy('production_assignment_np_designs.design_id')
            ->get()
            ->pluck('qty', 'design_id');

        // ── Attach available_qty and np_available_qty to each design ─────
        $catalogue->designs->each(function ($design) use ($receivedRows, $stitchingAssignedRows, $npReturnedRows, $npAssignedByDesign) {
            $stitchingAssigned = (int) ($stitchingAssignedRows[$design->id] ?? 0);
            $received          = (int) ($receivedRows[$design->id] ?? 0);
            if ($design->needs_naeem_pakki) {
                $npReturned = (int) ($npReturnedRows[$design->id] ?? 0);
                $npAssigned = (int) ($npAssignedByDesign[$design->id] ?? 0);
                $design->available_qty    = max(0, $npReturned - $stitchingAssigned);
                $design->np_available_qty = max(0, $received - $npAssigned);
            } else {
                $design->available_qty    = max(0, $received - $stitchingAssigned);
                $design->np_available_qty = 0;
            }
        });

        $stitchingUnits = StitchingUnit::where('is_active', true)->orderBy('number')->get();

        return view('production.assignments.create', compact('catalogue', 'stitchingUnits'));
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

    public function updateNpRate(Request $request, ProductionAssignment $productionAssignment, ProductionAssignmentNpDesign $npDesign)
    {
        abort_if($npDesign->production_assignment_id !== $productionAssignment->id, 404);

        $validated = $request->validate([
            'per_piece_price' => 'required|numeric|min:0',
        ]);

        $npDesign->update(['per_piece_price' => $validated['per_piece_price']]);

        return back()->with('success', 'Rate updated for ' . ($npDesign->design->name ?? 'design') . '.');
    }
}
