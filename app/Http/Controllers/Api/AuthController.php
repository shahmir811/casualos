<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\StaffMobileLoginToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Mobile-app authentication. Customers never have a password — a bearer
 * token is issued only after proving ownership of the permanent portal_token
 * link *and* confirming the email on file, the same two-factor check
 * CustomerPortalController::verify() does for the web PWA. That controller's
 * cookie + customer_devices flow is untouched; this is a parallel credential
 * store (Sanctum's personal_access_tokens) for the app only.
 *
 * Staff (admin/accountant/production_manager/creative_head) authenticate
 * through this same endpoint using their own permanent User::mobile_login_token
 * + email, but never receive a Sanctum bearer token — they never call another
 * /api/* endpoint. Instead a single-use, short-lived handoff token
 * (StaffMobileLoginToken) is minted so the app can open an embedded WebView
 * at MobileLoginController::consume(), which starts a real Laravel web
 * session. The existing Spatie role middleware on routes/web.php then governs
 * exactly what that staff member can see, with no new permission logic here.
 */
class AuthController extends Controller
{
    /**
     * Accepts either a bare portal_token/mobile_login_token (UUID) or a full
     * pasted link (e.g. https://casualiteos.com/portal/{uuid}) — customers
     * and staff will naturally copy the whole shared link rather than
     * extracting the token themselves, so every client is spared
     * reimplementing that parsing.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'portal_token' => 'required|string',
            'email'        => 'required|email',
        ]);

        $token = Str::of($validated['portal_token'])->trim()->explode('/')->last();

        $customer = Customer::where('portal_token', $token)->first();

        if ($customer && strtolower($customer->email) === strtolower($validated['email'])) {
            $apiToken = $customer->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'account_type' => 'customer',
                'token'        => $apiToken,
                'customer'     => $this->customerPayload($customer),
            ]);
        }

        $user = User::where('mobile_login_token', $token)->first();

        if ($user && strtolower($user->email) === strtolower($validated['email']) && $user->is_active) {
            return response()->json([
                'account_type' => 'staff',
                'redirect_url' => $this->buildStaffRedirectUrl($user, $request),
            ]);
        }

        // Same vague message regardless of which branch almost matched — never
        // leak whether a token belongs to a customer, a staff account, or a
        // disabled staff account.
        return response()->json([
            'message' => 'We could not verify those details. Check your portal link and email address.',
        ], 422);
    }

    /**
     * Mints a single-use handoff token and returns the URL the app's
     * embedded WebView should open. Only a hash of the raw token is ever
     * stored — see StaffMobileLoginToken / MobileLoginController::consume().
     */
    protected function buildStaffRedirectUrl(User $user, Request $request): string
    {
        $rawToken = Str::random(64);

        StaffMobileLoginToken::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addSeconds(config('casualite.staff_mobile_login_token_ttl')),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return rtrim(config('casualite.web_app_url'), '/') . '/mobile-login/' . $rawToken;
    }

    public function me(Request $request)
    {
        return response()->json([
            'customer' => $this->customerPayload($request->user()),
        ]);
    }

    /**
     * Revokes only the token used for this request, not every session the
     * customer has — signing out on one device shouldn't sign them out
     * everywhere.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
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
