<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Expects the controller to have set an `already_ordered` attribute on each
 * Catalogue instance before building this resource — computed once as a
 * batch query, not per-row, to avoid an N+1 across the list.
 */
class CatalogueSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'cover_photo_url'    => $this->cover_photo ? Storage::url($this->cover_photo) : null,
            'quantity_benchmark' => $this->quantity_benchmark,
            'qty_per_design'     => $this->qty_per_design,
            'number_of_designs'  => $this->number_of_designs,
            'total_pieces'       => $this->totalPieces(),
            'available_pieces'   => $this->availablePieces(),
            'sold_out'           => $this->isSoldOut(),
            'already_ordered'    => (bool) $this->already_ordered,
        ];
    }
}
