<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ProductionTrackerController
 *
 * Answers the question: "For a given catalogue, where is every piece right now?"
 *
 * Per-design breakdown across the full pipeline:
 *   Fabric Received → Assigned → [Naeem Pakki ↔] → Stitching → In Factory → [Tarpai ↔] → Packed
 */
class ProductionTrackerController extends Controller
{
    public function index(Request $request)
    {
        // All catalogues for the selector dropdown
        $catalogues = Catalogue::orderByDesc('created_at')->get();

        $selectedId = $request->input('catalogue_id', $catalogues->first()?->id);
        $catalogue  = $catalogues->find($selectedId);

        if (! $catalogue) {
            return view('production.tracker.index', [
                'catalogues'   => $catalogues,
                'catalogue'    => null,
                'designs'      => collect(),
                'summary'      => null,
            ]);
        }

        $catId = $catalogue->id;

        /* ----------------------------------------------------------------
         | 1. Fabric received per design
         | fabric_batch_items → fabric_batches (catalogue_id)
         * -------------------------------------------------------------- */
        $fabricReceived = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $catId)
            ->select('fabric_batch_items.design_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batch_items.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | 2. Production assignment destination & assigned qty per design
         * -------------------------------------------------------------- */
        $assignmentDestination = DB::table('production_assignments')
            ->where('catalogue_id', $catId)
            ->pluck('destination', 'design_id');

        $assigned = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | 3. Naeem Pakki — sent & returned per design
         |
         | naeem_pakki_sends has catalogue_id + design_id directly (no sub-items table).
         |
         | naeem_pakki_returns links to production_assignment_id (naeem_pakki_send_id
         | was dropped in migration 2026_05_05_000001). Per-design qty lives in
         | naeem_pakki_return_items, joined to production_assignment_np_designs for design_id.
         * -------------------------------------------------------------- */
        $npSent = DB::table('production_assignment_np_designs')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_np_designs.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->where('production_assignments.destination', 'naeem_pakki')
            ->select('production_assignment_np_designs.design_id', DB::raw('SUM(production_assignment_np_designs.quantity) as qty'))
            ->groupBy('production_assignment_np_designs.design_id')
            ->pluck('qty', 'design_id');

        $npReturned = DB::table('naeem_pakki_return_items')
            ->join('naeem_pakki_returns', 'naeem_pakki_returns.id', '=', 'naeem_pakki_return_items.naeem_pakki_return_id')
            ->join('production_assignments', 'production_assignments.id', '=', 'naeem_pakki_returns.production_assignment_id')
            ->join('production_assignment_np_designs', 'production_assignment_np_designs.id', '=', 'naeem_pakki_return_items.np_design_id')
            ->where('production_assignments.catalogue_id', $catId)
            ->select('production_assignment_np_designs.design_id', DB::raw('SUM(naeem_pakki_return_items.quantity) as qty'))
            ->groupBy('production_assignment_np_designs.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | 4. Stitching returns per design (pieces RETURNED FROM stitching)
         * -------------------------------------------------------------- */
        $stitchingReturned = DB::table('stitching_return_items')
            ->join('stitching_returns', 'stitching_returns.id', '=', 'stitching_return_items.stitching_return_id')
            ->where('stitching_returns.catalogue_id', $catId)
            ->select('stitching_returns.design_id', DB::raw('SUM(stitching_return_items.quantity) as qty'))
            ->groupBy('stitching_returns.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | 5. Tarpai — sent & returned per design
         * -------------------------------------------------------------- */
        $tarpaiSent = DB::table('tarpai_send_items')
            ->join('tarpai_sends', 'tarpai_sends.id', '=', 'tarpai_send_items.tarpai_send_id')
            ->where('tarpai_sends.catalogue_id', $catId)
            ->select('tarpai_send_items.design_id', DB::raw('SUM(tarpai_send_items.quantity) as qty'))
            ->groupBy('tarpai_send_items.design_id')
            ->pluck('qty', 'design_id');

        $tarpaiReturned = DB::table('tarpai_return_items')
            ->join('tarpai_returns', 'tarpai_returns.id', '=', 'tarpai_return_items.tarpai_return_id')
            ->join('tarpai_sends', 'tarpai_sends.id', '=', 'tarpai_returns.tarpai_send_id')
            ->where('tarpai_sends.catalogue_id', $catId)
            ->select('tarpai_return_items.design_id', DB::raw('SUM(tarpai_return_items.quantity) as qty'))
            ->groupBy('tarpai_return_items.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | 6. Outsourced batch received per design
         * -------------------------------------------------------------- */
        $outsourcedReceived = DB::table('outsourced_batch_items')
            ->join('outsourced_batches', 'outsourced_batches.id', '=', 'outsourced_batch_items.outsourced_batch_id')
            ->where('outsourced_batches.catalogue_id', $catId)
            ->select('outsourced_batch_items.design_id', DB::raw('SUM(outsourced_batch_items.quantity) as qty'))
            ->groupBy('outsourced_batch_items.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | 7. Packed per design (press_return_items = pieces returned from press = packed)
         * -------------------------------------------------------------- */
        $packed = DB::table('press_return_items')
            ->join('press_returns', 'press_returns.id', '=', 'press_return_items.press_return_id')
            ->join('press_sends', 'press_sends.id', '=', 'press_returns.press_send_id')
            ->where('press_sends.catalogue_id', $catId)
            ->select('press_return_items.design_id', DB::raw('SUM(press_return_items.quantity) as qty'))
            ->groupBy('press_return_items.design_id')
            ->pluck('qty', 'design_id');

        /* ----------------------------------------------------------------
         | Build per-design rows
         * -------------------------------------------------------------- */
        $allDesigns = Catalogue::find($catId)
            ->designs()
            ->orderBy('sort_order')
            ->get();

        $designs = $allDesigns->map(function ($design) use (
            $fabricReceived, $outsourcedReceived, $assignmentDestination,
            $assigned, $npSent, $npReturned,
            $stitchingReturned, $tarpaiSent, $tarpaiReturned, $packed, $catalogue
        ) {
            $d           = $design->id;
            $isInHouse   = $design->manufacturing_type === 'in_house';
            $destination = $assignmentDestination[$d] ?? null; // 'naeem_pakki' or 'stitching_unit'

            $fabricQty      = (int) ($isInHouse ? ($fabricReceived[$d] ?? 0) : ($outsourcedReceived[$d] ?? 0));
            $assignedQty    = (int) ($assigned[$d] ?? 0);
            $npSentQty      = (int) ($npSent[$d] ?? 0);
            $npReturnedQty  = (int) ($npReturned[$d] ?? 0);
            $stitchedQty    = (int) ($stitchingReturned[$d] ?? 0);
            $tarpaiSentQty  = (int) ($tarpaiSent[$d] ?? 0);
            $tarpaiRetQty   = (int) ($tarpaiReturned[$d] ?? 0);
            $packedQty      = (int) ($packed[$d] ?? 0);

            // At Naeem Pakki = sent - returned (in-house, NP destination only)
            $atNaeemPakki = max(0, $npSentQty - $npReturnedQty);

            // Waiting at stitching unit
            //   NP path: pieces returned from NP that haven't been returned from stitching
            //   Direct path: assigned pieces that haven't been returned from stitching
            $readyForStitching = ($destination === 'naeem_pakki')
                ? $npReturnedQty
                : ($isInHouse ? $assignedQty : 0);
            $atStitching = max(0, $readyForStitching - $stitchedQty);

            // In factory (stitched, waiting for tarpai or pack)
            $inFactory = max(0, $stitchedQty - $tarpaiSentQty);
            // For outsourced: in factory = received - tarpaiSent (no stitching step)
            if (! $isInHouse) {
                $inFactory = max(0, $fabricQty - $tarpaiSentQty - $packedQty);
            }

            // At Tarpai = sent - returned
            $atTarpai = max(0, $tarpaiSentQty - $tarpaiRetQty);

            // Post-tarpai, not yet packed
            $postTarpai = max(0, $tarpaiRetQty - $packedQty);

            // Expected = catalogue's qty_per_design
            $expected = (int) $catalogue->qty_per_design;

            return (object) [
                'id'            => $design->id,
                'name'          => $design->name,
                'type'          => $design->manufacturing_type,
                'destination'   => $destination,
                'expected'      => $expected,
                'fabricQty'     => $fabricQty,
                'assignedQty'   => $assignedQty,
                'atNaeemPakki'  => $atNaeemPakki,
                'atStitching'   => $atStitching,
                'inFactory'     => $inFactory,
                'atTarpai'      => $atTarpai,
                'postTarpai'    => $postTarpai,
                'packedQty'     => $packedQty,
            ];
        });

        /* ----------------------------------------------------------------
         | Summary totals for stat cards
         * -------------------------------------------------------------- */
        $inHouseCount = $allDesigns->where('manufacturing_type', 'in_house')->count();

        $summary = (object) [
            'expectedTotal'  => (int) $catalogue->qty_per_design * $inHouseCount,
            'fabricTotal'    => $designs->sum('fabricQty'),
            'atNaeemPakki'   => $designs->sum('atNaeemPakki'),
            'atStitching'    => $designs->sum('atStitching'),
            'inFactory'      => $designs->sum('inFactory'),
            'atTarpai'       => $designs->sum('atTarpai'),
            'postTarpai'     => $designs->sum('postTarpai'),
            'packed'         => $designs->sum('packedQty'),
        ];

        return view('production.tracker.index', compact(
            'catalogues', 'catalogue', 'designs', 'summary'
        ));
    }
}
