@extends('layouts.app')
@section('title', 'Dispatch History')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Dispatch History</span>
</div>

<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dispatch History</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Per-customer dispatch status and remaining pieces &mdash; <span class="font-medium text-[#1D1D1F]">{{ $selectedCatalogue->name }}</span></p>
    </div>
    @if($orders->count())
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('reports.dispatch-history.excel') }}"
           class="btn-secondary text-sm px-4 py-2 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Excel
        </a>
        <a href="{{ route('reports.dispatch-history.pdf') }}"
           class="btn-primary text-sm px-4 py-2 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            PDF
        </a>
    </div>
    @endif
</div>

@php
    $fullyDispatched = $orders->where('status', 'dispatched')->count();
    $partiallyDisp   = $orders->where('status', 'partially_dispatched')->count();
    $pending         = $orders->whereIn('status', ['confirmed', 'stitching'])->count();
    $totalOrdered    = $orders->sum('total_ordered');
    $totalDispatched = $orders->sum('total_dispatched');
    $totalRemaining  = $orders->sum('total_remaining');
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Fully Dispatched</p>
        <p class="text-3xl font-light text-green-600">{{ $fullyDispatched }}</p>
        <p class="text-[#86868B] text-xs mt-1">orders</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Partially Dispatched</p>
        <p class="text-3xl font-light text-purple-600">{{ $partiallyDisp }}</p>
        <p class="text-[#86868B] text-xs mt-1">orders</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pending Dispatch</p>
        <p class="text-3xl font-light text-[#0071E3]">{{ $pending }}</p>
        <p class="text-[#86868B] text-xs mt-1">orders</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pieces Remaining</p>
        <p class="text-3xl font-light {{ $totalRemaining > 0 ? 'text-orange-500' : 'text-green-600' }}">{{ number_format($totalRemaining) }}</p>
        <p class="text-[#86868B] text-xs mt-1">to dispatch</p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">#</th>
                <th class="text-left">Customer</th>
                <th class="text-left">City</th>
                <th class="text-left">Order #</th>
                <th class="text-left">Status</th>
                <th class="text-right">Ordered</th>
                <th class="text-right">Dispatched</th>
                <th class="text-right">Remaining</th>
                <th class="text-left">First Dispatch</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $i => $order)
            @php
                $statusMap = [
                    'confirmed'            => ['label' => 'Confirmed',    'class' => 'bg-yellow-100 text-yellow-700'],
                    'stitching'            => ['label' => 'Stitching',    'class' => 'bg-orange-100 text-orange-700'],
                    'partially_dispatched' => ['label' => 'Partial',      'class' => 'bg-purple-100 text-purple-700'],
                    'dispatched'           => ['label' => 'Dispatched',   'class' => 'bg-green-100 text-green-700'],
                ];
                $s = $statusMap[$order->status] ?? ['label' => $order->status, 'class' => 'bg-gray-100 text-gray-700'];
            @endphp
            <tr>
                <td class="text-[#86868B] text-xs">{{ $i + 1 }}</td>
                <td class="font-medium">{{ $order->customer?->name ?? $order->submitted_name }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $order->customer?->city ?? $order->submitted_city ?? '—' }}</td>
                <td>
                    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline font-medium">
                        #{{ $order->order_number }}
                    </a>
                </td>
                <td><span class="badge {{ $s['class'] }}">{{ $s['label'] }}</span></td>
                <td class="text-right">{{ number_format($order->total_ordered) }}</td>
                <td class="text-right text-green-700 font-medium">{{ number_format($order->total_dispatched) }}</td>
                <td class="text-right font-semibold {{ $order->total_remaining === 0 ? 'text-green-600' : 'text-orange-600' }}">
                    {{ number_format($order->total_remaining) }}
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $order->first_dispatch?->format('d M Y') ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-[#86868B] py-12">No orders found for this catalogue.</td></tr>
            @endforelse
        </tbody>
        @if($orders->count())
        <tfoot>
            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                <td class="px-5 py-3 font-semibold text-sm" colspan="5">Total</td>
                <td class="px-5 py-3 text-right font-bold text-sm">{{ number_format($totalOrdered) }}</td>
                <td class="px-5 py-3 text-right font-bold text-sm text-green-600">{{ number_format($totalDispatched) }}</td>
                <td class="px-5 py-3 text-right font-bold text-sm {{ $totalRemaining > 0 ? 'text-orange-600' : 'text-green-600' }}">{{ number_format($totalRemaining) }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@endsection
