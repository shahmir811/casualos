<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
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
    public function send(
        string $title,
        string $body,
        array $imagePaths,
        ?User $sentBy,
        ?string $audioPath = null,
        ?string $audioOriginalFilename = null,
        ?int $audioFileSize = null,
    ): Announcement {
        $announcement = Announcement::create([
            'title'                    => $title,
            'body'                     => $body,
            'image_paths'              => $imagePaths,
            'audio_path'               => $audioPath,
            'audio_original_filename'  => $audioOriginalFilename,
            'audio_file_size'          => $audioFileSize,
            'sent_by'                  => $sentBy?->id,
            'sent_at'                  => now(),
            'recipient_count'          => Customer::count(),
        ]);

        foreach (Customer::all() as $customer) {
            AnnouncementRead::create([
                'announcement_id' => $announcement->id,
                'customer_id'     => $customer->id,
                'read_at'         => null,
            ]);

            $customer->notify(new AnnouncementNotification($title, $body, $imagePaths, $audioPath, $announcement->id));
        }

        return $announcement;
    }
}
