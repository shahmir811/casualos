<?php

namespace App\Http\Controllers;

use App\Models\FabricBatch;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FabricBatchController extends Controller
{
    public function index()
    {
        $batches = FabricBatch::with(['catalogue', 'items'])->latest()->paginate(20);
        return view('production.fabric-batches.index', compact('batches'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')->orderBy('name')->get();
        return view('production.fabric-batches.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id' => 'required|exists:catalogues,id',
            'arrival_date' => 'required|date',
            'notes'        => 'nullable|string',
            'items'        => 'required|array',
            'items.*.design_id' => 'required|exists:designs,id',
            'items.*.total_pieces' => 'required|integer|min:1',
        ]);

        $batch = FabricBatch::create([
            'catalogue_id' => $validated['catalogue_id'],
            'arrival_date' => $validated['arrival_date'],
            'notes'        => $validated['notes'] ?? null,
            'logged_by'    => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            $batch->items()->create([
                'design_id'    => $item['design_id'],
                'total_pieces' => $item['total_pieces'],
            ]);
        }

        return redirect()->route('fabric-batches.show', $batch)
            ->with('success', 'Fabric batch arrival recorded.');
    }

    public function show(FabricBatch $fabricBatch)
    {
        $fabricBatch->load(['catalogue', 'items.design', 'loggedBy']);
        return view('production.fabric-batches.show', compact('fabricBatch'));
    }
}
