<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class OrderReduction extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id', 'reduced_by', 'reduction_date', 'adjustment_type',
        'original_total', 'new_total', 'adjustment_amount', 'notes', 'surplus_action',
    ];

    protected $casts = [
        'reduction_date'    => 'date',
        'original_total'    => 'decimal:2',
        'new_total'         => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reducedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reduced_by');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderReductionItem::class);
    }

    public function refund(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Refund::class, 'order_reduction_id');
    }
}
