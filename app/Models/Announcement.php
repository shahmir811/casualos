<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per broadcast (not per recipient) — the admin-facing record of
 * what's been sent, distinct from the framework `notifications` table which
 * stores one row per customer per send (see AnnouncementService).
 */
class Announcement extends Model
{
    protected $fillable = [
        'title', 'body', 'image_paths',
        'audio_path', 'audio_original_filename', 'audio_file_size',
        'sent_by', 'sent_at', 'recipient_count',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'image_paths'  => 'array',
    ];

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * One row per customer this announcement was sent to (see
     * AnnouncementService::send()) — empty for announcements sent before
     * read analytics was added, which AnnouncementController::show()
     * surfaces as "not tracked" rather than a fabricated 0/recipient_count.
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }
}
