<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'design'       => [
                'id'        => $this->design?->id,
                'name'      => $this->design?->name,
                'photo_url' => $this->design?->photo ? Storage::url($this->design->photo) : null,
            ],
            'qty_xs'       => $this->qty_xs,
            'qty_s'        => $this->qty_s,
            'qty_m'        => $this->qty_m,
            'qty_l'        => $this->qty_l,
            'qty_xl'       => $this->qty_xl,
            'total_qty'    => $this->total_qty,
            'unit_price'   => $this->unit_price,
            'total_amount' => $this->total_amount,
        ];
    }
}
