<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class AdvancePayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id', 'payment_type', 'amount', 'bank_account_id', 'payment_date', 'notes', 'receipt_image', 'logged_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'payment_date'  => 'date',
        'receipt_image' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
