<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Design;
use App\Models\NaeemPakkiReturn;
use App\Models\NaeemPakkiReturnItem;
use App\Models\ProductionAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NaeemPakkiController extends Controller
{
    public function index(Request $request)
    {
        $selectedCatalogueId = (int) session('active_catalogue_id', 0) ?: '';
        $selectedDesignId    = $request->get('design_id', '');

        $catalogueDesigns = $selectedCatalogueId
            ? Design::where('catalogue_id', $selectedCatalogueId)
                ->where('manufacturing_type', 'in_house')
                ->where('needs_naeem_pakki', true)
                ->orderBy('sort_order')
                ->get()
            : collect();

        $query = ProductionAssignment::with([
            'catalogue',
            'npDesigns.design',
            'npDesigns.returnItems',
        ])
            ->where('destination', 'naeem_pakki')
            ->whereHas('npDesigns')
            ->latest();

        if ($selectedCatalogueId) $query->where('catalogue_id', $selectedCatalogueId);
        if ($selectedDesignId)    $query->whereHas('npDesigns', fn($q) => $q->where('design_id', $selectedDesignId));

        $assignments = $query->paginate(20)->withQueryString();

        return view('production.naeem-pakki.index', compact(
            'assignments', 'catalogueDesigns', 'selectedCatalogueId', 'selectedDesignId'
        ));
    }

    public function show(ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load([
            'catalogue',
            'loggedBy',
            'npDesigns.design',
            'npDesigns.returnItems',
            'naeemPakkiReturns.items.npDesign.design',
            'naeemPakkiReturns.loggedBy',
        ]);

        return view('production.naeem-pakki.show', compact('productionAssignment'));
    }

    public function logReturn(Request $request, ProductionAssignment $productionAssignment)
    {
        $productionAssignment->load(['npDesigns.returnItems']);

        $request->validate([
            'return_date'          => 'required|date',
            'items'                => 'required|array',
            'items.*.np_design_id' => 'required|exists:production_assignment_np_designs,id',
            'items.*.quantity'     => 'required|integer|min:0',
        ]);

        // Build only rows with qty > 0 and validate against outstanding
        $linesToSave = [];
        foreach ($request->items as $item) {
            $qty = (int) $item['quantity'];
            if ($qty === 0) continue;

            $npDesign = $productionAssignment->npDesigns->firstWhere('id', $item['np_design_id']);
            if (!$npDesign) continue;

            $outstanding = $npDesign->outstandingPieces();
            if ($qty > $outstanding) {
                return back()->withInput()->withErrors([
                    'items' => "Cannot return {$qty} pcs for '{$npDesign->design->name}' — only {$outstanding} pcs outstanding.",
                ]);
            }

            $linesToSave[] = ['np_design_id' => $npDesign->id, 'quantity' => $qty];
        }

        if (empty($linesToSave)) {
            return back()->withInput()->withErrors([
                'items' => 'Enter at least one piece quantity to log a return.',
            ]);
        }

        DB::transaction(function () use ($request, $productionAssignment, $linesToSave) {
            $batch = NaeemPakkiReturn::create([
                'production_assignment_id' => $productionAssignment->id,
                'return_date'              => $request->return_date,
                'logged_by'                => Auth::id(),
            ]);

            foreach ($linesToSave as $line) {
                NaeemPakkiReturnItem::create([
                    'naeem_pakki_return_id' => $batch->id,
                    'np_design_id'          => $line['np_design_id'],
                    'quantity'              => $line['quantity'],
                ]);
            }
        });

        $total = collect($linesToSave)->sum('quantity');

        return redirect()->route('naeem-pakki-sends.show', $productionAssignment)
            ->with('success', $total . ' pieces returned from Naeem Pakki.');
    }
}
