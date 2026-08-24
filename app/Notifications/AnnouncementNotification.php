<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A broadcast announcement from Casualite, sent to a customer via
 * $customer->notify(new self(...)). Delivered on two channels:
 *
 * - `database` — writes a row to the `notifications` table (migration
 *   2026_07_15_000002, added ahead of time specifically for this feed —
 *   see its docblock), backing the in-app announcement history the mobile
 *   app reads via GET /api/announcements.
 * - ExpoPushChannel — pushes to the customer's registered devices, same
 *   mechanism OrderStatusChanged uses for order-status pushes.
 *
 * No WebPushChannel here — announcements are a mobile-app-only feature
 * (Module 04), not surfaced on the customer portal PWA.
 */
class AnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly array $imagePaths = [],
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    /**
     * @return array{title: string, body: string, image_paths: array<int, string>}
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'       => $this->title,
            'body'        => $this->body,
            'image_paths' => $this->imagePaths,
        ];
    }

    /**
     * @return array{title: string, body: string, sound: string, data: array{type: string, announcement_id: ?string}}
     */
    public function toExpoPush(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'sound' => 'default',
            'data'  => [
                'type'            => 'announcement',
                // Set by ChannelManager before any channel runs — the same
                // uuid the `database` channel writes as the notifications
                // row id, so the app can deep-link straight to this
                // announcement instead of just opening the list.
                'announcement_id' => $this->id,
            ],
        ];
    }
}
