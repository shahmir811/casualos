<?php

namespace App\Http\Controllers;

use App\Models\OutsourcedBatch;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OutsourcedBatchController extends Controller
{
    public function index()
    {
        $batches = OutsourcedBatch::with(['catalogue', 'items'])->latest()->paginate(20);
        return view('production.outsourced-batches.index', compact('batches'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')->with('designs')->orderBy('name')->get();
        return view('production.outsourced-batches.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id'           => 'required|exists:catalogues,id',
            'received_date'          => 'required|date',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array',
            'items.*.design_id'      => 'required|exists:designs,id',
            'items.*.total_pieces'   => 'required|integer|min:0',
        ]);

        $batch = OutsourcedBatch::create([
            'catalogue_id'  => $validated['catalogue_id'],
            'received_date' => $validated['received_date'],
            'notes'         => $validated['notes'] ?? null,
            'logged_by'     => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            if ($item['total_pieces'] > 0) {
                $batch->items()->create([
                    'design_id'    => $item['design_id'],
                    'total_pieces' => $item['total_pieces'],
                ]);
            }
        }

        return redirect()->route('outsourced-batches.show', $batch)
            ->with('success', 'Outsourced batch recorded.');
    }

    public function show(OutsourcedBatch $outsourcedBatch)
    {
        $outsourcedBatch->load(['catalogue', 'items.design', 'loggedBy']);
        return view('production.outsourced-batches.show', compact('outsourcedBatch'));
    }
}
