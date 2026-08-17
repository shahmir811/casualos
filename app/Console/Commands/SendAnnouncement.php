<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Notifications\AnnouncementNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Minimal way to send a real announcement end to end while the CasualOS
 * admin compose UI (form, image upload, history view) is still separate
 * follow-up work. Loops every customer and calls Customer::notify(), which
 * both writes the in-app history row and pushes via Expo.
 */
class SendAnnouncement extends Command
{
    protected $signature = 'announcements:send
                            {title : Announcement title}
                            {body : Announcement body text}
                            {--image= : Local file path to an image to attach; uploaded to S3 under announcements/}';

    protected $description = 'Send an announcement to every customer (in-app history + Expo push) for manual end-to-end testing.';

    public function handle(): int
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

        $customers = Customer::all();

        if ($customers->isEmpty()) {
            $this->warn('No customers found — nothing sent.');

            return self::SUCCESS;
        }

        foreach ($customers as $customer) {
            $customer->notify(new AnnouncementNotification($title, $body, $imagePath));
        }

        $this->info("Queued announcement \"{$title}\" for {$customers->count()} customer(s).");
        $this->line('Note: QUEUE_CONNECTION=database, so delivery happens on the next queue:work tick (runs every minute via the scheduler) — not instantly.');

        return self::SUCCESS;
    }
}
