<?php

namespace App\Http\Controllers;

use App\Models\StitchingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $unit = StitchingUnit::create([
            'number'         => $nextNumber,
            'name'           => $validated['name'],
            'payment_type'   => $validated['payment_type'],
            'per_piece_rate' => $validated['payment_type'] === 'per_piece' ? ($validated['per_piece_rate'] ?? null) : null,
            'is_active'      => true,
        ]);

        activity()
            ->performedOn($unit)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'unit_number'    => $unit->number,
                'name'           => $unit->name,
                'payment_type'   => ucfirst(str_replace('_', ' ', $unit->payment_type)),
                'per_piece_rate' => $unit->per_piece_rate !== null ? 'PKR ' . number_format((float) $unit->per_piece_rate, 0) : '—',
                'status'         => 'Active',
                'created_by'     => Auth::user()->name,
            ])
            ->log("Stitching unit {$unit->number} — {$unit->name} created");

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

        $logProps = ['unit_number' => $stitchingUnit->number, 'name' => $validated['name']];
        foreach (['name', 'payment_type', 'per_piece_rate'] as $field) {
            $old = (string) ($stitchingUnit->getOriginal($field) ?? '');
            $new = (string) ($validated[$field] ?? '');
            if ($old !== $new) {
                $logProps[$field] = ($old ?: '—') . ' → ' . ($new ?: '—');
            }
        }

        $stitchingUnit->update([
            'name'           => $validated['name'],
            'payment_type'   => $validated['payment_type'],
            'per_piece_rate' => $validated['payment_type'] === 'per_piece' ? ($validated['per_piece_rate'] ?? null) : null,
        ]);

        activity()
            ->performedOn($stitchingUnit)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties($logProps)
            ->log("Stitching unit {$stitchingUnit->number} — {$stitchingUnit->name} updated");

        return redirect()->route('stitching-units.index')
            ->with('success', "Unit {$stitchingUnit->number} updated.");
    }

    public function toggle(StitchingUnit $stitchingUnit)
    {
        $previousState = $stitchingUnit->is_active ? 'active' : 'inactive';
        $stitchingUnit->update(['is_active' => ! $stitchingUnit->is_active]);
        $newState = $stitchingUnit->is_active ? 'active' : 'inactive';

        activity()
            ->performedOn($stitchingUnit)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'unit_number'    => $stitchingUnit->number,
                'name'           => $stitchingUnit->name,
                'status_changed' => $previousState . ' → ' . $newState,
                'action_by'      => Auth::user()->name,
            ])
            ->log("Stitching unit {$stitchingUnit->number} — {$stitchingUnit->name} {$newState}");

        $state = $stitchingUnit->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Unit {$stitchingUnit->number} — {$stitchingUnit->name} {$state}.");
    }
}
