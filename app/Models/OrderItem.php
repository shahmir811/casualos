<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'design_id',
        'qty_xs', 'qty_s', 'qty_m', 'qty_l', 'qty_xl',
        'unit_price', 'total_qty', 'total_amount',
    ];

    protected $casts = [
        'unit_price'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Auto-compute totals before saving
    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $item->total_qty    = $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl;
            $item->total_amount = $item->total_qty * $item->unit_price;
        });
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
