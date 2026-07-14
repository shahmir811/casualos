<?php

namespace App\Http\Controllers;

use App\Models\CostEstimation;
use App\Models\CostEstimationItem;
use App\Models\Design;
use App\Models\FabricBatchItem;
use App\Models\ProductionAssignmentNpDesign;
use App\Models\StitchingReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CostEstimationController extends Controller
{
    public const CATEGORIES = [
        'fabric_cost'         => 'Fabric Cost',
        'dupatta'             => 'Dupatta',
        'block_printing'      => 'Block Printing',
        'dying'               => 'Dying',
        'computer_embroidery' => 'Computer Embroidery',
        'pakki_embroidery'    => 'Pakki Embroidery',
        'hand_embroidery'     => 'Hand Embroidery',
        'accessories'         => 'Accessories',
        'stitching_cost'      => 'Stitching Cost',
    ];

    // Pakki Embroidery is system-computed, not a manually editable repeatable list.
    private const EDITABLE_CATEGORIES = ['fabric_cost', 'dupatta', 'block_printing', 'dying', 'computer_embroidery', 'hand_embroidery', 'accessories', 'stitching_cost'];

    private function blankRow(): array
    {
        return ['particulars' => '', 'avg' => '', 'qty' => '', 'rate' => ''];
    }

    /** Stitching units that have actually returned stitched pieces for this design, joined "A + B". */
    private function stitchedByNames(Design $design): string
    {
        $names = StitchingReturn::where('design_id', $design->id)
            ->join('stitching_units', 'stitching_units.id', '=', 'stitching_returns.stitching_unit_id')
            ->select('stitching_units.name', 'stitching_units.number')
            ->distinct()
            ->orderBy('stitching_units.number')
            ->pluck('stitching_units.name');

        return $names->implode(' + ');
    }

    /**
     * Actual Naeem Pakki cost recorded for this design, summed across every batch sent
     * (different batches can carry different per-piece rates). Returns null when the
     * design doesn't need Naeem Pakki work, or no batch has been sent for it yet.
     *
     * @return array{qty: float, rate: float, amount: float}|null
     */
    private function naeemPakkiCost(Design $design): ?array
    {
        if (!$design->needs_naeem_pakki) {
            return null;
        }

        $npBatches = ProductionAssignmentNpDesign::where('design_id', $design->id)->get(['quantity', 'per_piece_price']);

        if ($npBatches->isEmpty()) {
            return null;
        }

        $qty    = (float) $npBatches->sum('quantity');
        $amount = round((float) $npBatches->sum(fn($b) => $b->quantity * (float) $b->per_piece_price), 2);
        $rate   = $qty > 0 ? round($amount / $qty, 2) : 0.0;

        return ['qty' => $qty, 'rate' => $rate, 'amount' => $amount];
    }

    /**
     * GET /designs/{design}/cost-estimation
     */
    public function edit(Design $design)
    {
        if (!$design->isInHouse()) {
            return redirect()->route('catalogues.show', $design->catalogue_id)
                ->with('error', 'Cost estimation is only available for in-house designs.');
        }

        $design->load('catalogue');
        $costEstimation = CostEstimation::with('items')->where('design_id', $design->id)->first();

        // Production Qty comes from Fabric Batch — sum of everything received for this design.
        $productionPlanQty = (int) FabricBatchItem::where('design_id', $design->id)->sum('quantity');

        $pakki       = $this->naeemPakkiCost($design);
        $pakkiAmount = $pakki['amount'] ?? 0.0;

        $itemsByCategory = [];
        foreach (self::EDITABLE_CATEGORIES as $category) {
            $itemsByCategory[$category] = [];
        }

        if ($costEstimation) {
            foreach ($costEstimation->items as $item) {
                if (!isset($itemsByCategory[$item->category])) {
                    continue; // pakki_embroidery rows are never rendered as editable inputs
                }
                $itemsByCategory[$item->category][] = [
                    'particulars' => $item->particulars,
                    'avg'         => $item->avg,
                    'qty'         => $item->qty,
                    'rate'        => $item->rate,
                ];
            }
        }

        // Seed one blank row in every still-empty category so the form has a starting line.
        foreach ($itemsByCategory as $category => $rows) {
            if (empty($rows)) {
                $itemsByCategory[$category][] = $this->blankRow();
            }
        }

        return view('production.cost-estimation.edit', [
            'design'            => $design,
            'catalogue'         => $design->catalogue,
            'costEstimation'    => $costEstimation,
            'productionPlanQty' => $productionPlanQty,
            'categories'        => self::CATEGORIES,
            'itemsByCategory'   => $itemsByCategory,
            'stitchedByDisplay' => $this->stitchedByNames($design),
            'pakki'             => $pakki,
            'pakkiAmount'       => $pakkiAmount,
        ]);
    }

    /**
     * POST /designs/{design}/cost-estimation
     */
    public function update(Request $request, Design $design)
    {
        $this->denyCreativeHead();

        if (!$design->isInHouse()) {
            abort(404);
        }

        $validated = $request->validate([
            'estimation_date'        => 'nullable|date',
            'market_rate'            => 'nullable|numeric|min:0',
            'margin'                 => 'nullable|numeric',
            'approved_by'            => 'nullable|string|max:255',
            'items'                  => 'nullable|array',
            'items.*'                => 'nullable|array',
            'items.*.*.particulars'  => 'nullable|string|max:255',
            'items.*.*.avg'          => 'nullable|numeric|min:0',
            'items.*.*.qty'          => 'nullable|numeric|min:0',
            'items.*.*.rate'         => 'nullable|numeric|min:0',
        ]);

        $productionPlanQty = (int) FabricBatchItem::where('design_id', $design->id)->sum('quantity');
        $pakki             = $this->naeemPakkiCost($design);
        $stitchedByDisplay = $this->stitchedByNames($design);

        DB::transaction(function () use ($validated, $request, $design, $productionPlanQty, $pakki, $stitchedByDisplay) {
            $costEstimation = CostEstimation::firstOrNew(['design_id' => $design->id]);
            $isNew = !$costEstimation->exists;

            $costEstimation->catalogue_id        = $design->catalogue_id;
            $costEstimation->estimation_date     = $validated['estimation_date'] ?? null;
            $costEstimation->stitched_by         = $stitchedByDisplay ?: null;
            $costEstimation->production_plan_qty = $productionPlanQty;
            $costEstimation->market_rate         = $validated['market_rate'] ?? null;
            $costEstimation->margin              = $validated['margin'] ?? null;
            $costEstimation->approved_by         = $validated['approved_by'] ?? null;

            if ($isNew) {
                $costEstimation->prepared_by = Auth::id();
            }

            $totalCost = 0;
            $rows = [];

            foreach (self::EDITABLE_CATEGORIES as $category) {
                foreach ($request->input("items.$category", []) as $row) {
                    $particulars = trim((string) ($row['particulars'] ?? ''));
                    $qty         = isset($row['qty'])  && $row['qty']  !== '' ? (float) $row['qty']  : null;
                    $rate        = isset($row['rate']) && $row['rate'] !== '' ? (float) $row['rate'] : null;
                    $avg         = isset($row['avg'])  && $row['avg']  !== '' ? (float) $row['avg']  : null;

                    if ($particulars === '' && $qty === null && $rate === null) {
                        continue;
                    }

                    // Amount is always system-multiplied — never accepted from the client.
                    $amount = round(($qty ?? 0) * ($rate ?? 0), 2);

                    $rows[] = [
                        'category'    => $category,
                        'particulars' => $particulars !== '' ? $particulars : null,
                        'avg'         => $avg,
                        'qty'         => $qty,
                        'rate'        => $rate,
                        'amount'      => $amount,
                    ];
                    $totalCost += $amount;
                }
            }

            // Pakki Embroidery — entirely system-derived, never accepted from the client.
            if ($pakki !== null) {
                $rows[] = [
                    'category'    => 'pakki_embroidery',
                    'particulars' => 'Naeem Pakki',
                    'avg'         => null,
                    'qty'         => $pakki['qty'],
                    'rate'        => $pakki['rate'],
                    'amount'      => $pakki['amount'],
                ];
                $totalCost += $pakki['amount'];
            }

            $costEstimation->total_cost    = $totalCost;
            $costEstimation->per_unit_cost = $productionPlanQty > 0 ? round($totalCost / $productionPlanQty, 2) : null;
            $costEstimation->save();

            $costEstimation->items()->delete();
            foreach ($rows as $row) {
                $row['cost_estimation_id'] = $costEstimation->id;
                CostEstimationItem::create($row);
            }
        });

        activity()
            ->performedOn($design)
            ->causedBy(Auth::user())
            ->event('detail')
            ->withProperties([
                'catalogue' => $design->catalogue?->name ?? '—',
                'design'    => $design->name,
            ])
            ->log('Cost estimation saved for design "' . $design->name . '"');

        return redirect()->route('catalogues.show', $design->catalogue_id)
            ->with('success', 'Cost estimation saved for ' . $design->name . '.');
    }

    /**
     * GET /designs/{design}/cost-estimation/pdf
     */
    public function pdf(Design $design)
    {
        $design->load('catalogue');
        $costEstimation = CostEstimation::with(['items', 'preparedBy'])->where('design_id', $design->id)->first();

        if (!$costEstimation) {
            return redirect()->route('catalogues.show', $design->catalogue_id)
                ->with('error', 'No cost estimation has been saved yet for ' . $design->name . '.');
        }

        $itemsByCategory = $costEstimation->items->groupBy('category');
        $logoDataUri      = pdf_logo_data_uri();

        $pdf = Pdf::loadView('production.cost-estimation.pdf', [
            'design'           => $design,
            'catalogue'        => $design->catalogue,
            'costEstimation'   => $costEstimation,
            'itemsByCategory'  => $itemsByCategory,
            'categories'       => self::CATEGORIES,
            'logoDataUri'      => $logoDataUri,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('cost-estimation-' . Str::slug($design->name) . '.pdf');
    }
}
