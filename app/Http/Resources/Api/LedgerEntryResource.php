<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * $this->resource is expected to be a plain array built by
 * Api\LedgerController::index() (entry fields + resolved order_number/payment_id),
 * not the raw CustomerLedger model — reference resolution needs a batch lookup
 * across Order/Payment/OrderReduction/Refund that the controller does once for
 * the whole list, mirroring LedgerController::show()'s $orderMap pattern.
 */
class LedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->resource['id'],
            'transaction_type'        => $this->resource['transaction_type'],
            'amount'                  => $this->resource['amount'],
            'running_advance_balance' => $this->resource['running_advance_balance'],
            'order_number'            => $this->resource['order_number'],
            'payment_id'              => $this->resource['payment_id'],
            'created_at'              => $this->resource['created_at'],
        ];
    }
}
