<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TarpaiPayment extends Model
{
    use LogsActivity;

    protected $fillable = [
        'catalogue_id', 'tarpai_house', 'week_start', 'week_end',
        'total_pieces_sent', 'total_amount',
        'is_confirmed', 'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'week_start'   => 'date',
        'week_end'     => 'date',
        'total_amount' => 'decimal:2',
        'is_confirmed' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['is_confirmed', 'confirmed_by', 'confirmed_at']);
    }

    public function catalogue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Catalogue::class);
    }

    public function confirmedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function houseLabel(): string
    {
        return match ($this->tarpai_house) {
            'rashid_bhai'  => 'Rashid Bhai',
            'yousaf_bhai'  => 'Yousaf Bhai',
            default        => $this->tarpai_house,
        };
    }

    public function houseBadgeClass(): string
    {
        return match ($this->tarpai_house) {
            'rashid_bhai'  => 'bg-purple-100 text-purple-700',
            'yousaf_bhai'  => 'bg-indigo-100 text-indigo-700',
            default        => 'bg-gray-100 text-gray-700',
        };
    }
}
