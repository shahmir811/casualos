<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Catalogue extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name', 'cover_photo', 'total_pieces', 'number_of_designs',
        'wage_rate', 'notes', 'status', 'order_token', 'created_by',
    ];

    // Auto-generate order_token on creation
    protected static function booted(): void
    {
        static::creating(function (Catalogue $catalogue) {
            $catalogue->order_token = Str::random(32);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty();
    }

    // Computed
    public function getPiecesPerDesignAttribute(): int
    {
        return $this->number_of_designs > 0
            ? (int) ($this->total_pieces / $this->number_of_designs)
            : 0;
    }

    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    /**
     * Total pieces still available for ordering.
     * Calculated: total_pieces - sum of all order_items quantities for this catalogue.
     */
    public function availablePieces(): int
    {
        $ordered = $this->orders()
            ->whereIn('status', ['received', 'confirmed', 'stitching', 'dispatched'])
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->sum(\DB::raw('order_items.qty_xs + order_items.qty_s + order_items.qty_m + order_items.qty_l + order_items.qty_xl'));

        return max(0, $this->total_pieces - (int) $ordered);
    }

    // Relationships
    public function designs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Design::class)->orderBy('sort_order');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function fabricBatches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FabricBatch::class);
    }

    public function wages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Wage::class);
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
