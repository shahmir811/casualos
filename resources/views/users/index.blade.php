@extends('layouts.app')

@section('title', 'User Management')

@section('content')

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Team Accounts</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Manage team access and roles</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn-primary self-start sm:self-auto">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Account
    </a>
</div>

{{-- ── Desktop table (md+) ─────────────────────────────────────────── --}}
<div class="card overflow-hidden hidden md:block">
    <div class="overflow-x-auto">
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
                            @if($user->role === 'admin') bg-[#1D1D1F] text-white
                            @elseif($user->role === 'accountant') bg-yellow-100 text-yellow-700
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
                            <button type="button"
                                    onclick="copyStaffLoginToken('{{ $user->mobile_login_token }}', '{{ $user->email }}', this)"
                                    title="Copy mobile app login token + email"
                                    class="text-[#0066CC] text-xs font-medium hover:underline flex items-center gap-1 transition-colors">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Login Token
                            </button>

                            @if($user->isAdmin())
                            <span class="text-[#86868B] text-xs">Protected</span>
                            @else
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
                            @endif
                        </div>

                        {{-- Inline Reset Password Form --}}
                        @unless($user->isAdmin())
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
                        @endunless
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
</div>

{{-- ── Mobile cards (below md) ─────────────────────────────────────── --}}
<div class="space-y-3 md:hidden">
    @forelse($users as $user)
    <div class="card p-4" x-data="{ showReset: false }">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
                <p class="text-[#1D1D1F] text-sm font-semibold truncate">{{ $user->name }}</p>
                <p class="text-[#6E6E73] text-xs truncate">{{ $user->email }}</p>
            </div>
            @if($user->is_active)
                <span class="badge bg-green-100 text-green-700 shrink-0">Active</span>
            @else
                <span class="badge bg-[#FFF0EF] text-[#FF3B30] shrink-0">Disabled</span>
            @endif
        </div>

        <div class="mb-3">
            <span class="badge
                @if($user->role === 'admin') bg-[#1D1D1F] text-white
                @elseif($user->role === 'accountant') bg-yellow-100 text-yellow-700
                @elseif($user->role === 'production_manager') bg-blue-100 text-blue-700
                @else bg-purple-100 text-purple-700
                @endif">
                {{ match($user->role) {
                    'production_manager' => 'Production Manager',
                    'creative_head'      => 'Creative Head',
                    default              => ucfirst($user->role),
                } }}
            </span>
        </div>

        <div class="flex items-center gap-4 pt-3 border-t border-[#E8E8ED]">
            <button type="button"
                    onclick="copyStaffLoginToken('{{ $user->mobile_login_token }}', '{{ $user->email }}', this)"
                    title="Copy mobile app login token + email"
                    class="text-[#0066CC] text-xs font-medium hover:underline flex items-center gap-1 transition-colors">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Login Token
            </button>

            @if($user->isAdmin())
            <span class="text-[#86868B] text-xs">Protected</span>
            @else
                @if($user->is_active)
                <form id="form-disable-mobile-{{ $user->id }}" method="POST" action="{{ route('users.disable', $user) }}">@csrf</form>
                <button type="button"
                        class="text-[#FF3B30] text-xs font-medium hover:underline"
                        @click="$store.confirm.show({
                            title: 'Disable Account',
                            message: `Disable {{ $user->name }}'s account? They will not be able to log in until re-enabled.`,
                            formId: 'form-disable-mobile-{{ $user->id }}',
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
            @endif
        </div>

        {{-- Inline Reset Password Form --}}
        @unless($user->isAdmin())
        <div x-show="showReset" x-cloak class="mt-3 pt-3 border-t border-[#E8E8ED]">
            <form method="POST" action="{{ route('users.reset-password', $user) }}" class="flex flex-col gap-2">
                @csrf
                <input type="password" name="password" placeholder="New password" required minlength="8"
                    class="apple-input text-sm w-full" style="padding: 0.5rem 0.75rem;">
                <input type="password" name="password_confirmation" placeholder="Confirm" required
                    class="apple-input text-sm w-full" style="padding: 0.5rem 0.75rem;">
                <button type="submit" class="btn-primary w-full" style="padding: 0.5rem 1rem; font-size:0.75rem;">
                    Set
                </button>
            </form>
        </div>
        @endunless
    </div>
    @empty
    <div class="card p-8 text-center text-[#86868B] text-sm">No team accounts yet.</div>
    @endforelse
</div>

<script>
function copyStaffLoginToken(token, email, btn) {
    const text = `Login Token: ${token}\nEmail: ${email}`;
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = `<svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Copied!`;
        btn.classList.add('text-[#30D158]');
        btn.classList.remove('text-[#0066CC]');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.remove('text-[#30D158]');
            btn.classList.add('text-[#0066CC]');
        }, 2000);
    });
}
</script>

@endsection
