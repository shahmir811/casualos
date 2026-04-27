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
        'name', 'cover_photo', 'qty_per_design', 'number_of_designs',
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

    /**
     * Total actual pieces produced across all designs.
     * qty_per_design = 70, number_of_designs = 7 → total = 490
     */
    public function totalPieces(): int
    {
        return $this->qty_per_design * $this->number_of_designs;
    }

    public function isOpen(): bool   { return $this->status === 'open'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    /**
     * Pieces still available for ordering.
     *
     * Total production  = qty_per_design × number_of_designs
     * Total ordered     = sum of all order_item quantities across all designs
     * Available         = total production − total ordered
     */
    public function availablePieces(): int
    {
        $ordered = $this->orders()
            ->whereIn('status', ['received', 'confirmed', 'stitching', 'dispatched'])
            ->join('order_items', 'orders.id', '=', 'order_items.order_id')
            ->sum(\DB::raw('order_items.qty_xs + order_items.qty_s + order_items.qty_m + order_items.qty_l + order_items.qty_xl'));

        return max(0, $this->totalPieces() - (int) $ordered);
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
