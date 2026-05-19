@extends('layouts.app')
@section('title', 'Wage Detail')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('wages.index') }}" class="text-[#0066CC] hover:underline text-sm">Wages</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">
        {{ $wage->stitchingUnit ? 'Unit '.$wage->stitchingUnit->number.' — '.$wage->stitchingUnit->name : '—' }}
        &middot; {{ $wage->week_start->format('d M') }}–{{ $wage->week_end->format('d M Y') }}
    </span>
</div>

{{-- Header row --}}
<div class="flex items-start justify-between mb-6 gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">
            {{ $wage->stitchingUnit ? 'Unit '.$wage->stitchingUnit->number.' — '.$wage->stitchingUnit->name : 'Wage Detail' }}
        </h1>
        <p class="text-[#6E6E73] text-sm mt-1">
            {{ $wage->catalogue->name ?? '—' }} &middot;
            {{ $wage->week_start->format('d M') }} – {{ $wage->week_end->format('d M Y') }}
        </p>
    </div>

    @if(!$wage->is_confirmed)
    <form id="form-confirm-wage" method="POST" action="{{ route('wages.confirm', $wage) }}">@csrf</form>
    <button type="button" class="btn-primary"
            @click="$store.confirm.show({
                title: 'Confirm Payment',
                message: `Mark Rs. {{ lacs_format($wage->total_wages, 0) }} as paid to {{ $wage->stitchingUnit?->name }}? This cannot be undone.`,
                formId: 'form-confirm-wage',
                confirmText: 'Yes, Confirm Payment'
            })">
        Confirm Payment
    </button>
    @else
    <div class="text-right">
        <span class="badge bg-green-100 text-green-700 text-sm px-3 py-1">Confirmed &amp; Paid</span>
        <p class="text-xs text-[#86868B] mt-1">{{ $wage->confirmed_at?->format('d M Y') }}</p>
    </div>
    @endif
</div>

{{-- Summary stats --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Kameez</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ lacs_format($wage->total_suits_stitched) }}</p>
        <p class="text-xs text-[#86868B] mt-1">pieces this week</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Rate</p>
        <p class="text-3xl font-light text-[#1D1D1F]">Rs. {{ lacs_format($wage->wage_rate, 0) }}</p>
        <p class="text-xs text-[#86868B] mt-1">per kameez</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Wages</p>
        <p class="text-3xl font-light {{ $wage->is_confirmed ? 'text-green-600' : 'text-orange-500' }}">
            Rs. {{ lacs_format($wage->total_wages, 0) }}
        </p>
        <p class="text-xs text-[#86868B] mt-1">{{ $wage->is_confirmed ? 'paid' : 'pending confirmation' }}</p>
    </div>
</div>

{{-- Per-design breakdown --}}
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-[#E5E5EA]">
        <h2 class="font-semibold text-[#1D1D1F]">Kameez Breakdown by Design</h2>
        <p class="text-xs text-[#6E6E73] mt-0.5">Sum of all kameez returned in this week window, grouped by design</p>
    </div>

    @if($byDesign->isEmpty())
    <div class="px-5 py-10 text-center text-[#86868B] text-sm">
        No kameez return data found for this week window.
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Design</th>
                    <th class="text-right">XS</th>
                    <th class="text-right">S</th>
                    <th class="text-right">M</th>
                    <th class="text-right">L</th>
                    <th class="text-right">XL</th>
                    <th class="text-right font-semibold">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byDesign as $designName => $sizes)
                <tr>
                    <td class="font-medium">{{ $designName }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $sizes['xs'] ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $sizes['s'] ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $sizes['m'] ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $sizes['l'] ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $sizes['xl'] ?: '—' }}</td>
                    <td class="text-right font-semibold">{{ $sizes['total'] }}</td>
                </tr>
                @endforeach
            </tbody>
            @php
                $grandXs    = $byDesign->sum('xs');
                $grandS     = $byDesign->sum('s');
                $grandM     = $byDesign->sum('m');
                $grandL     = $byDesign->sum('l');
                $grandXl    = $byDesign->sum('xl');
                $grandTotal = $byDesign->sum('total');
            @endphp
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="text-right">{{ $grandXs ?: '—' }}</td>
                    <td class="text-right">{{ $grandS ?: '—' }}</td>
                    <td class="text-right">{{ $grandM ?: '—' }}</td>
                    <td class="text-right">{{ $grandL ?: '—' }}</td>
                    <td class="text-right">{{ $grandXl ?: '—' }}</td>
                    <td class="text-right">{{ $grandTotal }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>

{{-- Wage formula callout --}}
<div class="mt-4 px-5 py-4 bg-[#F5F5F7] rounded-xl text-sm text-[#6E6E73]">
    {{ $grandTotal ?? 0 }} kameez
    &times; Rs. {{ lacs_format($wage->wage_rate, 0) }}/pc
    = <strong class="text-[#1D1D1F]">Rs. {{ lacs_format($wage->total_wages, 0) }}</strong>
</div>

@endsection
