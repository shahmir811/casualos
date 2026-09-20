<?php

namespace App\Notifications\Channels;

use App\Models\ExpoPushToken;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * iOS's locked-screen/background notification sound player is far
     * stricter than the in-app decoder and silently drops a plain .wav —
     * Apple's own guidance is a .caf built with afconvert. Android has no
     * such issue with .wav and, unlike iOS, can't be given the same
     * basename as the .caf (the expo-notifications config plugin would
     * collide on the same Android raw-resource name), so the two platforms
     * carry genuinely different bundled filenames — see casualite-app's
     * app.json expo-notifications "sounds" list, which bundles both.
     */
    private const SOUND_IOS     = 'casualite_notification_ios.caf';
    private const SOUND_ANDROID = 'casualite_notification_01.wav';

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toExpoPush')) {
            return;
        }

        $tokens = $notifiable->expoPushTokens()->get(['id', 'token', 'platform']);

        if ($tokens->isEmpty()) {
            return;
        }

        $message = $notification->toExpoPush($notifiable);

        $messages = $tokens
            ->map(fn (ExpoPushToken $expoPushToken) => array_merge($message, [
                'to'    => $expoPushToken->token,
                'sound' => $expoPushToken->platform === 'ios' ? self::SOUND_IOS : self::SOUND_ANDROID,
            ]))
            ->values()
            ->all();

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
    protected function pruneInvalidTokens(Collection $tokens, Response $response): void
    {
        if (! $response->successful()) {
            return;
        }

        foreach ($response->json('data', []) as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                $tokens->get($index)?->delete();
            }
        }
    }
}
