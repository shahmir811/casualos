<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DispatchBatch extends Model
{
    use LogsActivity;

    protected $fillable = [
        'order_id', 'batch_number', 'dispatch_date',
        'shipping_address', 'cargo_document', 'logged_by',
    ];

    protected $casts = ['dispatch_date' => 'date'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DispatchBatchItem::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function totalPieces(): int
    {
        return $this->items->sum('quantity');
    }
}
