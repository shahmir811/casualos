<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NaeemPakkiReturn extends Model
{
    protected $fillable = ['production_assignment_id', 'return_date', 'logged_by'];

    protected $casts = ['return_date' => 'date'];

    public function assignment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductionAssignment::class, 'production_assignment_id');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NaeemPakkiReturnItem::class);
    }

    public function loggedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function totalPieces(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
