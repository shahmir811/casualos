@extends('layouts.app')
@section('title', 'Bank Account Breakdown')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Bank Account Breakdown</span>
</div>

<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Bank Account Breakdown</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Per-customer payments broken down by bank account</p>
    </div>
    <a href="{{ route('reports.bank-account-breakdown.pdf') }}"
       class="btn-secondary flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Download PDF
    </a>
</div>

@php
    $grandTotals = $bankAccounts->mapWithKeys(fn($ba) => [$ba->id => $orders->sum(fn($o) => $o->bank_totals[$ba->id] ?? 0)]);
    $grandMisc   = $orders->sum('misc_total');
    $grandPaid   = $orders->sum('total_paid');
    $grandBal    = $orders->sum('outstanding_balance');
@endphp

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Received</p>
        <p class="text-2xl font-light text-green-600">Rs. {{ number_format($grandPaid, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p>
        <p class="text-2xl font-light {{ $grandBal > 0 ? 'text-red-500' : 'text-[#86868B]' }}">Rs. {{ number_format($grandBal, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Orders</p>
        <p class="text-2xl font-light text-[#1D1D1F]">{{ $orders->count() }}</p>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-[#E8E8ED] flex items-center justify-between">
        <h2 class="font-semibold text-[#1D1D1F]">{{ $selectedCatalogue->name }}</h2>
        <span class="text-[#6E6E73] text-sm">{{ $orders->count() }} {{ Str::plural('order', $orders->count()) }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">#</th>
                    <th class="text-left">Customer</th>
                    <th class="text-left">City</th>
                    @foreach($bankAccounts as $ba)
                        <th class="text-right text-xs">{{ $ba->title }}</th>
                    @endforeach
                    <th class="text-right text-xs">Cash / Adv.</th>
                    <th class="text-right">Total Payment</th>
                    <th class="text-right">Net Receivable</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $i => $order)
                <tr>
                    <td class="text-[#86868B] text-xs">{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $order->customer?->name ?? $order->submitted_name }}</td>
                    <td class="text-[#6E6E73]">{{ $order->customer?->city ?? $order->submitted_city }}</td>
                    @foreach($bankAccounts as $ba)
                        <td class="text-right text-sm">
                            {{ ($order->bank_totals[$ba->id] ?? 0) > 0 ? number_format($order->bank_totals[$ba->id], 0) : '—' }}
                        </td>
                    @endforeach
                    <td class="text-right text-sm">{{ $order->misc_total > 0 ? number_format($order->misc_total, 0) : '—' }}</td>
                    <td class="text-right font-medium">{{ number_format($order->total_paid, 0) }}</td>
                    <td class="text-right {{ $order->outstanding_balance > 0 ? 'text-red-600 font-medium' : 'text-[#86868B]' }}">
                        {{ number_format($order->outstanding_balance, 0) }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ 7 + $bankAccounts->count() }}" class="text-center text-[#86868B] py-12">No confirmed orders for this catalogue.</td></tr>
                @endforelse
            </tbody>
            @if($orders->count())
            <tfoot>
                <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-3 font-semibold text-sm" colspan="3">Total</td>
                    @foreach($bankAccounts as $ba)
                        <td class="px-5 py-3 text-right font-bold text-sm">
                            {{ $grandTotals[$ba->id] > 0 ? 'Rs. ' . number_format($grandTotals[$ba->id], 0) : '—' }}
                        </td>
                    @endforeach
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $grandMisc > 0 ? 'Rs. ' . number_format($grandMisc, 0) : '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm text-green-700">Rs. {{ number_format($grandPaid, 0) }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm {{ $grandBal > 0 ? 'text-red-600' : 'text-[#86868B]' }}">Rs. {{ number_format($grandBal, 0) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
