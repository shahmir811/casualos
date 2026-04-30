<?php

namespace App\Http\Controllers;

use App\Models\StitchingReturn;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StitchingReturnController extends Controller
{
    public function index()
    {
        $returns = StitchingReturn::with(['catalogue', 'design', 'items', 'loggedBy'])
            ->latest()
            ->paginate(20);

        // ── Per-unit summary: pieces returned + distinct designs per unit ──
        $unitSummary = DB::table('stitching_returns')
            ->join('stitching_return_items', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->whereNotNull('stitching_returns.stitching_unit')
            ->select(
                'stitching_returns.stitching_unit',
                DB::raw('SUM(stitching_return_items.quantity) as total_pieces'),
                DB::raw('COUNT(DISTINCT stitching_returns.design_id) as total_designs')
            )
            ->groupBy('stitching_returns.stitching_unit')
            ->get()
            ->keyBy('stitching_unit');

        // ── Per-unit assigned: pieces & designs assigned from production_assignments ──
        $unitAssigned = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.destination', 'stitching_unit')
            ->whereNotNull('production_assignments.stitching_unit')
            ->select(
                'production_assignments.stitching_unit',
                DB::raw('SUM(production_assignment_items.quantity) as total_assigned'),
                DB::raw('COUNT(DISTINCT production_assignments.design_id) as designs_assigned')
            )
            ->groupBy('production_assignments.stitching_unit')
            ->get()
            ->keyBy('stitching_unit');

        return view('production.stitching-returns.index', compact('returns', 'unitSummary', 'unitAssigned'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->orderBy('name')
            ->get();

        $catIds = $catalogues->pluck('id')->toArray();

        // Assigned stitching_unit per (catalogue, design)
        $unitByDesign = DB::table('production_assignments')
            ->whereIn('catalogue_id', $catIds)
            ->where('destination', 'stitching_unit')
            ->whereNotNull('stitching_unit')
            ->select('catalogue_id', 'design_id', 'stitching_unit')
            ->get()
            ->groupBy('catalogue_id')
            ->map(fn($rows) => $rows->pluck('stitching_unit', 'design_id'));

        // Total pieces assigned per (catalogue, design) — stitching_unit only
        $assignedQty = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
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

        // Total pieces already returned per (catalogue, design)
        $returnedQty = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->whereIn('stitching_returns.catalogue_id', $catIds)
            ->select(
                'stitching_returns.catalogue_id',
                'stitching_returns.design_id',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy('stitching_returns.catalogue_id', 'stitching_returns.design_id')
            ->get()
            ->groupBy('catalogue_id')
            ->map(fn($rows) => $rows->pluck('qty', 'design_id'));

        // ── Per-SIZE assigned per (catalogue, design) — stitching_unit only ──
        $assignedSizesRaw = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->whereIn('production_assignments.catalogue_id', $catIds)
            ->where('production_assignments.destination', 'stitching_unit')
            ->select(
                'production_assignments.catalogue_id',
                'production_assignments.design_id',
                'production_assignment_items.size',
                DB::raw('SUM(production_assignment_items.quantity) as qty')
            )
            ->groupBy('production_assignments.catalogue_id', 'production_assignments.design_id', 'production_assignment_items.size')
            ->get()
            ->groupBy('catalogue_id');

        // ── Per-SIZE already returned per (catalogue, design) ──
        $returnedSizesRaw = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->whereIn('stitching_returns.catalogue_id', $catIds)
            ->select(
                'stitching_returns.catalogue_id',
                'stitching_returns.design_id',
                'stitching_return_items.size',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy('stitching_returns.catalogue_id', 'stitching_returns.design_id', 'stitching_return_items.size')
            ->get()
            ->groupBy('catalogue_id');

        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        // Attach all qty data to each design
        $catalogues->each(function ($cat) use ($unitByDesign, $assignedQty, $returnedQty, $assignedSizesRaw, $returnedSizesRaw, $sizes) {
            $catUnits    = $unitByDesign[$cat->id] ?? collect();
            $catAssigned = $assignedQty[$cat->id]  ?? collect();
            $catReturned = $returnedQty[$cat->id]  ?? collect();

            // Build per-size lookup: [design_id => [size => qty]]
            $catAssignedSizes = [];
            foreach (($assignedSizesRaw[$cat->id] ?? collect()) as $row) {
                $catAssignedSizes[$row->design_id][$row->size] = (int) $row->qty;
            }
            $catReturnedSizes = [];
            foreach (($returnedSizesRaw[$cat->id] ?? collect()) as $row) {
                $catReturnedSizes[$row->design_id][$row->size] = (int) $row->qty;
            }

            $cat->designs->each(function ($design) use ($catUnits, $catAssigned, $catReturned, $catAssignedSizes, $catReturnedSizes, $sizes) {
                $design->stitching_unit = $catUnits[$design->id] ?? null;
                $design->assigned_qty   = (int) ($catAssigned[$design->id] ?? 0);
                $design->returned_qty   = (int) ($catReturned[$design->id] ?? 0);
                $design->remaining_qty  = max(0, $design->assigned_qty - $design->returned_qty);

                $assignedBySize  = $catAssignedSizes[$design->id]  ?? [];
                $returnedBySize  = $catReturnedSizes[$design->id]  ?? [];
                $remainingBySize = [];
                foreach ($sizes as $s) {
                    $a = (int) ($assignedBySize[$s]  ?? 0);
                    $r = (int) ($returnedBySize[$s]  ?? 0);
                    $remainingBySize[$s] = max(0, $a - $r);
                }
                $design->assigned_sizes  = (object) array_map(fn($s) => (int)($assignedBySize[$s]  ?? 0), array_combine($sizes, $sizes));
                $design->returned_sizes  = (object) array_map(fn($s) => (int)($returnedBySize[$s]  ?? 0), array_combine($sizes, $sizes));
                $design->remaining_sizes = (object) $remainingBySize;
            });
        });

        return view('production.stitching-returns.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'   => 'required|exists:catalogues,id',
            'design_id'      => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'stitching_unit' => 'required|integer|in:1,2,3,4',
            'return_date'    => 'required|date',
            'items'          => 'required|array',
            'items.*.size'   => 'required|in:xs,s,m,l,xl',
            'items.*.qty'    => 'nullable|integer|min:0',
        ]);

        $totalPieces = collect($validated['items'])->sum(fn($i) => (int) ($i['qty'] ?? 0));
        if ($totalPieces === 0) {
            return back()->withInput()->withErrors(['items' => 'Please enter at least one piece quantity.']);
        }

        // ── Remaining-qty constraint ────────────────────────────────────
        $assignedToStitching = (int) DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $validated['catalogue_id'])
            ->where('production_assignments.design_id', $validated['design_id'])
            ->where('production_assignments.destination', 'stitching_unit')
            ->sum('production_assignment_items.quantity');

        $alreadyReturned = (int) DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->where('stitching_returns.catalogue_id', $validated['catalogue_id'])
            ->where('stitching_returns.design_id', $validated['design_id'])
            ->sum('stitching_return_items.quantity');

        $remainingQty = max(0, $assignedToStitching - $alreadyReturned);

        if ($totalPieces > $remainingQty) {
            return back()->withInput()->withErrors([
                'items' => "Cannot log {$totalPieces} pieces — only {$remainingQty} are still outstanding for this design (assigned: {$assignedToStitching}, already returned: {$alreadyReturned}).",
            ]);
        }
        // ────────────────────────────────────────────────────────────────

        $return = StitchingReturn::create([
            'catalogue_id'   => $validated['catalogue_id'],
            'design_id'      => $validated['design_id'],
            'stitching_unit' => $validated['stitching_unit'],
            'return_date'    => $validated['return_date'],
            'logged_by'      => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty > 0) {
                $return->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $qty,
                ]);
            }
        }

        return redirect()->route('stitching-returns.show', $return)
            ->with('success', "Stitching return of {$totalPieces} pieces from Unit {$validated['stitching_unit']} recorded.");
    }

    public function show(StitchingReturn $stitchingReturn)
    {
        $stitchingReturn->load(['catalogue', 'design', 'items', 'loggedBy']);
        return view('production.stitching-returns.show', compact('stitchingReturn'));
    }
}
