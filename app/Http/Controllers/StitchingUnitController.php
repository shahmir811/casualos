<?php

namespace App\Http\Controllers;

use App\Models\StitchingUnit;
use Illuminate\Http\Request;

class StitchingUnitController extends Controller
{
    public function index()
    {
        $stitchingUnits = StitchingUnit::orderBy('number')->get();

        return view('admin.stitching-units.index', compact('stitchingUnits'));
    }

    public function create()
    {
        $nextNumber = StitchingUnit::nextNumber();

        return view('admin.stitching-units.create', compact('nextNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'payment_type'   => 'required|in:salary,per_piece',
            'per_piece_rate' => 'required_if:payment_type,per_piece|nullable|numeric|min:0',
        ]);

        $nextNumber = StitchingUnit::nextNumber();

        StitchingUnit::create([
            'number'         => $nextNumber,
            'name'           => $validated['name'],
            'payment_type'   => $validated['payment_type'],
            'per_piece_rate' => $validated['payment_type'] === 'per_piece' ? ($validated['per_piece_rate'] ?? null) : null,
            'is_active'      => true,
        ]);

        return redirect()->route('stitching-units.index')
            ->with('success', "Unit {$nextNumber} — {$validated['name']} added.");
    }

    public function edit(StitchingUnit $stitchingUnit)
    {
        return view('admin.stitching-units.edit', compact('stitchingUnit'));
    }

    public function update(Request $request, StitchingUnit $stitchingUnit)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'payment_type'   => 'required|in:salary,per_piece',
            'per_piece_rate' => 'required_if:payment_type,per_piece|nullable|numeric|min:0',
        ]);

        $stitchingUnit->update([
            'name'           => $validated['name'],
            'payment_type'   => $validated['payment_type'],
            'per_piece_rate' => $validated['payment_type'] === 'per_piece' ? ($validated['per_piece_rate'] ?? null) : null,
        ]);

        return redirect()->route('stitching-units.index')
            ->with('success', "Unit {$stitchingUnit->number} updated.");
    }

    public function toggle(StitchingUnit $stitchingUnit)
    {
        $stitchingUnit->update(['is_active' => ! $stitchingUnit->is_active]);

        $state = $stitchingUnit->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Unit {$stitchingUnit->number} — {$stitchingUnit->name} {$state}.");
    }
}
