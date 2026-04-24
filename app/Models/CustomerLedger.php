<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    protected $table = 'customer_ledger';

    protected $fillable = [
        'customer_id', 'transaction_type', 'amount', 'running_advance_balance',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'                  => 'decimal:2',
        'running_advance_balance' => 'decimal:2',
    ];

    // Ledger records are IMMUTABLE — never update or delete
    public static function boot(): void
    {
        parent::boot();
        static::updating(fn() => false); // prevent updates
        static::deleting(fn() => false); // prevent deletions
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Polymorphic reference
    public function reference(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('reference');
    }
}
