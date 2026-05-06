<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StitchingUnit extends Model
{
    protected $fillable = ['number', 'name', 'payment_type', 'per_piece_rate', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function isPerPiece(): bool { return $this->payment_type === 'per_piece'; }
    public function isSalary(): bool   { return $this->payment_type === 'salary'; }

    /** Returns the next available unit number (max existing + 1). */
    public static function nextNumber(): int
    {
        return ((int) static::max('number')) + 1;
    }

    public function label(): string
    {
        return "Unit {$this->number} — {$this->name}";
    }

    public function productionAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionAssignment::class);
    }

    public function stitchingReturns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StitchingReturn::class);
    }
}
