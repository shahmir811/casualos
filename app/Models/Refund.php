<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'order_id', 'order_reduction_id', 'customer_id',
        'amount', 'refund_method', 'refund_reference', 'refund_document',
        'refund_date', 'notes', 'refunded_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'refund_date' => 'date',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reduction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderReduction::class, 'order_reduction_id');
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function refundedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }
}
