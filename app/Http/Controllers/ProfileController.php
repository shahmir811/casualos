<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * ProfileController — all authenticated users.
 *
 * Allows a user to update their own name and password.
 * Email is read-only (cannot be changed from here).
 */
class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        // Password change is optional — only validate if provided
        if ($request->filled('password')) {
            $rules['current_password'] = ['required', 'string'];
            $rules['password']         = ['required', 'confirmed', Rules\Password::min(8)];
        }

        $validated = $request->validate($rules);

        // Verify current password if changing
        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'The current password is incorrect.'])
                    ->withInput();
            }
        }

        $user->name = $validated['name'];

        if ($request->filled('password')) {
            $user->password = Hash::make($validated['password']);
        }

        $nameChanged     = $user->isDirty('name');
        $passwordChanged = $request->filled('password');
        $oldName         = $user->getOriginal('name');

        $user->save();

        $props = [
            'user'  => $user->name,
            'email' => $user->email,
            'role'  => ucwords(str_replace('_', ' ', $user->role)),
        ];
        if ($nameChanged) {
            $props['name_changed'] = $oldName . ' → ' . $user->name;
        }
        if ($passwordChanged) {
            $props['password_changed'] = 'Yes (self-service)';
        }

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->event('detail')
            ->withProperties($props)
            ->log('User "' . $user->name . '" updated own profile' . ($passwordChanged ? ' (including password)' : ''));

        return back()->with('success', 'Profile updated successfully.');
    }
}
