<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderReductionItem extends Model
{
    protected $fillable = [
        'order_reduction_id', 'design_id', 'size', 'qty_reduced', 'unit_price', 'amount_reduced',
    ];

    protected $casts = [
        'unit_price'    => 'decimal:2',
        'amount_reduced'=> 'decimal:2',
    ];

    public function reduction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderReduction::class, 'order_reduction_id');
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
