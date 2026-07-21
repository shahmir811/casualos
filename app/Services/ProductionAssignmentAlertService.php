<?php

namespace App\Services;

use App\Models\Design;
use App\Models\Order;
use App\Models\ProductionAlert;
use Illuminate\Support\Facades\DB;

/**
 * Flags designs whose fabric has already been fully committed to a production
 * assignment (Naeem Pakki or Stitching Unit) when a subsequent order change
 * — an Adjust Order, a Log Reduction, or the auto-cancellation it can trigger
 * — alters that design's size demand. At that point the size split baked into
 * the assignment can no longer be corrected from fabric (it's already been
 * cut/handed out), so the mismatch needs a human to look at it immediately
 * rather than being discovered later, by chance, on the Production Tracker.
 *
 * Uses the same "fully assigned" gate as ProductionTrackerController's
 * size-mismatch check (fabricQty > 0 && assignedQty >= fabricQty).
 */
class ProductionAssignmentAlertService
{
    private const REASON_LABELS = [
        'order_adjusted'  => 'adjusted',
        'order_reduced'   => 'reduced',
        'order_cancelled' => 'reduced to zero and cancelled',
    ];

    public function checkOrder(Order $order, array $designIds, string $trigger): void
    {
        $designIds = array_values(array_unique(array_filter($designIds)));
        if (empty($designIds)) {
            return;
        }

        $catalogueId = $order->catalogue_id;

        $inHouseDesignIds = Design::whereIn('id', $designIds)
            ->where('manufacturing_type', 'in_house')
            ->pluck('id');

        if ($inHouseDesignIds->isEmpty()) {
            return;
        }

        $fabricByDesign = DB::table('fabric_batch_items')
            ->join('fabric_batches', 'fabric_batches.id', '=', 'fabric_batch_items.fabric_batch_id')
            ->where('fabric_batches.catalogue_id', $catalogueId)
            ->whereIn('fabric_batch_items.design_id', $inHouseDesignIds)
            ->select('fabric_batch_items.design_id', DB::raw('SUM(fabric_batch_items.quantity) as qty'))
            ->groupBy('fabric_batch_items.design_id')
            ->pluck('qty', 'design_id');

        $assignedByDesign = DB::table('production_assignment_items')
            ->join('production_assignments', 'production_assignments.id', '=', 'production_assignment_items.production_assignment_id')
            ->where('production_assignments.catalogue_id', $catalogueId)
            ->whereIn('production_assignments.design_id', $inHouseDesignIds)
            ->select('production_assignments.design_id', DB::raw('SUM(production_assignment_items.quantity) as qty'))
            ->groupBy('production_assignments.design_id')
            ->pluck('qty', 'design_id');

        $designNames = Design::whereIn('id', $inHouseDesignIds)->pluck('name', 'id');
        $reason      = self::REASON_LABELS[$trigger] ?? $trigger;

        foreach ($inHouseDesignIds as $designId) {
            $fabricQty   = (int) ($fabricByDesign[$designId] ?? 0);
            $assignedQty = (int) ($assignedByDesign[$designId] ?? 0);

            if ($fabricQty === 0 || $assignedQty < $fabricQty) {
                continue; // fabric for this design isn't fully committed yet — no risk of a stale split
            }

            $alreadyFlagged = ProductionAlert::where('catalogue_id', $catalogueId)
                ->where('design_id', $designId)
                ->whereNull('resolved_at')
                ->exists();

            if ($alreadyFlagged) {
                continue; // an unresolved alert for this design is already surfaced — avoid piling up duplicates
            }

            $designName = $designNames[$designId] ?? "Design #{$designId}";

            ProductionAlert::create([
                'catalogue_id' => $catalogueId,
                'design_id'    => $designId,
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'trigger'      => $trigger,
                'message'      => "Order #{$order->order_number} was {$reason} after \"{$designName}\" was already fully assigned to production. "
                    . 'The size split committed to stitching may no longer match current order demand — recheck before pieces move further down the line.',
            ]);
        }
    }
}
