<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * UserManagementController — Admin only.
 *
 * Rules (per proposal):
 * - Admin creates accounts for accountant, production_manager, creative_head
 * - Accounts can be enabled/disabled (never deleted)
 * - Only admin can reset passwords (no self-service)
 * - Disabled users cannot log in; their records stay intact
 */
class UserManagementController extends Controller
{
    public function index()
    {
        // Admin is included (previously excluded) so the admin's own
        // mobile_login_token can be copied from this screen too — see
        // resetPassword()'s isAdmin() guard below for why Disable/Reset
        // Password still can't touch that row even though it's now visible.
        $users = User::orderByRaw("role = 'admin' desc")
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'role'     => ['required', 'in:accountant,production_manager,creative_head'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)],
        ]);

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'role'       => $validated['role'],
            'password'   => Hash::make($validated['password']),
            'is_active'  => true,
            'created_by' => auth()->id(),
        ]);

        $user->assignRole($validated['role']);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('detail')
            ->withProperties([
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => ucwords(str_replace('_', ' ', $user->role)),
                'status'     => 'Active',
                'created_by' => auth()->user()->name,
            ])
            ->log("User account created: {$user->name} ({$user->role})");

        return redirect()->route('users.index')
            ->with('success', "Account created for {$user->name}.");
    }

    public function enable(User $user)
    {
        $user->update(['is_active' => true]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('detail')
            ->withProperties([
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => ucwords(str_replace('_', ' ', $user->role)),
                'status_changed' => 'disabled → active',
                'action_by'      => auth()->user()->name,
            ])
            ->log("User account re-enabled: {$user->name}");

        return back()->with('success', "{$user->name}'s account has been enabled.");
    }

    public function disable(User $user)
    {
        // Prevent disabling admin accounts
        if ($user->isAdmin()) {
            return back()->with('error', 'Admin accounts cannot be disabled.');
        }

        $user->update(['is_active' => false]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('detail')
            ->withProperties([
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => ucwords(str_replace('_', ' ', $user->role)),
                'status_changed' => 'active → disabled',
                'action_by'      => auth()->user()->name,
            ])
            ->log("User account disabled: {$user->name}");

        return back()->with('success', "{$user->name}'s account has been disabled.");
    }

    public function resetPassword(Request $request, User $user)
    {
        // Same protection disable() already has — the admin row is now
        // visible on this screen (for its mobile_login_token copy button),
        // but this form was built for admin to manage other staff, not itself.
        if ($user->isAdmin()) {
            return back()->with('error', 'Admin passwords cannot be reset from this screen.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event('detail')
            ->withProperties([
                'name'       => $user->name,
                'email'      => $user->email,
                'role'       => ucwords(str_replace('_', ' ', $user->role)),
                'reset_by'   => auth()->user()->name,
            ])
            ->log("Password reset for user: {$user->name}");

        return back()->with('success', "Password reset for {$user->name}.");
    }
}
