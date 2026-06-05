<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    protected $fillable = [
        'job_name', 'job_label', 'triggered_by',
        'week_start', 'week_end',
        'records_created', 'records_updated', 'records_skipped',
        'status', 'output', 'ran_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end'   => 'date',
        'ran_at'     => 'datetime',
    ];

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'success' => 'bg-green-100 text-green-700',
            'failed'  => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-700',
        };
    }
}
