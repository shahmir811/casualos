<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full order detail — mirrors the expanded card on the customer portal
 * dashboard (portal/dashboard.blade.php). Expects 'items.design', 'payments.order',
 * 'payments.bankAccount', 'dispatchBatches.items.design', and 'reductions.items'
 * (for netSizeQty()) to be eager-loaded by the caller.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'order_number'        => $this->order_number,
            'status'              => $this->status,
            'catalogue'           => [
                'id'             => $this->catalogue?->id,
                'name'           => $this->catalogue?->name,
                'hd_gallery_url' => $this->catalogue?->hd_gallery_token
                    ? route('gallery.show', $this->catalogue->hd_gallery_token)
                    : null,
            ],
            'total_amount'        => $this->total_amount,
            'total_paid'          => $this->total_paid,
            'outstanding_balance' => $this->outstanding_balance,
            'is_fully_dispatched' => $this->isFullyDispatched(),
            'can_be_dispatched'   => $this->canBeDispatched(),
            'size_breakdown'      => [
                'xs' => $this->netSizeQty('xs'),
                's'  => $this->netSizeQty('s'),
                'm'  => $this->netSizeQty('m'),
                'l'  => $this->netSizeQty('l'),
                'xl' => $this->netSizeQty('xl'),
            ],
            'total_pieces'        => (int) $this->items->sum('total_qty'),
            'created_at'          => $this->created_at,
            'items'               => OrderItemResource::collection($this->items),
            'payments'            => PaymentResource::collection($this->payments),
            'dispatch_batches'    => DispatchBatchResource::collection($this->dispatchBatches),
        ];
    }
}
