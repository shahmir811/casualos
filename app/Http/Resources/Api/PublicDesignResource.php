<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Same as DesignResource but for unauthenticated browsing (see
 * CatalogueController::publicShow) — deliberately omits selling_price and
 * discount_price. Pricing stays a sign-in perk; browsing doesn't.
 */
class PublicDesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'photo_url' => $this->photo ? Storage::url($this->photo) : null,
        ];
    }
}
