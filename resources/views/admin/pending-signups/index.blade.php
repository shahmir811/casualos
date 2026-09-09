@extends('layouts.app')

@section('title', 'Pending Signups')

@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Pending Signups</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Customers who signed up from the mobile app, waiting for review.</p>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

{{-- Pending — Desktop table --}}
<div class="card overflow-hidden hidden md:block mb-8">
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Name</th>
                    <th class="text-left">Email</th>
                    <th class="text-left">Contact</th>
                    <th class="text-left">City / Country</th>
                    <th class="text-left">Submitted</th>
                    <th class="text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pending as $signup)
                <tr>
                    <td class="font-medium text-[#1D1D1F]">{{ $signup->name }}</td>
                    <td class="text-[#6E6E73] text-sm">{{ $signup->email }}</td>
                    <td class="text-[#6E6E73] text-sm">{{ $signup->contact_number }}</td>
                    <td class="text-[#6E6E73] text-sm">{{ $signup->city }}, {{ $signup->country }}</td>
                    <td class="text-[#86868B] text-sm" title="{{ $signup->created_at->format('M j, Y g:i A') }}">{{ $signup->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="flex items-center gap-4">
                            <form method="POST" action="{{ route('pending-signups.approve', $signup) }}">
                                @csrf
                                <button type="submit" class="text-[#0071E3] text-xs font-medium hover:underline">
                                    Approve
                                </button>
                            </form>

                            <form id="reject-form-{{ $signup->id }}" method="POST" action="{{ route('pending-signups.reject', $signup) }}" class="hidden">@csrf</form>
                            <button type="button"
                                    class="text-[#FF3B30] text-xs font-medium hover:underline"
                                    @click="$store.confirm.show({
                                        title: 'Reject Signup',
                                        message: `Reject {{ $signup->name }}'s signup request? No customer account will be created.`,
                                        formId: 'reject-form-{{ $signup->id }}',
                                        confirmText: 'Reject',
                                        danger: true
                                    })">
                                Reject
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-[#86868B] py-10 text-sm">No pending signups.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pending — Mobile cards --}}
<div class="space-y-3 md:hidden mb-8">
    @forelse($pending as $signup)
    <div class="card p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-medium text-[#1D1D1F]">{{ $signup->name }}</p>
                <p class="text-[#6E6E73] text-sm truncate">{{ $signup->email }}</p>
            </div>
            <span class="text-[#86868B] text-xs shrink-0" title="{{ $signup->created_at->format('M j, Y g:i A') }}">{{ $signup->created_at->diffForHumans() }}</span>
        </div>
        <div class="mt-2 text-sm text-[#6E6E73] space-y-0.5">
            <p>{{ $signup->contact_number }}</p>
            <p>{{ $signup->city }}, {{ $signup->country }}</p>
            @if($signup->address)<p>{{ $signup->address }}</p>@endif
        </div>
        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-[#F2F2F7]">
            <form method="POST" action="{{ route('pending-signups.approve', $signup) }}">
                @csrf
                <button type="submit" class="text-[#0071E3] text-sm font-medium hover:underline">
                    Approve
                </button>
            </form>

            <form id="reject-form-m-{{ $signup->id }}" method="POST" action="{{ route('pending-signups.reject', $signup) }}" class="hidden">@csrf</form>
            <button type="button"
                    class="text-[#FF3B30] text-sm font-medium hover:underline"
                    @click="$store.confirm.show({
                        title: 'Reject Signup',
                        message: `Reject {{ $signup->name }}'s signup request? No customer account will be created.`,
                        formId: 'reject-form-m-{{ $signup->id }}',
                        confirmText: 'Reject',
                        danger: true
                    })">
                Reject
            </button>
        </div>
    </div>
    @empty
    <div class="card p-6 text-center text-[#86868B] text-sm">No pending signups.</div>
    @endforelse
</div>

{{-- History --}}
<h2 class="text-lg font-semibold tracking-tight text-[#1D1D1F] mb-3">History</h2>
<div class="card overflow-hidden hidden md:block">
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Name</th>
                    <th class="text-left">Email</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Reviewed By</th>
                    <th class="text-left">Reviewed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $signup)
                <tr>
                    <td class="font-medium text-[#1D1D1F]">
                        @if($signup->status === 'approved' && $signup->customer)
                        <a href="{{ route('customers.show', $signup->customer) }}" class="text-[#0066CC] hover:underline">{{ $signup->name }}</a>
                        @else
                        {{ $signup->name }}
                        @endif
                    </td>
                    <td class="text-[#6E6E73] text-sm">{{ $signup->email }}</td>
                    <td>
                        @if($signup->status === 'approved')
                        <span class="badge bg-green-100 text-green-700">Approved</span>
                        @else
                        <span class="badge bg-[#FFF0EF] text-[#FF3B30]">Rejected</span>
                        @endif
                    </td>
                    <td class="text-[#6E6E73] text-sm">{{ $signup->reviewedBy?->name ?? '—' }}</td>
                    <td class="text-[#86868B] text-sm" title="{{ $signup->reviewed_at?->format('M j, Y g:i A') }}">{{ $signup->reviewed_at?->diffForHumans() ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-[#86868B] py-10 text-sm">No reviewed signups yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="space-y-3 md:hidden">
    @forelse($history as $signup)
    <div class="card p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                @if($signup->status === 'approved' && $signup->customer)
                <a href="{{ route('customers.show', $signup->customer) }}" class="font-medium text-[#0066CC] hover:underline">{{ $signup->name }}</a>
                @else
                <p class="font-medium text-[#1D1D1F]">{{ $signup->name }}</p>
                @endif
                <p class="text-[#6E6E73] text-sm truncate">{{ $signup->email }}</p>
            </div>
            @if($signup->status === 'approved')
            <span class="badge bg-green-100 text-green-700 shrink-0">Approved</span>
            @else
            <span class="badge bg-[#FFF0EF] text-[#FF3B30] shrink-0">Rejected</span>
            @endif
        </div>
        <p class="text-[#86868B] text-xs mt-2">by {{ $signup->reviewedBy?->name ?? '—' }} · {{ $signup->reviewed_at?->diffForHumans() ?? '—' }}</p>
    </div>
    @empty
    <div class="card p-6 text-center text-[#86868B] text-sm">No reviewed signups yet.</div>
    @endforelse
</div>

<div class="mt-5">{{ $history->links() }}</div>

@endsection
