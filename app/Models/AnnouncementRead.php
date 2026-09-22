<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (announcement, customer) — created with read_at=null for
 * every customer at send time (AnnouncementService::send()), filled in by
 * Api\AnnouncementController::markRead() the first time that customer opens
 * it. Backs the read-analytics stats page (AnnouncementController::show()).
 */
class AnnouncementRead extends Model
{
    protected $fillable = [
        'announcement_id', 'customer_id', 'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
