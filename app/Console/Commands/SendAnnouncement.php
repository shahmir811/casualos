<?php

namespace App\Console\Commands;

use App\Services\AnnouncementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CLI entry point for sending an announcement, alongside the CasualOS admin
 * compose screen (AnnouncementController) — both call AnnouncementService,
 * which creates the broadcast record and notifies every customer.
 */
class SendAnnouncement extends Command
{
    protected $signature = 'announcements:send
                            {title : Announcement title}
                            {body : Announcement body text}
                            {--image= : Local file path to an image to attach; uploaded to S3 under announcements/}';

    protected $description = 'Send an announcement to every customer (in-app history + Expo push) from the command line.';

    public function handle(AnnouncementService $announcements): int
    {
        $title = $this->argument('title');
        $body  = $this->argument('body');
        $imagePath = null;

        if ($localPath = $this->option('image')) {
            if (! file_exists($localPath)) {
                $this->error("Image file not found: {$localPath}");

                return self::FAILURE;
            }

            $extension = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'jpg';
            $imagePath = 'announcements/' . Str::uuid() . '.' . $extension;
            Storage::disk('s3')->put($imagePath, file_get_contents($localPath));
        }

        $announcement = $announcements->send($title, $body, $imagePath, null);

        if ($announcement->recipient_count === 0) {
            $this->warn('No customers found — announcement recorded, but nothing was sent.');

            return self::SUCCESS;
        }

        $this->info("Queued announcement \"{$title}\" for {$announcement->recipient_count} customer(s).");
        $this->line('Note: QUEUE_CONNECTION=database, so delivery happens on the next queue:work tick (runs every minute via the scheduler) — not instantly.');

        return self::SUCCESS;
    }
}
