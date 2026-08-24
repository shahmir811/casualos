<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DispatchBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'batch_number'       => $this->batch_number,
            'dispatch_date'      => $this->dispatch_date,
            'shipping_address'   => $this->shipping_address,
            'cargo_document_url' => $this->cargo_document ? Storage::url($this->cargo_document) : null,
            'total_pieces'       => $this->totalPieces(),
            'items'              => $this->items->map(fn ($item) => [
                'design'   => $item->design?->name,
                'size'     => $item->size,
                'quantity' => $item->quantity,
            ]),
        ];
    }
}
