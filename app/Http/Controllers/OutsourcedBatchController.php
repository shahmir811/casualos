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
        $catalogues = Catalogue::with(['designs' => fn($q) => $q->where('manufacturing_type', 'outsourced')->orderBy('name')])
            ->orderBy('name')
            ->get();
        return view('production.outsourced-batches.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $sizes = ['xs', 's', 'm', 'l', 'xl'];

        $validated = $request->validate([
            'catalogue_id'      => 'required|exists:catalogues,id',
            'received_date'     => 'required|date',
            'notes'             => 'nullable|string',
            'items'             => 'required|array',
            'items.*.design_id' => 'required|exists:designs,id',
            'items.*.xs'        => 'nullable|integer|min:0',
            'items.*.s'         => 'nullable|integer|min:0',
            'items.*.m'         => 'nullable|integer|min:0',
            'items.*.l'         => 'nullable|integer|min:0',
            'items.*.xl'        => 'nullable|integer|min:0',
        ]);

        $totalPieces = 0;
        foreach ($validated['items'] as $item) {
            foreach ($sizes as $size) {
                $totalPieces += (int) ($item[$size] ?? 0);
            }
        }
        if ($totalPieces === 0) {
            return back()->withInput()->withErrors(['items' => 'Please enter at least one piece quantity.']);
        }

        $batch = null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $sizes, &$batch) {
            $batch = OutsourcedBatch::create([
                'catalogue_id'  => $validated['catalogue_id'],
                'received_date' => $validated['received_date'],
                'notes'         => $validated['notes'] ?? null,
                'logged_by'     => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                foreach ($sizes as $size) {
                    $qty = (int) ($item[$size] ?? 0);
                    if ($qty > 0) {
                        $batch->items()->create([
                            'design_id'         => $item['design_id'],
                            'size'              => $size,
                            'quantity'          => $qty,
                            'original_quantity' => $qty,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('outsourced-batches.show', $batch)
            ->with('success', 'Outsourced batch recorded.');
    }

    public function show(OutsourcedBatch $outsourcedBatch)
    {
        $outsourcedBatch->load(['catalogue', 'items.design', 'loggedBy']);
        return view('production.outsourced-batches.show', compact('outsourcedBatch'));
    }
}
