<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Wage extends Model
{
    use LogsActivity;

    protected $fillable = [
        'catalogue_id', 'week_start', 'week_end',
        'total_suits_stitched', 'wage_rate', 'total_wages',
        'is_confirmed', 'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'week_start'          => 'date',
        'week_end'            => 'date',
        'wage_rate'           => 'decimal:2',
        'total_wages'         => 'decimal:2',
        'is_confirmed'        => 'boolean',
        'confirmed_at'        => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['is_confirmed', 'confirmed_by', 'confirmed_at']);
    }

    // Auto-compute total_wages before saving
    protected static function booted(): void
    {
        static::saving(function (Wage $wage) {
            $wage->total_wages = $wage->total_suits_stitched * $wage->wage_rate;
        });
    }

    public function catalogue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function confirmedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
