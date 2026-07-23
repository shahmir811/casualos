<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionAlert extends Model
{
    protected $fillable = [
        'catalogue_id', 'design_id', 'order_id', 'order_number',
        'trigger', 'message', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
