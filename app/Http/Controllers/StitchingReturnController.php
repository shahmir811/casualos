<?php

namespace App\Http\Controllers;

use App\Models\ProductionAssignment;
use App\Models\StitchingReturn;
use App\Models\StitchingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StitchingReturnController extends Controller
{
    public function index()
    {
        $assignments = ProductionAssignment::with(['catalogue', 'design', 'stitchingUnit', 'items'])
            ->where('destination', 'stitching_unit')
            ->whereHas('items')
            ->latest()
            ->paginate(20);

        // Bulk-load return totals per (catalogue, design, stitching_unit, component)
        $catalogueIds = $assignments->pluck('catalogue_id')->unique()->values()->toArray();

        $returnTotalsRaw = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->whereIn('stitching_returns.catalogue_id', $catalogueIds)
            ->whereNotNull('stitching_returns.stitching_unit_id')
            ->select(
                'stitching_returns.catalogue_id',
                'stitching_returns.design_id',
                'stitching_returns.stitching_unit_id',
                'stitching_return_items.component',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy(
                'stitching_returns.catalogue_id',
                'stitching_returns.design_id',
                'stitching_returns.stitching_unit_id',
                'stitching_return_items.component'
            )
            ->get()
            ->groupBy(fn($r) => "{$r->catalogue_id}_{$r->design_id}_{$r->stitching_unit_id}");

        // Attach computed stats to each assignment
        $assignments->each(function ($a) use ($returnTotalsRaw) {
            $key     = "{$a->catalogue_id}_{$a->design_id}_{$a->stitching_unit_id}";
            $rows    = $returnTotalsRaw[$key] ?? collect();
            $a->total_assigned   = $a->items->sum('quantity');
            $a->kameez_returned  = (int) ($rows->firstWhere('component', 'kameez')?->qty ?? 0);
            $a->shalwar_returned = (int) ($rows->firstWhere('component', 'shalwar')?->qty ?? 0);
            $a->dupatta_returned = (int) ($rows->firstWhere('component', 'dupatta')?->qty ?? 0);
            $a->is_complete      = $a->total_assigned > 0
                && $a->kameez_returned  >= $a->total_assigned
                && $a->shalwar_returned >= $a->total_assigned
                && $a->dupatta_returned >= $a->total_assigned;
        });

        // Unit summary cards
        $stitchingUnits = StitchingUnit::orderBy('number')->get();

        $unitAssigned = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.destination', 'stitching_unit')
            ->whereNotNull('production_assignments.stitching_unit_id')
            ->select(
                'production_assignments.stitching_unit_id',
                DB::raw('SUM(production_assignment_items.quantity) as total_assigned')
            )
            ->groupBy('production_assignments.stitching_unit_id')
            ->get()
            ->keyBy('stitching_unit_id');

        $unitReturned = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->whereNotNull('stitching_returns.stitching_unit_id')
            ->select(
                'stitching_returns.stitching_unit_id',
                'stitching_return_items.component',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy('stitching_returns.stitching_unit_id', 'stitching_return_items.component')
            ->get()
            ->groupBy('stitching_unit_id');

        return view('production.stitching-returns.index', compact(
            'assignments', 'stitchingUnits', 'unitAssigned', 'unitReturned'
        ));
    }

    public function showAssignment(ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load(['catalogue', 'design', 'stitchingUnit', 'items', 'loggedBy']);

        $sizes      = ['xs', 's', 'm', 'l', 'xl'];
        $components = ['kameez', 'shalwar', 'dupatta'];

        // All returns for this assignment (matched by catalogue + design + stitching unit)
        $stitchingReturns = StitchingReturn::with(['items', 'loggedBy'])
            ->where('catalogue_id',      $productionAssignment->catalogue_id)
            ->where('design_id',         $productionAssignment->design_id)
            ->where('stitching_unit_id', $productionAssignment->stitching_unit_id)
            ->latest()
            ->get();

        // Assigned qty per size from assignment items
        $assignedPerSize = $productionAssignment->items->pluck('quantity', 'size')->toArray();

        // Returned totals per (size, component)
        $returnedRaw = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->where('stitching_returns.catalogue_id',      $productionAssignment->catalogue_id)
            ->where('stitching_returns.design_id',         $productionAssignment->design_id)
            ->where('stitching_returns.stitching_unit_id', $productionAssignment->stitching_unit_id)
            ->select(
                'stitching_return_items.size',
                'stitching_return_items.component',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy('stitching_return_items.size', 'stitching_return_items.component')
            ->get()
            ->groupBy('size');

        // Build matrix: matrix[size][component] = [assigned, returned, remaining]
        $matrix = [];
        foreach ($sizes as $size) {
            $assigned   = (int) ($assignedPerSize[$size] ?? 0);
            $sizeRows   = $returnedRaw[$size] ?? collect();
            foreach ($components as $component) {
                $returned = (int) ($sizeRows->firstWhere('component', $component)?->qty ?? 0);
                $matrix[$size][$component] = [
                    'assigned'  => $assigned,
                    'returned'  => $returned,
                    'remaining' => max(0, $assigned - $returned),
                ];
            }
        }

        // remaining[size][component] — passed to Alpine.js on the form
        $remainingPerSizePerComponent = [];
        foreach ($sizes as $size) {
            foreach ($components as $component) {
                $remainingPerSizePerComponent[$size][$component] = $matrix[$size][$component]['remaining'];
            }
        }

        $isFullyComplete = collect($components)->every(
            fn($c) => collect($sizes)->every(fn($s) => $matrix[$s][$c]['remaining'] === 0 && $matrix[$s][$c]['assigned'] > 0)
        );

        return view('production.stitching-returns.assignment', compact(
            'productionAssignment', 'stitchingReturns', 'matrix',
            'sizes', 'components', 'remainingPerSizePerComponent', 'isFullyComplete'
        ));
    }

    public function reportAssignment(ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load(['catalogue', 'design', 'stitchingUnit', 'items', 'loggedBy']);

        $sizes      = ['xs', 's', 'm', 'l', 'xl'];
        $components = ['kameez', 'shalwar', 'dupatta'];

        $stitchingReturns = StitchingReturn::with(['items', 'loggedBy'])
            ->where('catalogue_id',      $productionAssignment->catalogue_id)
            ->where('design_id',         $productionAssignment->design_id)
            ->where('stitching_unit_id', $productionAssignment->stitching_unit_id)
            ->latest()
            ->get();

        $assignedPerSize = $productionAssignment->items->pluck('quantity', 'size')->toArray();

        $returnedRaw = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->where('stitching_returns.catalogue_id',      $productionAssignment->catalogue_id)
            ->where('stitching_returns.design_id',         $productionAssignment->design_id)
            ->where('stitching_returns.stitching_unit_id', $productionAssignment->stitching_unit_id)
            ->select(
                'stitching_return_items.size',
                'stitching_return_items.component',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy('stitching_return_items.size', 'stitching_return_items.component')
            ->get()
            ->groupBy('size');

        $matrix = [];
        foreach ($sizes as $size) {
            $assigned = (int) ($assignedPerSize[$size] ?? 0);
            $sizeRows = $returnedRaw[$size] ?? collect();
            foreach ($components as $component) {
                $returned = (int) ($sizeRows->firstWhere('component', $component)?->qty ?? 0);
                $matrix[$size][$component] = [
                    'assigned'    => $assigned,
                    'returned'    => $returned,
                    'outstanding' => max(0, $assigned - $returned),
                ];
            }
        }

        $totalAssigned = $productionAssignment->items->sum('quantity');

        return view('production.stitching-returns.report', compact(
            'productionAssignment', 'stitchingReturns', 'matrix',
            'sizes', 'components', 'totalAssigned'
        ));
    }

    public function storeReturn(Request $request, ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load('items');

        $request->validate([
            'return_date'         => 'required|date',
            'components'          => 'required|array|min:1',
            'components.*'        => 'in:kameez,shalwar,dupatta',
            'component_items'     => 'required|array',
            'component_items.*.*' => 'nullable|integer|min:0',
        ]);

        $components      = $request->input('components', []);
        $assignedPerSize = $productionAssignment->items->pluck('quantity', 'size')->toArray();

        // Already returned per (component, size)
        $alreadyReturned = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->where('stitching_returns.catalogue_id',      $productionAssignment->catalogue_id)
            ->where('stitching_returns.design_id',         $productionAssignment->design_id)
            ->where('stitching_returns.stitching_unit_id', $productionAssignment->stitching_unit_id)
            ->whereIn('stitching_return_items.component', $components)
            ->select(
                'stitching_return_items.component',
                'stitching_return_items.size',
                DB::raw('SUM(stitching_return_items.quantity) as qty')
            )
            ->groupBy('stitching_return_items.component', 'stitching_return_items.size')
            ->get()
            ->groupBy('component')
            ->map(fn($rows) => $rows->pluck('qty', 'size'));

        $linesToSave = [];
        $totalPieces = 0;

        foreach ($components as $component) {
            $prevReturned = $alreadyReturned[$component] ?? collect();
            $sizeData     = $request->input("component_items.{$component}", []);

            foreach (['xs', 's', 'm', 'l', 'xl'] as $size) {
                $qty = (int) ($sizeData[$size] ?? 0);
                if ($qty === 0) continue;

                $assigned  = (int) ($assignedPerSize[$size] ?? 0);
                $previous  = (int) ($prevReturned[$size] ?? 0);
                $remaining = max(0, $assigned - $previous);

                if ($qty > $remaining) {
                    return back()->withInput()->withErrors([
                        'items' => "Cannot return {$qty} " . strtoupper($size) . " {$component} — only {$remaining} pcs outstanding.",
                    ]);
                }

                $linesToSave[] = ['component' => $component, 'size' => $size, 'qty' => $qty];
                $totalPieces  += $qty;
            }
        }

        if (empty($linesToSave)) {
            return back()->withInput()->withErrors([
                'items' => 'Enter at least one piece quantity to log a return.',
            ]);
        }

        DB::transaction(function () use ($request, $productionAssignment, $linesToSave) {
            $return = StitchingReturn::create([
                'catalogue_id'      => $productionAssignment->catalogue_id,
                'design_id'         => $productionAssignment->design_id,
                'stitching_unit_id' => $productionAssignment->stitching_unit_id,
                'return_date'       => $request->input('return_date'),
                'logged_by'         => Auth::id(),
            ]);

            foreach ($linesToSave as $line) {
                $return->items()->create([
                    'size'      => $line['size'],
                    'component' => $line['component'],
                    'quantity'  => $line['qty'],
                ]);
            }
        });

        $componentLabels = implode(' + ', array_map('ucfirst', $components));

        return redirect()
            ->route('stitching-assignments.show', $productionAssignment)
            ->with('success', "{$totalPieces} pieces ({$componentLabels}) logged successfully.");
    }

    public function show(StitchingReturn $stitchingReturn)
    {
        $stitchingReturn->load(['catalogue', 'design', 'items', 'stitchingUnit', 'loggedBy']);
        return view('production.stitching-returns.show', compact('stitchingReturn'));
    }
}
