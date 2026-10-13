<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Unauthenticated counterpart to CatalogueSummaryResource — no
 * already_ordered (there's no customer to check against) and no pricing.
 */
class PublicCatalogueSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'status'            => $this->status,
            'cover_photo_url'   => $this->cover_photo ? Storage::url($this->cover_photo) : null,
            'number_of_designs' => $this->number_of_designs,
            'sold_out'          => $this->isSoldOut(),
        ];
    }
}
