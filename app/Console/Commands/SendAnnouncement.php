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
                            {--image=* : Local file path to an image to attach; repeat --image for multiple; each is uploaded to S3 under announcements/}
                            {--audio= : Local file path to a voice note to attach; uploaded to S3 under announcements/}';

    protected $description = 'Send an announcement to every customer (in-app history + Expo push) from the command line.';

    public function handle(AnnouncementService $announcements): int
    {
        $title = $this->argument('title');
        $body  = $this->argument('body');
        $imagePaths = [];

        foreach ($this->option('image') as $localPath) {
            if (! file_exists($localPath)) {
                $this->error("Image file not found: {$localPath}");

                return self::FAILURE;
            }

            $extension = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'jpg';
            $path = 'announcements/' . Str::uuid() . '.' . $extension;
            Storage::disk('s3')->put($path, file_get_contents($localPath));
            $imagePaths[] = $path;
        }

        $audioPath = null;
        $audioOriginalFilename = null;
        $audioFileSize = null;

        if ($localAudioPath = $this->option('audio')) {
            if (! file_exists($localAudioPath)) {
                $this->error("Audio file not found: {$localAudioPath}");

                return self::FAILURE;
            }

            $extension = pathinfo($localAudioPath, PATHINFO_EXTENSION) ?: 'm4a';
            $audioPath = 'announcements/' . Str::uuid() . '.' . $extension;
            Storage::disk('s3')->put($audioPath, file_get_contents($localAudioPath));
            $audioOriginalFilename = basename($localAudioPath);
            $audioFileSize = filesize($localAudioPath);
        }

        $announcement = $announcements->send(
            $title, $body, $imagePaths, null,
            $audioPath, $audioOriginalFilename, $audioFileSize,
        );

        if ($announcement->recipient_count === 0) {
            $this->warn('No customers found — announcement recorded, but nothing was sent.');

            return self::SUCCESS;
        }

        $this->info("Queued announcement \"{$title}\" for {$announcement->recipient_count} customer(s).");
        $this->line('Note: QUEUE_CONNECTION=database, so delivery happens on the next queue:work tick (runs every minute via the scheduler) — not instantly.');

        return self::SUCCESS;
    }
}
