<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostEstimationItem extends Model
{
    protected $fillable = [
        'cost_estimation_id', 'category', 'particulars', 'avg', 'qty', 'rate', 'amount',
    ];

    protected $casts = [
        'avg'    => 'decimal:2',
        'qty'    => 'decimal:2',
        'rate'   => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function costEstimation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CostEstimation::class);
    }
}
