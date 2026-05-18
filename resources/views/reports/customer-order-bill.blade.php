@extends('layouts.app')
@section('title', 'Customer Order Bill')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Customer Order Bill</span>
</div>

<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Customer Order Bill</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Per-customer bill, amount received, and outstanding balance</p>
    </div>
    <a href="{{ route('reports.customer-order-bill.pdf') }}"
       class="btn-secondary flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Download PDF
    </a>
</div>

@php
    $totXs      = $orders->sum('agg_xs');
    $totS       = $orders->sum('agg_s');
    $totM       = $orders->sum('agg_m');
    $totL       = $orders->sum('agg_l');
    $totXl      = $orders->sum('agg_xl');
    $totQty     = $orders->sum('agg_total');
    $totBill    = $orders->sum('total_amount');
    $totPaid    = $orders->sum('total_paid');
    $totBalance = $orders->sum('outstanding_balance');
@endphp

<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Bill</p>
        <p class="text-2xl font-light text-[#1D1D1F]">Rs. {{ lacs_format($totBill, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Amount Received</p>
        <p class="text-2xl font-light text-green-600">Rs. {{ lacs_format($totPaid, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p>
        <p class="text-2xl font-light {{ $totBalance > 0 ? 'text-red-500' : 'text-[#86868B]' }}">Rs. {{ lacs_format($totBalance, 0) }}</p>
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
                    <th class="text-right">XS</th>
                    <th class="text-right">S</th>
                    <th class="text-right">M</th>
                    <th class="text-right">L</th>
                    <th class="text-right">XL</th>
                    <th class="text-right">Total Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Total Bill</th>
                    <th class="text-right">Received</th>
                    <th class="text-right">Receivable</th>
                    <th class="text-left">Title Given</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $i => $order)
                <tr>
                    <td class="text-[#86868B] text-xs">{{ $i + 1 }}</td>
                    <td class="font-medium">{{ $order->customer?->name ?? $order->submitted_name }}</td>
                    <td class="text-[#6E6E73]">{{ $order->customer?->city ?? $order->submitted_city }}</td>
                    <td class="text-right">{{ $order->agg_xs ?: '—' }}</td>
                    <td class="text-right">{{ $order->agg_s ?: '—' }}</td>
                    <td class="text-right">{{ $order->agg_m ?: '—' }}</td>
                    <td class="text-right">{{ $order->agg_l ?: '—' }}</td>
                    <td class="text-right">{{ $order->agg_xl ?: '—' }}</td>
                    <td class="text-right font-medium">{{ lacs_format($order->agg_total) }}</td>
                    <td class="text-right text-[#6E6E73] text-xs">{{ lacs_format($order->agg_rate) }}</td>
                    <td class="text-right">{{ lacs_format($order->total_amount, 0) }}</td>
                    <td class="text-right text-green-700">{{ lacs_format($order->total_paid, 0) }}</td>
                    <td class="text-right {{ $order->outstanding_balance > 0 ? 'text-red-600 font-medium' : 'text-[#86868B]' }}">
                        {{ lacs_format($order->outstanding_balance, 0) }}
                    </td>
                    <td class="text-[#6E6E73] text-xs">{{ $order->title_given_label }}</td>
                </tr>
                @empty
                <tr><td colspan="14" class="text-center text-[#86868B] py-12">No confirmed orders for this catalogue.</td></tr>
                @endforelse
            </tbody>
            @if($orders->count())
            <tfoot>
                <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-3 font-semibold text-sm" colspan="3">Total</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $totXs ?: '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $totS ?: '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $totM ?: '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $totL ?: '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $totXl ?: '—' }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ lacs_format($totQty) }}</td>
                    <td class="px-5 py-3"></td>
                    <td class="px-5 py-3 text-right font-bold text-sm">Rs. {{ lacs_format($totBill, 0) }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm text-green-700">Rs. {{ lacs_format($totPaid, 0) }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm {{ $totBalance > 0 ? 'text-red-600' : 'text-[#86868B]' }}">Rs. {{ lacs_format($totBalance, 0) }}</td>
                    <td class="px-5 py-3"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

@endsection
