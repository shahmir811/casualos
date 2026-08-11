<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'order_number'         => $this->order_number,
            'status'               => $this->status,
            'catalogue'            => [
                'id'   => $this->catalogue?->id,
                'name' => $this->catalogue?->name,
            ],
            'total_pieces'         => (int) $this->items->sum('total_qty'),
            'total_amount'         => $this->total_amount,
            'total_paid'           => $this->total_paid,
            'outstanding_balance'  => $this->outstanding_balance,
            'is_fully_dispatched'  => $this->isFullyDispatched(),
            'created_at'           => $this->created_at,
        ];
    }
}
