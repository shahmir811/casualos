<?php

namespace App\Http\Controllers;

use App\Models\StitchingReturn;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StitchingReturnController extends Controller
{
    public function index()
    {
        $returns = StitchingReturn::with(['catalogue', 'design', 'items', 'loggedBy'])->latest()->paginate(20);
        return view('production.stitching-returns.index', compact('returns'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->orderBy('name')
            ->get();
        return view('production.stitching-returns.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id' => 'required|exists:catalogues,id',
            'design_id'    => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'return_date'  => 'required|date',
            'items'        => 'required|array',
            'items.*.size' => 'required|in:xs,s,m,l,xl',
            'items.*.qty'  => 'required|integer|min:0',
        ]);

        $return = StitchingReturn::create([
            'catalogue_id' => $validated['catalogue_id'],
            'design_id'    => $validated['design_id'],
            'return_date'  => $validated['return_date'],
            'logged_by'    => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['qty'] > 0) {
                $return->items()->create([
                    'size'     => $item['size'],
                    'quantity' => $item['qty'],
                ]);
            }
        }

        $total = collect($validated['items'])->sum('qty');

        return redirect()->route('stitching-returns.show', $return)
            ->with('success', "Stitching return of {$total} pieces recorded.");
    }

    public function show(StitchingReturn $stitchingReturn)
    {
        $stitchingReturn->load(['catalogue', 'design', 'items', 'loggedBy']);
        return view('production.stitching-returns.show', compact('stitchingReturn'));
    }
}
