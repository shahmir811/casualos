<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: track-app-usage
 *
 * Refreshes Customer::app_last_seen_at (and app_platform, if sent) on every
 * authenticated mobile-app request — not just at login. The Sanctum token
 * issued by Api\AuthController::verify() persists indefinitely, so a
 * customer may only call verify() once and then reuse the same token for
 * months; anchoring "last seen" solely to that one-time login would go
 * stale immediately. The app is expected to send the X-App-Platform header
 * (ios|android) on every request; this middleware is a no-op if it's
 * missing or the authenticated user isn't a Customer (this route group is
 * customer-only today, but the check keeps this middleware safe to reuse
 * elsewhere without assuming that).
 */
class TrackCustomerAppUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Customer) {
            $platform = $request->header('X-App-Platform');

            $update = ['app_last_seen_at' => now()];

            if (in_array($platform, ['ios', 'android'], true)) {
                $update['app_platform'] = $platform;
            }

            $user->update($update);
        }

        return $next($request);
    }
}
