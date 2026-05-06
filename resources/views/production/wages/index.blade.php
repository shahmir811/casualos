@extends('layouts.app')
@section('title', 'Worker Wages')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Worker Wages</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Weekly stitching wages by catalogue</p>
    </div>
    <a href="{{ route('wages.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Record Wages
    </a>
</div>

@php
    $totalPaid = $wages->where('is_confirmed', true)->sum('total_wages');
    $totalPending = $wages->where('is_confirmed', false)->sum('total_wages');
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Confirmed &amp; Paid</p>
        <p class="text-3xl font-light text-green-600">Rs. {{ number_format($totalPaid, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pending Confirmation</p>
        <p class="text-3xl font-light {{ $totalPending > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">Rs. {{ number_format($totalPending, 0) }}</p>
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
            <tr>
                <td>
                    <p class="font-medium text-sm">{{ $wage->week_start->format('d M') }} – {{ $wage->week_end->format('d M Y') }}</p>
                </td>
                <td>{{ $wage->catalogue->name ?? '—' }}</td>
                <td class="text-[#6E6E73] text-sm">{{ $wage->stitchingUnit ? 'Unit '.$wage->stitchingUnit->number.' — '.$wage->stitchingUnit->name : '—' }}</td>
                <td class="text-right">{{ number_format($wage->total_suits_stitched) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">Rs. {{ number_format($wage->wage_rate, 0) }}/suit</td>
                <td class="text-right font-semibold">Rs. {{ number_format($wage->total_wages, 0) }}</td>
                <td>
                    @if($wage->is_confirmed)
                        <span class="badge bg-green-100 text-green-700">Confirmed</span>
                    @else
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @endif
                </td>
                <td>
                    @if(!$wage->is_confirmed)
                    <form method="POST" action="{{ route('wages.confirm', $wage) }}" class="inline">
                        @csrf
                        <button type="submit" class="text-[#0066CC] text-xs hover:underline"
                                onclick="return confirm('Confirm payment of Rs. {{ number_format($wage->total_wages, 0) }}?')">
                            Confirm →
                        </button>
                    </form>
                    @else
                    <span class="text-[#86868B] text-xs">{{ $wage->confirmed_at?->format('d M Y') }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-[#86868B] py-12">No wage records yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $wages->links() }}</div>

@endsection
