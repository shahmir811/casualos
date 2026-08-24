<?php

namespace App\Notifications\Channels;

use App\Models\ExpoPushToken;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

/**
 * Delivers a notification to a customer's registered Expo push tokens
 * (native RN app), the same role WebPushChannel plays for the browser PWA.
 * Add this alongside WebPushChannel::class in a Notification's via() and
 * implement toExpoPush($notifiable): array — same convention as toWebPush().
 *
 * No-ops silently with zero registered tokens, mirroring WebPushChannel's
 * behaviour for a customer with no subscriptions.
 */
class ExpoPushChannel
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpoPush')) {
            return;
        }

        $tokens = $notifiable->expoPushTokens()->pluck('token')->values()->all();

        if (empty($tokens)) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);

        $messages = array_map(
            fn (string $token) => array_merge($message, ['to' => $token]),
            $tokens
        );

        $headers = [
            'Accept'          => 'application/json',
            'Accept-Encoding' => 'gzip, deflate',
            'Content-Type'    => 'application/json',
        ];

        if ($accessToken = config('services.expo.access_token')) {
            $headers['Authorization'] = "Bearer {$accessToken}";
        }

        $response = Http::withHeaders($headers)->post(self::ENDPOINT, $messages);

        $this->pruneInvalidTokens($tokens, $response);
    }

    /**
     * Expo's response 'data' array is positionally parallel to the request
     * array, so index $i of the response ticket corresponds to $tokens[$i].
     * A DeviceNotRegistered error means the app was uninstalled (or similar)
     * and Expo will never deliver to this token again — safe to delete.
     */
    protected function pruneInvalidTokens(array $tokens, Response $response): void
    {
        if (! $response->successful()) {
            return;
        }

        foreach ($response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                ExpoPushToken::where('token', $tokens[$index] ?? null)->delete();
            }
        }
    }
}
