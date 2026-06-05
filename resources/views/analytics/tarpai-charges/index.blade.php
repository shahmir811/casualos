@extends('layouts.app')
@section('title', 'Tarpai Charges')
@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Tarpai Charges</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Auto-calculated every Friday from Tarpai sends (Rashid Bhai &amp; Yousaf Bhai)</p>
</div>

{{-- Recalculate panel --}}
<div class="card p-5 mb-6"
     x-data="{ open: false }">
    <button type="button"
            @click="open = !open"
            class="flex items-center gap-2 text-sm font-medium text-[#0066CC] hover:underline">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span x-text="open ? 'Cancel' : 'Recalculate Tarpai Charges for a Week'">Recalculate Tarpai Charges for a Week</span>
    </button>

    <div x-show="open" x-transition class="mt-4 border-t border-[#E5E5EA] pt-4">
        <p class="text-sm text-[#6E6E73] mb-4">
            Pick any date within the target week. The system will sum all pieces sent to
            Rashid Bhai and Yousaf Bhai (Saturday → Friday) per catalogue and create or update
            <strong>unconfirmed</strong> charge records. Confirmed (paid) records are never changed.
        </p>
        <form id="form-recalculate" method="POST" action="{{ route('tarpai-charges.recalculate') }}" class="flex items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Any date in the week</label>
                <input type="date" name="week_date" value="{{ today()->toDateString() }}"
                       class="apple-input" required>
            </div>
            <button type="button" class="btn-secondary"
                    @click="$store.confirm.show({
                        title: 'Recalculate Tarpai Charges',
                        message: 'Unconfirmed charge records for this week will be overwritten. Confirmed (paid) records will not be changed.',
                        formId: 'form-recalculate',
                        confirmText: 'Yes, Recalculate'
                    })">
                Calculate
            </button>
        </form>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('tarpai-charges.index') }}" class="card p-4 mb-6">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Week</label>
            <input type="date" name="week_date"
                   value="{{ request('week_date') }}"
                   class="apple-input">
        </div>
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Tarpai House</label>
            <select name="tarpai_house" class="apple-input">
                <option value="">All Houses</option>
                <option value="rashid_bhai"  {{ request('tarpai_house') === 'rashid_bhai'  ? 'selected' : '' }}>Rashid Bhai</option>
                <option value="yousaf_bhai"  {{ request('tarpai_house') === 'yousaf_bhai'  ? 'selected' : '' }}>Yousaf Bhai</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Status</label>
            <select name="status" class="apple-input">
                <option value="">All</option>
                <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
                <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        @if(request()->hasAny(['week_date','tarpai_house','status']))
        <a href="{{ route('tarpai-charges.index') }}" class="btn-secondary">Clear</a>
        @endif
    </div>
</form>

@php
    $totalPaid    = $payments->where('is_confirmed', true)->sum('total_amount');
    $totalPending = $payments->where('is_confirmed', false)->sum('total_amount');
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Confirmed &amp; Paid</p>
        <p class="text-3xl font-light text-green-600">Rs. {{ lacs_format($totalPaid, 0) }}</p>
        <p class="text-xs text-[#86868B] mt-1">{{ request()->hasAny(['week_date','tarpai_house','status']) ? 'filtered results' : 'this page' }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pending Confirmation</p>
        <p class="text-3xl font-light {{ $totalPending > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">Rs. {{ lacs_format($totalPending, 0) }}</p>
        <p class="text-xs text-[#86868B] mt-1">{{ request()->hasAny(['week_date','tarpai_house','status']) ? 'filtered results' : 'this page' }}</p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Week</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">House</th>
                <th class="text-right">Pieces</th>
                <th class="text-right">Total Amount</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($payments as $payment)
            <tr class="cursor-pointer hover:bg-[#F5F5F7]"
                onclick="window.location='{{ route('tarpai-charges.show', $payment) }}'">
                <td>
                    <a href="{{ route('tarpai-charges.show', $payment) }}"
                       class="font-medium text-sm text-[#0066CC] hover:underline">
                        {{ $payment->week_start->format('d M') }} – {{ $payment->week_end->format('d M Y') }}
                    </a>
                </td>
                <td>{{ $payment->catalogue->name ?? '—' }}</td>
                <td>
                    <span class="badge {{ $payment->houseBadgeClass() }}">{{ $payment->houseLabel() }}</span>
                </td>
                <td class="text-right">{{ lacs_format($payment->total_pieces_sent) }}</td>
                <td class="text-right font-semibold">Rs. {{ lacs_format($payment->total_amount, 0) }}</td>
                <td>
                    @if($payment->is_confirmed)
                        <span class="badge bg-green-100 text-green-700">Confirmed</span>
                    @else
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @endif
                </td>
                <td class="text-[#86868B] text-xs">
                    @if($payment->is_confirmed)
                        {{ $payment->confirmed_at?->format('d M Y') }}
                    @else
                        <span class="text-[#0066CC]">View →</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">No charge records yet. Run the calculation on a Friday or use Recalculate above.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $payments->links() }}</div>

@endsection
