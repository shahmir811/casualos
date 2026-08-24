<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per broadcast (not per recipient) — the admin-facing record of
 * what's been sent, distinct from the framework `notifications` table which
 * stores one row per customer per send (see AnnouncementService).
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'image_paths', 'sent_by', 'sent_at', 'recipient_count'];

    protected $casts = [
        'sent_at'      => 'datetime',
        'image_paths'  => 'array',
    ];

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
