<?php

namespace App\Http\Controllers;

use App\Models\ProductionAssignment;
use App\Models\ProductionAssignmentNpDesign;
use App\Models\Design;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductionAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $openCatalogues      = Catalogue::where('status', 'open')->orderBy('name')->get();
        $defaultCatalogueId  = $openCatalogues->first()?->id;
        $selectedCatalogueId = $request->get('catalogue_id', $defaultCatalogueId);
        $selectedDestination = $request->get('destination', '');
        $selectedUnit        = $request->get('stitching_unit', '');

        $query = ProductionAssignment::with(['catalogue', 'design', 'items', 'npDesigns.design'])->latest();

        if ($selectedCatalogueId) $query->where('catalogue_id', $selectedCatalogueId);
        if ($selectedDestination) $query->where('destination', $selectedDestination);
        if ($selectedUnit)        $query->where('stitching_unit', $selectedUnit);

        $assignments = $query->paginate(20)->withQueryString();

        return view('production.assignments.index', compact(
            'assignments', 'openCatalogues',
            'selectedCatalogueId', 'selectedDestination', 'selectedUnit'
        ));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
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

        // ── Already assigned (stitching items + new-style NP np_designs) ─
        // Old-style stitching/NP: design_id on parent assignment, qty in items table
        $itemsAssigned = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->whereNotNull('production_assignments.design_id')
            ->select(
                'production_assignments.catalogue_id',
                'production_assignments.design_id',
                DB::raw('SUM(production_assignment_items.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignments.design_id')
            ->get();

        // New-style NP: design_id on np_designs, qty in np_designs table
        $npAssigned = DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->select(
                'production_assignments.catalogue_id',
                'production_assignment_np_designs.design_id',
                DB::raw('SUM(production_assignment_np_designs.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignment_np_designs.design_id')
            ->get();

        // Merge: group by catalogue_id → design_id → total assigned
        $assignedRows = $itemsAssigned->concat($npAssigned)
            ->groupBy('catalogue_id')
            ->map(fn($catRows) =>
                $catRows->groupBy('design_id')->map(fn($r) => $r->sum('qty'))
            );

        // ── Attach available_qty to each design ──────────────────────────
        $catalogues->each(function ($cat) use ($receivedRows, $assignedRows) {
            $catReceived = $receivedRows[$cat->id] ?? collect();
            $catAssigned = $assignedRows[$cat->id] ?? collect();
            $cat->designs->each(function ($design) use ($catReceived, $catAssigned) {
                $received = (int) ($catReceived[$design->id] ?? 0);
                $assigned = (int) ($catAssigned[$design->id] ?? 0);
                $design->available_qty = max(0, $received - $assigned);
            });
        });

        return view('production.assignments.create', compact('catalogues'));
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
            'np_items.*.quantity'        => 'required|integer|min:0',
            'np_items.*.per_piece_price' => 'required|numeric|min:0',
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
        foreach ($items as $designId => $item) {
            $qty = (int) $item['quantity'];

            $fabricReceived = (int) DB::table('fabric_batch_items')
                ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
                ->where('fabric_batches.catalogue_id', $catalogueId)
                ->where('fabric_batch_items.design_id', $designId)
                ->sum('fabric_batch_items.quantity');

            // Old-style (design_id on parent assignment)
            $oldAssigned = (int) DB::table('production_assignment_items')
                ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
                ->where('production_assignments.catalogue_id', $catalogueId)
                ->where('production_assignments.design_id', $designId)
                ->sum('production_assignment_items.quantity');

            // New-style (design_id on np_designs row)
            $newAssigned = (int) DB::table('production_assignment_np_designs')
                ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
                ->where('production_assignments.catalogue_id', $catalogueId)
                ->where('production_assignment_np_designs.design_id', $designId)
                ->sum('production_assignment_np_designs.quantity');

            $available = max(0, $fabricReceived - $oldAssigned - $newAssigned);

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
            'catalogue_id'    => $catalogueId,
            'design_id'       => null,          // NP batches: no single design on parent
            'destination'     => 'naeem_pakki',
            'stitching_unit'  => null,
            'naeem_pakki_rate'=> null,           // rate stored per-design in np_designs
            'assignment_date' => $request->assignment_date,
            'logged_by'       => Auth::id(),
        ]);

        // Create one np_designs row per selected design
        foreach ($items as $designId => $item) {
            $assignment->npDesigns()->create([
                'design_id'       => $designId,
                'quantity'        => (int) $item['quantity'],
                'per_piece_price' => (float) $item['per_piece_price'],
            ]);
        }

        return redirect()->route('production-assignments.show', $assignment)
            ->with('success', $items->count() . ' design(s) assigned to Naeem Pakki.');
    }

    // ── Stitching Unit: single design, per-size quantities ───────────────
    private function storeStitchingUnit(Request $request)
    {
        $validated = $request->validate([
            'design_id'      => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'stitching_unit' => 'required|integer|in:1,2,3,4',
            'items'          => 'required|array',
            'items.*.size'   => 'required|in:xs,s,m,l,xl',
            'items.*.qty'    => 'required|integer|min:0',
        ]);

        $totalItemsQty = collect($validated['items'])->sum(fn($i) => (int) $i['qty']);

        if ($totalItemsQty === 0) {
            return back()->withInput()->withErrors(['items' => 'Please enter at least one piece quantity to create an assignment.']);
        }

        // ── Availability check ───────────────────────────────────────────
        $fabricReceived = (int) DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $request->catalogue_id)
            ->where('fabric_batch_items.design_id', $validated['design_id'])
            ->sum('fabric_batch_items.quantity');

        $oldAssigned = (int) DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $request->catalogue_id)
            ->where('production_assignments.design_id', $validated['design_id'])
            ->sum('production_assignment_items.quantity');

        $newNpAssigned = (int) DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->where('production_assignments.catalogue_id', $request->catalogue_id)
            ->where('production_assignment_np_designs.design_id', $validated['design_id'])
            ->sum('production_assignment_np_designs.quantity');

        $availableInFactory = max(0, $fabricReceived - $oldAssigned - $newNpAssigned);

        if ($totalItemsQty > $availableInFactory) {
            return back()->withInput()->withErrors([
                'items' => "Cannot assign {$totalItemsQty} pieces — only {$availableInFactory} available in factory for this design.",
            ]);
        }
        // ────────────────────────────────────────────────────────────────

        $assignment = ProductionAssignment::create([
            'catalogue_id'    => $request->catalogue_id,
            'design_id'       => $validated['design_id'],
            'destination'     => 'stitching_unit',
            'stitching_unit'  => $validated['stitching_unit'],
            'naeem_pakki_rate'=> null,
            'assignment_date' => $request->assignment_date,
            'logged_by'       => Auth::id(),
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
        $productionAssignment->load(['catalogue', 'design', 'items', 'npDesigns.design', 'loggedBy']);
        return view('production.assignments.show', compact('productionAssignment'));
    }
}
