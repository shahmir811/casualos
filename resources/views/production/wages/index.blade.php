@extends('layouts.app')
@section('title', 'Worker Wages')
@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Worker Wages</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Auto-calculated every Friday from stitching returns</p>
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
        <span x-text="open ? 'Cancel' : 'Recalculate Wages for a Week'">Recalculate Wages for a Week</span>
    </button>

    <div x-show="open" x-transition class="mt-4 border-t border-[#E5E5EA] pt-4">
        <p class="text-sm text-[#6E6E73] mb-4">
            Pick any date within the target week. The system will sum all kameez returned
            (Saturday → Friday) per catalogue per stitching unit and create or update
            <strong>unconfirmed</strong> wage records. Confirmed (paid) records are never changed.
        </p>
        <form id="form-recalculate" method="POST" action="{{ route('wages.recalculate') }}" class="flex items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Any date in the week</label>
                <input type="date" name="week_date" value="{{ today()->toDateString() }}"
                       class="apple-input" required>
            </div>
            <button type="button" class="btn-secondary"
                    @click="$store.confirm.show({
                        title: 'Recalculate Wages',
                        message: 'Unconfirmed wage records for this week will be overwritten. Confirmed (paid) records will not be changed.',
                        formId: 'form-recalculate',
                        confirmText: 'Yes, Recalculate'
                    })">
                Calculate
            </button>
        </form>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('wages.index') }}" class="card p-4 mb-6">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Week</label>
            <input type="date" name="week_date"
                   value="{{ request('week_date') }}"
                   class="apple-input">
        </div>
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Stitching Unit</label>
            <select name="stitching_unit_id" class="apple-input">
                <option value="">All Units</option>
                @foreach($units as $unit)
                <option value="{{ $unit->id }}" {{ request('stitching_unit_id') == $unit->id ? 'selected' : '' }}>
                    Unit {{ $unit->number }} — {{ $unit->name }}
                </option>
                @endforeach
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
        @if(request()->hasAny(['week_date','stitching_unit_id','status']))
        <a href="{{ route('wages.index') }}" class="btn-secondary">Clear</a>
        @endif
    </div>
</form>

@php
    $totalPaid    = $wages->where('is_confirmed', true)->sum('total_wages');
    $totalPending = $wages->where('is_confirmed', false)->sum('total_wages');
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Confirmed &amp; Paid</p>
        <p class="text-3xl font-light text-green-600">Rs. {{ lacs_format($totalPaid, 0) }}</p>
        <p class="text-xs text-[#86868B] mt-1">{{ request()->hasAny(['week_date','stitching_unit_id','status']) ? 'filtered results' : 'this page' }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pending Confirmation</p>
        <p class="text-3xl font-light {{ $totalPending > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">Rs. {{ lacs_format($totalPending, 0) }}</p>
        <p class="text-xs text-[#86868B] mt-1">{{ request()->hasAny(['week_date','stitching_unit_id','status']) ? 'filtered results' : 'this page' }}</p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Week</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Unit</th>
                <th class="text-right">Suits</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Total</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($wages as $wage)
            <tr class="cursor-pointer hover:bg-[#F5F5F7]"
                onclick="window.location='{{ route('wages.show', $wage) }}'">
                <td>
                    <a href="{{ route('wages.show', $wage) }}"
                       class="font-medium text-sm text-[#0066CC] hover:underline">
                        {{ $wage->week_start->format('d M') }} – {{ $wage->week_end->format('d M Y') }}
                    </a>
                </td>
                <td>{{ $wage->catalogue->name ?? '—' }}</td>
                <td class="text-[#6E6E73] text-sm">{{ $wage->stitchingUnit ? 'Unit '.$wage->stitchingUnit->number.' — '.$wage->stitchingUnit->name : '—' }}</td>
                <td class="text-right">{{ lacs_format($wage->total_suits_stitched) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">Rs. {{ lacs_format($wage->wage_rate, 0) }}/pc</td>
                <td class="text-right font-semibold">Rs. {{ lacs_format($wage->total_wages, 0) }}</td>
                <td>
                    @if($wage->is_confirmed)
                        <span class="badge bg-green-100 text-green-700">Confirmed</span>
                    @else
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @endif
                </td>
                <td class="text-[#86868B] text-xs">
                    @if($wage->is_confirmed)
                        {{ $wage->confirmed_at?->format('d M Y') }}
                    @else
                        <span class="text-[#0066CC]">View →</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-[#86868B] py-12">No wage records yet. Run the calculation on a Friday or use Recalculate above.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $wages->links() }}</div>

@endsection
