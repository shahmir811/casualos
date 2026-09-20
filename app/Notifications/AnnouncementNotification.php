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
        private readonly ?string $audioPath = null,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    /**
     * @return array{title: string, body: string, image_paths: array<int, string>, audio_path: ?string}
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title'       => $this->title,
            'body'        => $this->body,
            'image_paths' => $this->imagePaths,
            'audio_path'  => $this->audioPath,
        ];
    }

    /**
     * @return array{title: string, body: string, badge: int, data: array{type: string, announcement_id: ?string, has_audio: bool}}
     */
    public function toExpoPush(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            // A push notification is text-only — there's nothing to play from
            // the OS notification itself, so a fixed indicator is prefixed
            // onto the body whenever a voice note is attached, and the
            // structured `has_audio` flag below lets the app render its own
            // mic icon without parsing this string.
            'body'  => $this->audioPath ? "🎤 Voice message · {$this->body}" : $this->body,
            // No 'sound' here — ExpoPushChannel::send() sets it per-token
            // based on platform, since iOS and Android need different
            // bundled sound filenames (see that class's docblock).
            // APNs needs an absolute count to update the icon while the app
            // is closed. Database and push channels run as separate jobs.
            'badge' => $this->unreadBadgeCount($notifiable),
            'data'  => [
                'type'            => 'announcement',
                // Set by ChannelManager before any channel runs — the same
                // uuid the `database` channel writes as the notifications
                // row id, so the app can deep-link straight to this
                // announcement instead of just opening the list.
                'announcement_id' => $this->id,
                'has_audio'       => $this->audioPath !== null,
            ],
        ];
    }

    private function unreadBadgeCount(mixed $notifiable): int
    {
        // Read one snapshot, including read rows so a delayed push for an
        // already-read announcement does not add it back to the badge.
        $announcements = $notifiable->notifications()
            ->where('type', self::class)
            ->get(['id', 'read_at']);

        $unread = $announcements->whereNull('read_at')->count();
        $pending = $this->id === null || ! $announcements->contains('id', $this->id);

        return $unread + ($pending ? 1 : 0);
    }

}
