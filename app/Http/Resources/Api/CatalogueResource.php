<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Expects 'designs' eager-loaded and an `already_ordered` attribute set on
 * the Catalogue instance by the controller (same convention as
 * CatalogueSummaryResource).
 */
class CatalogueResource extends JsonResource
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
            'designs'            => DesignResource::collection($this->designs),
        ];
    }
}
