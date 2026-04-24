<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access middleware.
 *
 * Usage in routes:
 *   ->middleware('role:admin')
 *   ->middleware('role:admin|accountant')
 *   ->middleware('role:manager')
 *
 * Checks the `role` column on the users table directly.
 * No external package needed.
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $userRole = Auth::user()->role;

        // Support pipe-separated roles: 'role:admin|accountant'
        $allowedRoles = collect($roles)
            ->flatMap(fn($r) => explode('|', $r))
            ->map(fn($r) => trim($r))
            ->toArray();

        if (! in_array($userRole, $allowedRoles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
