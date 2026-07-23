<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Payment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'customer_id', 'order_id', 'sequence_number', 'payment_type', 'bank_account_id', 'title_given', 'amount', 'payment_date', 'notes', 'receipt_image', 'logged_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'payment_date'  => 'date',
        'receipt_image' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        // 'order_number' isn't a real column — it's the virtual accessor below.
        // Adding it alongside '*' snapshots the parent order_number straight into
        // this log entry's own attribute_changes, since Payment's real columns only
        // hold order_id (a raw FK) and the parent Order row may itself be gone by
        // the time anyone reads this log (e.g. Delete Order removes both).
        return LogOptions::defaults()->logOnly(['*', 'order_number']);
    }

    public function getOrderNumberAttribute(): ?string
    {
        return $this->order?->order_number;
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        if ($eventName !== 'deleted') {
            return $eventName;
        }

        $order       = $this->order;
        $orderNumber = $order?->order_number;

        $description = $orderNumber
            ? "Payment #{$orderNumber}p{$this->sequence_number} on the Order #{$orderNumber}"
            : "Payment #{$this->id}";

        if ($catalogueName = $order?->catalogue?->name) {
            $description .= " on the {$catalogueName} catalogue";
        }

        if ($customerName = $order?->customer?->name) {
            $description .= " from {$customerName}";
        }

        return $description . ' has been deleted';
    }

    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function bankAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
