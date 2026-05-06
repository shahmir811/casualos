<?php

namespace App\Http\Controllers;

use App\Models\Wage;
use App\Models\Catalogue;
use App\Models\StitchingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WagesController extends Controller
{
    public function index()
    {
        $wages = Wage::with(['catalogue', 'stitchingUnit', 'confirmedBy'])->latest()->paginate(20);
        return view('production.wages.index', compact('wages'));
    }

    public function create()
    {
        $catalogues = Catalogue::orderBy('name')->get();
        $units = StitchingUnit::where('is_active', true)
            ->where('payment_type', 'per_piece')
            ->orderBy('number')
            ->get(['id', 'number', 'name', 'per_piece_rate']);
        return view('production.wages.create', compact('catalogues', 'units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'         => 'required|exists:catalogues,id',
            'stitching_unit_id'    => 'required|exists:stitching_units,id',
            'week_start'           => 'required|date',
            'week_end'             => 'required|date|after_or_equal:week_start',
            'total_suits_stitched' => 'required|integer|min:1',
        ]);

        $unit = StitchingUnit::findOrFail($validated['stitching_unit_id']);
        $validated['wage_rate'] = $unit->per_piece_rate ?? 0;

        // total_wages is auto-computed in model boot
        Wage::create($validated);

        return redirect()->route('wages.index')->with('success', 'Wage record created.');
    }

    public function confirm(Wage $wage)
    {
        $wage->update([
            'is_confirmed' => true,
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        return back()->with('success', 'Wage payment confirmed for week of ' . $wage->week_start->format('d M') . '.');
    }
}
