<?php

namespace App\Http\Controllers;

use App\Models\ProductionAssignment;
use App\Models\Design;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionAssignmentController extends Controller
{
    public function index()
    {
        $assignments = ProductionAssignment::with(['catalogue', 'design'])->latest()->paginate(20);
        return view('production.assignments.index', compact('assignments'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')->with('designs')->orderBy('name')->get();
        return view('production.assignments.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'    => 'required|exists:catalogues,id',
            'design_id'       => 'required|exists:designs,id',
            'destination'     => 'required|in:naeem_pakki,stitching_unit',
            'assignment_date' => 'required|date',
            'items'           => 'required|array',
            'items.*.size'    => 'required|in:xs,s,m,l,xl',
            'items.*.qty'     => 'required|integer|min:0',
        ]);

        $assignment = ProductionAssignment::create([
            'catalogue_id'    => $validated['catalogue_id'],
            'design_id'       => $validated['design_id'],
            'destination'     => $validated['destination'],
            'assignment_date' => $validated['assignment_date'],
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
            ->with('success', 'Production assignment created.');
    }

    public function show(ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load(['catalogue', 'design', 'items', 'loggedBy']);
        return view('production.assignments.show', compact('productionAssignment'));
    }
}
