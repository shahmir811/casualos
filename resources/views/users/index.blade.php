@extends('layouts.app')

@section('title', 'User Management')

@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Team Accounts</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Manage team access and roles</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Account
    </a>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Name</th>
                <th class="text-left">Email</th>
                <th class="text-left">Role</th>
                <th class="text-left">Status</th>
                <th class="text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr x-data="{ showReset: false }">
                <td class="font-medium text-[#1D1D1F]">{{ $user->name }}</td>
                <td class="text-[#6E6E73] text-sm">{{ $user->email }}</td>
                <td>
                    <span class="badge
                        @if($user->role === 'accountant') bg-yellow-100 text-yellow-700
                        @elseif($user->role === 'production_manager') bg-blue-100 text-blue-700
                        @else bg-purple-100 text-purple-700
                        @endif">
                        {{ match($user->role) {
                            'production_manager' => 'Production Manager',
                            'creative_head'      => 'Creative Head',
                            default              => ucfirst($user->role),
                        } }}
                    </span>
                </td>
                <td>
                    @if($user->is_active)
                        <span class="badge bg-green-100 text-green-700">Active</span>
                    @else
                        <span class="badge bg-[#FFF0EF] text-[#FF3B30]">Disabled</span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-4">
                        @if($user->is_active)
                        <form id="form-disable-{{ $user->id }}" method="POST" action="{{ route('users.disable', $user) }}">@csrf</form>
                        <button type="button"
                                class="text-[#FF3B30] text-xs font-medium hover:underline"
                                @click="$store.confirm.show({
                                    title: 'Disable Account',
                                    message: `Disable {{ $user->name }}'s account? They will not be able to log in until re-enabled.`,
                                    formId: 'form-disable-{{ $user->id }}',
                                    confirmText: 'Disable',
                                    danger: true
                                })">
                            Disable
                        </button>
                        @else
                        <form method="POST" action="{{ route('users.enable', $user) }}">
                            @csrf
                            <button type="submit" class="text-[#30D158] text-xs font-medium hover:underline">
                                Enable
                            </button>
                        </form>
                        @endif

                        <button @click="showReset = !showReset"
                            class="text-[#0066CC] text-xs font-medium hover:underline">
                            Reset Password
                        </button>
                    </div>

                    {{-- Inline Reset Password Form --}}
                    <div x-show="showReset" x-cloak class="mt-3">
                        <form method="POST" action="{{ route('users.reset-password', $user) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="password" name="password" placeholder="New password" required minlength="8"
                                class="apple-input text-sm" style="width: 160px; padding: 0.45rem 0.75rem;">
                            <input type="password" name="password_confirmation" placeholder="Confirm" required
                                class="apple-input text-sm" style="width: 130px; padding: 0.45rem 0.75rem;">
                            <button type="submit" class="btn-primary" style="padding: 0.45rem 1rem; font-size:0.75rem;">
                                Set
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center text-[#86868B] py-12">No team accounts yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
