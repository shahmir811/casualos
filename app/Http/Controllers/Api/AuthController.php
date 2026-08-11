<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mobile-app authentication. Customers never have a password — a bearer
 * token is issued only after proving ownership of the permanent portal_token
 * link *and* confirming the email on file, the same two-factor check
 * CustomerPortalController::verify() does for the web PWA. That controller's
 * cookie + customer_devices flow is untouched; this is a parallel credential
 * store (Sanctum's personal_access_tokens) for the app only.
 */
class AuthController extends Controller
{
    /**
     * Accepts either a bare portal_token (UUID) or a full pasted portal link
     * (e.g. https://casualiteos.com/portal/{uuid}) — customers will naturally
     * copy the whole WhatsApp link rather than extracting the token
     * themselves, so every client is spared reimplementing that parsing.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'portal_token' => 'required|string',
            'email'        => 'required|email',
        ]);

        $token = Str::of($validated['portal_token'])->trim()->explode('/')->last();

        $customer = Customer::where('portal_token', $token)->first();

        if (! $customer || strtolower($customer->email) !== strtolower($validated['email'])) {
            return response()->json([
                'message' => 'We could not verify those details. Check your portal link and email address.',
            ], 422);
        }

        $apiToken = $customer->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token'    => $apiToken,
            'customer' => $this->customerPayload($customer),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'customer' => $this->customerPayload($request->user()),
        ]);
    }

    protected function customerPayload(Customer $customer): array
    {
        return [
            'id'      => $customer->id,
            'name'    => $customer->name,
            'email'   => $customer->email,
            'city'    => $customer->city,
            'country' => $customer->country,
        ];
    }
}
