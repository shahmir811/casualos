<?php

namespace App\Http\Controllers;

use App\Models\StaffMobileLoginToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Consumes the single-use handoff token minted by Api\AuthController::verify()
 * for a staff member signing into the mobile app, and starts a real Laravel
 * web session — the mobile app then shows this in an embedded WebView, so
 * everything from here on (screen visibility per role, etc.) is governed by
 * the same Spatie role middleware already applied throughout routes/web.php.
 */
class MobileLoginController extends Controller
{
    public function consume(Request $request, string $token)
    {
        $hash = hash('sha256', $token);

        return DB::transaction(function () use ($request, $hash) {
            $record = StaffMobileLoginToken::where('token_hash', $hash)->lockForUpdate()->first();

            if (! $record || $record->used_at || $record->expires_at->isPast()) {
                return redirect()->route('login')->with('error', 'This sign-in link is invalid or has expired.');
            }

            // Consume immediately, before touching Auth, so the token can never
            // be replayed even if something below fails.
            $record->update(['used_at' => now()]);

            $user = $record->user;

            if (! $user || ! $user->is_active) {
                return redirect()->route('login')->with('error', 'Your account has been disabled. Contact the Admin.');
            }

            Auth::login($user);
            $request->session()->regenerate();
            $user->update(['last_login_at' => now()]);

            return redirect()->intended(route('dashboard'));
        });
    }
}
