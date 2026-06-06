<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use App\Models\BankAccount;

class Order extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'catalogue_id', 'assigned_bank_account_id', 'status',
        'submitted_name', 'submitted_city', 'submitted_email',
        'total_amount', 'total_paid', 'outstanding_balance',
        'is_flagged', 'notes', 'submitted_at', 'order_number',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Order $order) {
            if (empty($order->order_number)) {
                do {
                    $number = (string) random_int(100000, 999999);
                } while (static::where('order_number', $number)->exists());

                $order->order_number = $number;
            }
        });
    }

    protected $casts = [
        'total_amount'        => 'decimal:2',
        'total_paid'          => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'is_flagged'          => 'boolean',
        'submitted_at'        => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total_amount', 'total_paid', 'outstanding_balance'])
            ->logOnlyDirty();
    }

    // Status helpers
    public function isReceived(): bool            { return $this->status === 'received'; }
    public function isConfirmed(): bool           { return $this->status === 'confirmed'; }
    public function isStitching(): bool           { return $this->status === 'stitching'; }
    public function isDispatched(): bool          { return $this->status === 'dispatched'; }
    public function isPartiallyDispatched(): bool { return $this->status === 'partially_dispatched'; }
    public function isFlagged(): bool    { return $this->is_flagged; }

    public function canBeDispatched(): bool
    {
        return $this->outstanding_balance <= 0;
    }

    public function isFullyDispatched(): bool
    {
        // Check if all ordered quantities have been dispatched
        $totalOrdered   = $this->items->sum('total_qty');
        $totalDispatched = $this->dispatchBatches()
            ->join('dispatch_batch_items', 'dispatch_batches.id', '=', 'dispatch_batch_items.dispatch_batch_id')
            ->sum('dispatch_batch_items.quantity');

        return $totalOrdered > 0 && $totalDispatched >= $totalOrdered;
    }

    // Relationships
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function catalogue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reductions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderReduction::class);
    }

    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function assignedBankAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'assigned_bank_account_id');
    }

    public function dispatchBatches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DispatchBatch::class)->orderBy('batch_number');
    }
}
