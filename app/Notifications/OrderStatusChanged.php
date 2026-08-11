<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    private const CONTENT = [
        'confirmed' => [
            'title' => 'Order Confirmed',
            'body'  => 'Your order #%s has been confirmed and is now in production.',
        ],
        'stitching' => [
            'title' => 'Stitching Started',
            'body'  => 'Your order #%s is now being stitched.',
        ],
        'partially_dispatched' => [
            'title' => 'Part of Your Order Shipped',
            'body'  => 'Some items from order #%s have been dispatched.',
        ],
        'dispatched' => [
            'title' => 'Order Dispatched',
            'body'  => 'Your order #%s is on its way.',
        ],
        'cancelled' => [
            'title' => 'Order Cancelled',
            'body'  => 'Order #%s has been cancelled.',
        ],
    ];

    public function __construct(
        private readonly Order $order,
        private readonly string $newStatus,
    ) {}

    /** @return string[] The only values notify() accepts as $newStatus. */
    public static function statuses(): array
    {
        return array_keys(self::CONTENT);
    }

    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class, ExpoPushChannel::class];
    }

    public function toWebPush(mixed $notifiable, mixed $notification): WebPushMessage
    {
        $content = self::CONTENT[$this->newStatus];
        $iconUrl = asset('images/pwa/icon-192.png');

        return (new WebPushMessage())
            ->title($content['title'])
            ->icon($iconUrl)
            ->badge($iconUrl)
            ->body(sprintf($content['body'], $this->order->order_number))
            ->data(['url' => route('portal.show', $this->order->customer->portal_token) . '#order-' . $this->order->id]);
    }

    /**
     * @return array{title: string, body: string, sound: string, data: array}
     */
    public function toExpoPush(mixed $notifiable): array
    {
        $content = self::CONTENT[$this->newStatus];

        return [
            'title' => $content['title'],
            'body'  => sprintf($content['body'], $this->order->order_number),
            'sound' => 'default',
            'data'  => [
                'order_id'     => $this->order->id,
                'order_number' => $this->order->order_number,
            ],
        ];
    }
}
