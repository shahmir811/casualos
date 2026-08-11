<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'photo_url'      => $this->photo ? Storage::url($this->photo) : null,
            'selling_price'  => $this->selling_price,
            'discount_price' => $this->discount_price,
        ];
    }
}
