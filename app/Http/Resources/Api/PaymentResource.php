<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * payment_id ({order_number}p{sequence_number}) is computed here, never stored —
 * see rule 5.22 in CLAUDE.md. Requires the order relation to be loaded on the
 * parent Payment (whoever builds this resource must eager-load 'order').
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'payment_id'    => $this->order
                ? "{$this->order->order_number}p{$this->sequence_number}"
                : null,
            'payment_type'  => $this->payment_type,
            'amount'        => $this->amount,
            'payment_date'  => $this->payment_date,
            'bank_account'  => $this->bankAccount?->title,
            'notes'         => $this->notes,
        ];
    }
}
