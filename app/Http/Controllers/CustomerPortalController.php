<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller
{
    // 400 days — Chrome's hard ceiling on Set-Cookie Max-Age. Reissued on every
    // successful validation, so this is a sliding window, not a hard expiry.
    private const DEVICE_COOKIE_NAME = 'portal_device';
    private const DEVICE_COOKIE_MINUTES = 400 * 24 * 60;

    public function show(Request $request, string $token)
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();

        $device = $this->resolveDevice($request, $customer);

        if (! $device) {
            return view('portal.show', compact('customer'));
        }

        $device->update(['last_seen_at' => now()]);
        Cookie::queue($this->deviceCookie($request, $request->cookie(self::DEVICE_COOKIE_NAME)));

        $customer->load(['orders.items.design', 'orders.catalogue', 'orders.payments', 'orders.dispatchBatches.items', 'ledger']);

        return view('portal.dashboard', compact('customer'));
    }

    public function verify(Request $request, string $token)
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();

        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        if (strtolower($customer->email) !== strtolower($validated['email'])) {
            return back()->with('error', 'Email address does not match our records.');
        }

        $rawToken = Str::random(64);

        CustomerDevice::create([
            'customer_id'  => $customer->id,
            'token_hash'   => hash('sha256', $rawToken),
            'user_agent'   => $request->userAgent(),
            'ip_address'   => $request->ip(),
            'last_seen_at' => now(),
        ]);

        Cookie::queue($this->deviceCookie($request, $rawToken));

        $customer->load(['orders.items.design', 'orders.catalogue', 'orders.payments', 'orders.dispatchBatches.items', 'ledger']);

        return view('portal.dashboard', compact('customer'));
    }

    // Manifest is generated per-customer (not a static public/manifest.json)
    // since start_url/scope must point at this customer's own portal URL.
    public function manifest(string $token)
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();
        $startUrl = route('portal.show', $customer->portal_token);
        // No trailing slash: start_url must be a prefix match within scope,
        // and this is a single-page app with nothing nested under this path.
        $scope    = $startUrl;

        return response()->json([
            'name'             => 'Casualite',
            'short_name'       => 'Casualite',
            'id'               => $startUrl,
            'start_url'        => $startUrl,
            'scope'            => $scope,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            // Black to match public/images/pwa/splash.png — the OS-generated
            // native splash (icon on this background_color) hands off to our
            // custom full-screen splash (portal.partials.pwa-splash) with no
            // color flash between the two.
            'background_color' => '#000000',
            'theme_color'      => '#1D1D1F',
            'icons'            => [
                ['src' => asset('images/pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('images/pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('images/pwa/icon-192-maskable.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('images/pwa/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    // Stores/updates the browser's push subscription for this customer. Only
    // reachable by an already-verified device — a customer who hasn't proven
    // ownership of the portal link shouldn't be able to register for pushes
    // about someone else's orders.
    public function pushSubscribe(Request $request, string $token)
    {
        $customer = Customer::where('portal_token', $token)->firstOrFail();

        if (! $this->resolveDevice($request, $customer)) {
            abort(403);
        }

        $validated = $request->validate([
            'endpoint'        => 'required|string',
            'keys.p256dh'     => 'required|string',
            'keys.auth'       => 'required|string',
        ]);

        $customer->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
        );

        return response()->json(['status' => 'subscribed']);
    }

    // Resolves the customer_devices row for the cookie on this request, scoped
    // to this specific $customer — a cookie verified against a different
    // customer's token on a shared device must not grant access here.
    private function resolveDevice(Request $request, Customer $customer): ?CustomerDevice
    {
        $rawToken = $request->cookie(self::DEVICE_COOKIE_NAME);

        if (! $rawToken) {
            return null;
        }

        return CustomerDevice::where('token_hash', hash('sha256', $rawToken))
            ->where('customer_id', $customer->id)
            ->first();
    }

    private function deviceCookie(Request $request, string $rawToken): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            self::DEVICE_COOKIE_NAME,
            $rawToken,
            self::DEVICE_COOKIE_MINUTES,
            '/portal',
            null,
            config('session.secure') ?? $request->secure(),
            true,
            false,
            'lax',
        );
    }
}
