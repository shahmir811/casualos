<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OutsourcedBatchItem;
use App\Models\PressReturnItem;

/**
 * Recommends how to split current packed inventory across pending orders so that
 * as much of it as possible gets dispatched — driving each design+size cell to
 * zero wherever there's enough combined demand to do so.
 *
 * Dispatch in this system is already partial per order (batch-wise, `partially_dispatched`
 * status), so this does NOT need to pick whole orders to fully ship — it can recommend
 * dispatching part of an order now and leaving the rest for a later batch. That makes
 * each design+size cell independent of every other cell: the most that can ever be
 * dispatched from a cell is min(available stock, combined demand for that cell), and
 * that maximum is reached simply by handing out the stock until either it or the demand
 * runs out. So unlike a whole-order selection (which is an NP-hard multidimensional
 * knapsack), this is a deterministic, always-maximal allocation — no search needed.
 *
 * When a cell's combined demand exceeds available stock, the remaining stock is handed
 * out oldest-order-first (by `submitted_at`) — a first-come-first-served assumption,
 * not a business rule confirmed elsewhere in the app.
 */
class DispatchOptimizerService
{
    private const SIZES = ['xs', 's', 'm', 'l', 'xl'];

    public function optimize(int $catalogueId): array
    {
        $stock  = $this->getAvailableStock($catalogueId);
        $orders = $this->getCandidateOrders($catalogueId);

        [$allocations, $remainingStock] = $this->allocate($orders, $stock);

        $recommended = collect($allocations)
            ->map(function ($demand, $idx) use ($orders) {
                return [
                    'order_id'           => $orders[$idx]['order_id'],
                    'order_number'       => $orders[$idx]['order_number'],
                    'customer_name'      => $orders[$idx]['customer_name'],
                    'demand'             => $demand,
                    'total_qty'          => array_sum(array_map('array_sum', $demand)),
                    'order_remaining_qty'=> $orders[$idx]['total_qty'],
                ];
            })
            ->filter(fn ($row) => $row['total_qty'] > 0)
            ->sortByDesc('total_qty')
            ->values();

        return [
            'stock'           => $stock,
            'remainingStock'  => $remainingStock,
            'consideredCount' => count($orders),
            'recommended'     => $recommended,
        ];
    }

    /**
     * Live packed inventory per design+size — press returns (in-house) and
     * outsourced batch items already reflect quantity net of past dispatches,
     * since DispatchController::store() decrements these rows directly.
     */
    private function getAvailableStock(int $catalogueId): array
    {
        $stock = [];

        $pressRows = PressReturnItem::whereHas('pressReturn.send', fn ($q) => $q->where('catalogue_id', $catalogueId))
            ->selectRaw('design_id, size, SUM(quantity) as total')
            ->groupBy('design_id', 'size')
            ->get();

        foreach ($pressRows as $row) {
            $stock[$row->design_id][$row->size] = ($stock[$row->design_id][$row->size] ?? 0) + (int) $row->total;
        }

        $outsourcedRows = OutsourcedBatchItem::whereHas('batch', fn ($q) => $q->where('catalogue_id', $catalogueId))
            ->selectRaw('design_id, size, SUM(quantity) as total')
            ->groupBy('design_id', 'size')
            ->get();

        foreach ($outsourcedRows as $row) {
            $stock[$row->design_id][$row->size] = ($stock[$row->design_id][$row->size] ?? 0) + (int) $row->total;
        }

        return $stock;
    }

    /**
     * Orders eligible for a recommendation: not already fully dispatched or cancelled,
     * and not blocked by an outstanding balance (rule 5.2 — DispatchController::store()
     * refuses these anyway, so recommending them would be unactionable). Demand is the
     * remaining (undispatched) quantity per design+size.
     */
    private function getCandidateOrders(int $catalogueId): array
    {
        $orders = Order::where('catalogue_id', $catalogueId)
            ->whereNotIn('status', ['dispatched', 'cancelled'])
            ->where('outstanding_balance', '<=', 0)
            ->with(['items', 'customer', 'dispatchBatches.items'])
            ->orderBy('submitted_at')
            ->get();

        $candidates = [];

        foreach ($orders as $order) {
            $dispatched = [];
            foreach ($order->dispatchBatches as $batch) {
                foreach ($batch->items as $item) {
                    $dispatched[$item->design_id][$item->size] =
                        ($dispatched[$item->design_id][$item->size] ?? 0) + $item->quantity;
                }
            }

            $demand   = [];
            $totalQty = 0;

            foreach ($order->items as $item) {
                foreach (self::SIZES as $size) {
                    $ordered   = (int) $item->{'qty_' . $size};
                    $already   = $dispatched[$item->design_id][$size] ?? 0;
                    $remaining = max(0, $ordered - $already);
                    if ($remaining > 0) {
                        $demand[$item->design_id][$size] = $remaining;
                        $totalQty += $remaining;
                    }
                }
            }

            if ($totalQty === 0) continue;

            $candidates[] = [
                'order_id'      => $order->id,
                'order_number'  => $order->order_number,
                'customer_name' => $order->customer->name ?? $order->submitted_name,
                'submitted_at'  => $order->submitted_at,
                'demand'        => $demand,
                'total_qty'     => $totalQty,
            ];
        }

        return $candidates;
    }

    /**
     * For every design+size cell, hand out available stock to the orders that need it,
     * oldest order first, until either the stock or the demand for that cell is used up.
     *
     * @return array{0: array<int, array>, 1: array} [allocations keyed by order index, remaining stock]
     */
    private function allocate(array $orders, array $stock): array
    {
        $cells = [];
        foreach ($orders as $idx => $order) {
            foreach ($order['demand'] as $designId => $sizes) {
                foreach ($sizes as $size => $qty) {
                    $cells[$designId][$size][] = ['idx' => $idx, 'qty' => $qty];
                }
            }
        }

        $allocations    = [];
        $remainingStock = $stock;

        foreach ($cells as $designId => $sizeGroups) {
            foreach ($sizeGroups as $size => $claims) {
                usort($claims, fn ($a, $b) => $orders[$a['idx']]['submitted_at'] <=> $orders[$b['idx']]['submitted_at']);

                $available = $remainingStock[$designId][$size] ?? 0;

                foreach ($claims as $claim) {
                    if ($available <= 0) break;

                    $give = min($available, $claim['qty']);
                    if ($give > 0) {
                        $allocations[$claim['idx']][$designId][$size] = $give;
                        $available -= $give;
                    }
                }

                $remainingStock[$designId][$size] = $available;
            }
        }

        return [$allocations, $remainingStock];
    }
}
