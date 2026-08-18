<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AnnouncementNotification;

/**
 * Shared side-effecting logic between the two ways an announcement gets
 * sent — the `announcements:send` artisan command and the CasualOS admin
 * compose screen — so the "create a broadcast record, notify every
 * customer" sequence lives once instead of twice.
 */
class AnnouncementService
{
    public function send(string $title, string $body, ?string $imagePath, ?User $sentBy): Announcement
    {
        $announcement = Announcement::create([
            'title'           => $title,
            'body'            => $body,
            'image_path'      => $imagePath,
            'sent_by'         => $sentBy?->id,
            'sent_at'         => now(),
            'recipient_count' => Customer::count(),
        ]);

        foreach (Customer::all() as $customer) {
            $customer->notify(new AnnouncementNotification($title, $body, $imagePath));
        }

        return $announcement;
    }
}
