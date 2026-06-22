@extends('layouts.app')
@section('title', 'Payroll History')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Payroll History</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Payroll History</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Weekly stitching wage records &mdash; <span class="font-medium text-[#1D1D1F]">{{ $selectedCatalogue->name }}</span></p>
</div>

@php
    $totalPaid    = $wages->where('is_confirmed', true)->sum('total_wages');
    $totalPending = $wages->where('is_confirmed', false)->sum('total_wages');
    $totalSuits   = $wages->sum('total_suits_stitched');
@endphp

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Paid Out</p>
        <p class="text-2xl font-light text-green-600">Rs. {{ number_format($totalPaid, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pending Payment</p>
        <p class="text-2xl font-light {{ $totalPending > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">Rs. {{ number_format($totalPending, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Suits</p>
        <p class="text-2xl font-light text-[#1D1D1F]">{{ number_format($totalSuits) }}</p>
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
                <th class="text-right">Rate / Suit</th>
                <th class="text-right">Total Wages</th>
                <th class="text-left">Status</th>
                <th class="text-left">Confirmed By</th>
                <th class="text-left">Confirmed On</th>
            </tr>
        </thead>
        <tbody>
            @forelse($wages as $wage)
            <tr>
                <td>
                    <p class="font-medium text-sm">{{ $wage->week_start->format('d M') }} – {{ $wage->week_end->format('d M Y') }}</p>
                </td>
                <td class="text-[#6E6E73]">{{ $wage->catalogue->name ?? '—' }}</td>
                <td class="text-[#6E6E73] text-sm">{{ $wage->stitchingUnit ? 'Unit '.$wage->stitchingUnit->number.' — '.$wage->stitchingUnit->name : '—' }}</td>
                <td class="text-right">{{ number_format($wage->total_suits_stitched) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">Rs. {{ number_format($wage->wage_rate, 0) }}</td>
                <td class="text-right font-semibold">Rs. {{ number_format($wage->total_wages, 0) }}</td>
                <td>
                    <span class="badge {{ $wage->is_confirmed ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                        {{ $wage->is_confirmed ? 'Confirmed' : 'Pending' }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $wage->confirmedBy?->name ?? '—' }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $wage->confirmed_at?->format('d M Y') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-[#86868B] py-12">No payroll records found.</td></tr>
            @endforelse
        </tbody>
        @if($wages->count())
        <tfoot>
            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                <td class="px-5 py-3 font-semibold text-sm" colspan="3">Total</td>
                <td class="px-5 py-3 text-right font-bold text-sm">{{ number_format($totalSuits) }}</td>
                <td></td>
                <td class="px-5 py-3 text-right font-bold text-sm">Rs. {{ number_format($totalPaid + $totalPending, 0) }}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@endsection
