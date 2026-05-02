@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div class="max-w-4xl mx-auto">

    {{-- Page header --}}
    <div class="mb-6">
        <h2 class="text-[#1D1D1F] font-semibold text-lg">My Profile</h2>
        <p class="text-[#6E6E73] text-sm mt-0.5">Manage your display name and account password.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── LEFT COLUMN: identity card ─────────────────────────── --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Avatar + name card --}}
            <div class="card p-6 text-center">
                <div class="w-20 h-20 rounded-full bg-[#0071E3] flex items-center justify-center text-white text-3xl font-semibold mx-auto mb-4">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                <p class="text-[#1D1D1F] font-semibold text-base">{{ $user->name }}</p>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mt-1">{{ ucfirst($user->role) }}</p>
            </div>

            {{-- Account details read-only summary --}}
            <div class="card p-6 space-y-4">
                <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest">Account Details</p>

                <div class="flex flex-col gap-0.5">
                    <span class="text-[10px] font-medium text-[#86868B] uppercase tracking-wide">Email</span>
                    <span class="text-[#1D1D1F] text-sm font-medium break-all">{{ $user->email }}</span>
                </div>

                <div class="flex flex-col gap-0.5">
                    <span class="text-[10px] font-medium text-[#86868B] uppercase tracking-wide">Role</span>
                    <span class="text-[#1D1D1F] text-sm font-medium">{{ ucfirst($user->role) }}</span>
                </div>

                <div class="flex flex-col gap-0.5">
                    <span class="text-[10px] font-medium text-[#86868B] uppercase tracking-wide">Account Since</span>
                    <span class="text-[#1D1D1F] text-sm font-medium">{{ $user->created_at->format('d M Y') }}</span>
                </div>
            </div>

        </div>

        {{-- ── RIGHT COLUMN: edit form ─────────────────────────────── --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                {{-- Account Information --}}
                <div class="card p-6 space-y-5">
                    <div class="border-b border-[#F2F2F7] pb-4">
                        <h3 class="text-[#1D1D1F] font-semibold text-sm">Account Information</h3>
                        <p class="text-[#86868B] text-xs mt-0.5">Update your display name. Email cannot be changed.</p>
                    </div>

                    {{-- Name --}}
                    <div>
                        <label for="name" class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Full Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name', $user->name) }}"
                            required
                            autofocus
                            class="apple-input"
                            placeholder="Your full name"
                        >
                        @error('name')
                            <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email (read-only) --}}
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <label for="email" class="text-[#1D1D1F] text-sm font-medium">Email Address</label>
                            <span class="text-[10px] font-semibold text-[#86868B] uppercase tracking-wide bg-[#F5F5F7] border border-[#E8E8ED] px-1.5 py-0.5 rounded">
                                Read-only
                            </span>
                        </div>
                        <input
                            type="email"
                            id="email"
                            value="{{ $user->email }}"
                            readonly
                            tabindex="-1"
                            class="apple-input"
                            style="background:#F5F5F7; color:#86868B; cursor:not-allowed; user-select:none;"
                        >
                        <p class="mt-1 text-[#86868B] text-xs">Contact the system admin to change your email address.</p>
                    </div>
                </div>

                {{-- Change Password --}}
                <div class="card p-6 space-y-5">
                    <div class="border-b border-[#F2F2F7] pb-4">
                        <h3 class="text-[#1D1D1F] font-semibold text-sm">Change Password</h3>
                        <p class="text-[#86868B] text-xs mt-0.5">Leave all password fields blank to keep your current password.</p>
                    </div>

                    {{-- Current password --}}
                    <div>
                        <label for="current_password" class="block text-[#1D1D1F] text-sm font-medium mb-1.5">
                            Current Password
                        </label>
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            class="apple-input"
                            placeholder="Enter your current password"
                            autocomplete="current-password"
                        >
                        @error('current_password')
                            <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- New + Confirm side-by-side --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-[#1D1D1F] text-sm font-medium mb-1.5">
                                New Password
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="apple-input"
                                placeholder="Min. 8 characters"
                                autocomplete="new-password"
                            >
                            @error('password')
                                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-[#1D1D1F] text-sm font-medium mb-1.5">
                                Confirm New Password
                            </label>
                            <input
                                type="password"
                                id="password_confirmation"
                                name="password_confirmation"
                                class="apple-input"
                                placeholder="Repeat new password"
                                autocomplete="new-password"
                            >
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" style="width:auto; padding:0.65rem 2rem;">
                        Save Changes
                    </button>
                </div>

            </form>
        </div>

    </div>
</div>
@endsection
