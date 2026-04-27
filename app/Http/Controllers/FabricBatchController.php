<?php

namespace App\Http\Controllers;

use App\Models\FabricBatch;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FabricBatchController extends Controller
{
    public function index()
    {
        $batches = FabricBatch::with(['catalogue', 'items'])->latest()->paginate(20);
        return view('production.fabric-batches.index', compact('batches'));
    }

    public function create()
    {
        $catalogues = Catalogue::where('status', 'open')
            ->with(['designs' => fn($q) => $q->where('manufacturing_type', 'in_house')])
            ->orderBy('name')
            ->get();
        return view('production.fabric-batches.create', compact('catalogues'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'catalogue_id' => 'required|exists:catalogues,id',
            'arrival_date' => 'required|date',
            'notes'        => 'nullable|string',
            'items'        => 'required|array',
            'items.*.design_id' => ['required', Rule::exists('designs', 'id')->where('manufacturing_type', 'in_house')],
            'items.*.quantity'  => 'required|integer|min:1',
        ]);

        $batch = FabricBatch::create([
            'catalogue_id' => $validated['catalogue_id'],
            'arrival_date' => $validated['arrival_date'],
            'notes'        => $validated['notes'] ?? null,
            'logged_by'    => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            $batch->items()->create([
                'design_id' => $item['design_id'],
                'quantity'  => $item['quantity'],
            ]);
        }

        // Auto-transition all confirmed orders for this catalogue → stitching
        \App\Models\Order::where('catalogue_id', $validated['catalogue_id'])
            ->where('status', 'confirmed')
            ->update(['status' => 'stitching']);

        return redirect()->route('fabric-batches.show', $batch)
            ->with('success', 'Fabric batch arrival recorded.');
    }

    public function show(FabricBatch $fabricBatch)
    {
        $fabricBatch->load(['catalogue', 'loggedBy']);

        // Only show in-house design items — outsourced designs are not part of fabric batch tracking
        $fabricBatch->setRelation('items',
            $fabricBatch->items()
                ->with('design')
                ->whereHas('design', fn($q) => $q->where('manufacturing_type', 'in_house'))
                ->get()
        );

        // Naeem Pakki tracking — which designs from this catalogue are at Naeem Pakki
        $naeemPakkiAssignments = \App\Models\ProductionAssignment::with([
                'design',
                'items',
                'naeemPakkiSend.items',
                'naeemPakkiSend.returns.items',
            ])
            ->where('catalogue_id', $fabricBatch->catalogue_id)
            ->where('destination', 'naeem_pakki')
            ->get()
            ->map(function ($assignment) {
                $send       = $assignment->naeemPakkiSend;
                $sentQty    = $send?->items->sum('quantity') ?? 0;
                $returnedQty= $send?->returns->flatMap->items->sum('quantity') ?? 0;
                $pending    = max(0, $sentQty - $returnedQty);

                return [
                    'design'       => $assignment->design->name ?? '—',
                    'rate'         => $assignment->naeem_pakki_rate,
                    'assigned_qty' => $assignment->items->sum('quantity'),
                    'sent_qty'     => $sentQty,
                    'returned_qty' => $returnedQty,
                    'pending_qty'  => $pending,
                ];
            });

        return view('production.fabric-batches.show', compact('fabricBatch', 'naeemPakkiAssignments'));
    }
}
