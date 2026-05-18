@extends('layouts.app')
@section('title', 'Production Status')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Production Status</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Production Status</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Orders currently in confirmed or stitching stage</p>
</div>

@php
    $confirmed = $orders->where('status', 'confirmed');
    $stitching = $orders->where('status', 'stitching');
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Confirmed</p><p class="text-3xl font-light text-yellow-500">{{ $confirmed->count() }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">In Stitching</p><p class="text-3xl font-light text-orange-500">{{ $stitching->count() }}</p></div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Order #</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Catalogue</th>
                <th class="text-right">Amount</th>
                <th class="text-left">Stage</th>
                <th class="text-left">Since</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="font-medium">#{{ $order->order_number }}</td>
                <td>{{ $order->customer->name ?? '—' }}</td>
                <td>{{ $order->catalogue->name ?? '—' }}</td>
                <td class="text-right">PKR {{ lacs_format($order->total_amount, 0) }}</td>
                <td>
                    <span class="badge {{ $order->status === 'stitching' ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $order->updated_at->format('d M Y') }}</td>
                <td><a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] text-sm hover:underline">View →</a></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-[#86868B] py-12">No orders in production.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
