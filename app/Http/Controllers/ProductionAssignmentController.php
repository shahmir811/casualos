<?php

namespace App\Http\Controllers;

use App\Models\ProductionAssignment;
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
        // All open catalogues for the filter dropdown
        $openCatalogues = Catalogue::where('status', 'open')->orderBy('name')->get();

        // Default catalogue_id to the first open catalogue
        $defaultCatalogueId = $openCatalogues->first()?->id;
        $selectedCatalogueId = $request->get('catalogue_id', $defaultCatalogueId);
        $selectedDestination = $request->get('destination', '');
        $selectedUnit        = $request->get('stitching_unit', '');

        $query = ProductionAssignment::with(['catalogue', 'design', 'items'])->latest();

        if ($selectedCatalogueId) {
            $query->where('catalogue_id', $selectedCatalogueId);
        }
        if ($selectedDestination) {
            $query->where('destination', $selectedDestination);
        }
        if ($selectedUnit && $selectedUnit !== '') {
            $query->where('stitching_unit', $selectedUnit);
        }

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

        // Fabric received per (catalogue, design)
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

        // Already assigned per (catalogue, design)
        $assignedRows = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->select(
                'production_assignments.catalogue_id',
                'production_assignments.design_id',
                DB::raw('SUM(production_assignment_items.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignments.design_id')
            ->get()
            ->groupBy('catalogue_id')
            ->map(fn($rows) => $rows->pluck('qty', 'design_id'));

        // Attach available_qty to each design in each catalogue
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
        $validated = $request->validate([
            'catalogue_id'      => 'required|exists:catalogues,id',
            'design_id'         => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'destination'       => 'required|in:naeem_pakki,stitching_unit',
            'stitching_unit'    => 'required_if:destination,stitching_unit|nullable|integer|in:1,2,3,4',
            'naeem_pakki_rate'  => 'required_if:destination,naeem_pakki|nullable|numeric|min:0',
            'assignment_date'   => 'required|date',
            'items'             => 'required|array',
            'items.*.size'      => 'required|in:xs,s,m,l,xl',
            'items.*.qty'       => 'required|integer|min:0',
        ]);

        // ── Factory-availability constraint ─────────────────────────────
        $totalItemsQty = collect($validated['items'])->sum(fn($i) => (int) $i['qty']);

        if ($totalItemsQty === 0) {
            return back()->withInput()->withErrors(['items' => 'Please enter at least one piece quantity to create an assignment.']);
        }

        $fabricReceived = (int) DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $validated['catalogue_id'])
            ->where('fabric_batch_items.design_id', $validated['design_id'])
            ->sum('fabric_batch_items.quantity');

        $alreadyAssigned = (int) DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $validated['catalogue_id'])
            ->where('production_assignments.design_id', $validated['design_id'])
            ->sum('production_assignment_items.quantity');

        $availableInFactory = max(0, $fabricReceived - $alreadyAssigned);

        if ($totalItemsQty > $availableInFactory) {
            return back()->withInput()->withErrors([
                'items' => "Cannot assign {$totalItemsQty} pieces — only {$availableInFactory} are available in factory for this design (received: {$fabricReceived}, already assigned: {$alreadyAssigned}).",
            ]);
        }
        // ────────────────────────────────────────────────────────────────

        $assignment = ProductionAssignment::create([
            'catalogue_id'     => $validated['catalogue_id'],
            'design_id'        => $validated['design_id'],
            'destination'      => $validated['destination'],
            'stitching_unit'   => $validated['destination'] === 'stitching_unit' ? $validated['stitching_unit'] : null,
            'naeem_pakki_rate' => $validated['destination'] === 'naeem_pakki' ? $validated['naeem_pakki_rate'] : null,
            'assignment_date'  => $validated['assignment_date'],
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
            ->with('success', 'Production assignment created.');
    }

    public function show(ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load(['catalogue', 'design', 'items', 'loggedBy']);
        return view('production.assignments.show', compact('productionAssignment'));
    }
}
