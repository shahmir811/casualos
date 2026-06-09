<?php

namespace App\Http\Controllers;

use App\Models\Wage;
use App\Models\StitchingUnit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WagesController extends Controller
{
    public function index(Request $request)
    {
        $query = Wage::with(['catalogue', 'stitchingUnit', 'confirmedBy']);

        // Filter by week — resolve any date in the week to its Saturday week_start
        if ($request->filled('week_date')) {
            $anchor           = Carbon::parse($request->input('week_date'));
            $daysSinceSat     = ($anchor->dayOfWeek + 1) % 7;
            $weekStart        = $anchor->copy()->subDays($daysSinceSat)->toDateString();
            $query->where('week_start', $weekStart);
        }

        if ($request->filled('stitching_unit_id')) {
            $query->where('stitching_unit_id', $request->input('stitching_unit_id'));
        }

        if ($request->input('status') === 'pending') {
            $query->where('is_confirmed', false);
        } elseif ($request->input('status') === 'confirmed') {
            $query->where('is_confirmed', true);
        }

        $wages = $query->latest('week_start')->paginate(20)->withQueryString();

        $units = StitchingUnit::where('is_active', true)
            ->where('payment_type', 'per_piece')
            ->orderBy('number')
            ->get(['id', 'number', 'name']);

        return view('production.wages.index', compact('wages', 'units'));
    }

    public function show(Wage $wage)
    {
        $wage->load(['catalogue', 'stitchingUnit', 'confirmedBy']);

        $items = DB::table('stitching_return_items as sri')
            ->join('stitching_returns as sr', 'sr.id', '=', 'sri.stitching_return_id')
            ->join('designs as d', 'd.id', '=', 'sr.design_id')
            ->where('sri.component', 'kameez')
            ->where('sr.catalogue_id', $wage->catalogue_id)
            ->where('sr.stitching_unit_id', $wage->stitching_unit_id)
            ->whereBetween('sr.return_date', [$wage->week_start->toDateString(), $wage->week_end->toDateString()])
            ->select('d.name as design_name', 'sri.size', DB::raw('SUM(sri.quantity) as qty'))
            ->groupBy('d.name', 'sri.size')
            ->orderBy('d.name')
            ->get();

        // Shape into [ 'Design Name' => ['xs' => 0, 's' => 5, ..., 'total' => 86] ]
        $byDesign = $items->groupBy('design_name')->map(function ($rows) {
            $sizes = ['xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0];
            foreach ($rows as $row) {
                $sizes[$row->size] = (int) $row->qty;
            }
            $sizes['total'] = array_sum($sizes);
            return $sizes;
        });

        return view('production.wages.show', compact('wage', 'byDesign'));
    }

    public function confirm(Wage $wage)
    {
        $this->denyCreativeHead();
        $wage->update([
            'is_confirmed' => true,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        return back()->with('success', 'Wage payment confirmed for week of ' . $wage->week_start->format('d M') . '.');
    }

    public function recalculate(Request $request)
    {
        $this->denyCreativeHead();
        $request->validate([
            'week_date' => 'required|date',
        ]);

        Artisan::call('wages:calculate-weekly', [
            '--week'         => $request->input('week_date'),
            '--triggered-by' => 'Manual — ' . Auth::user()->name,
        ]);

        return back()->with('success', 'Wages recalculated for the week containing ' . \Carbon\Carbon::parse($request->input('week_date'))->format('d M Y') . '. Confirmed records were not changed.');
    }
}
