@extends('layouts.app')

@section('title', 'Orders')

@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Orders</h1>
        <p class="text-[#6E6E73] text-sm mt-1">{{ $orders->total() }} total orders</p>
    </div>
    <a href="{{ route('orders.flagged') }}" class="btn-danger">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M4 4h16l-2 7H6L4 4z M6 11l-2 9h16l-2-9"/>
        </svg>
        Flagged Orders
    </a>
</div>

{{-- Filters --}}
<form method="GET" class="flex flex-wrap items-center gap-3 mb-5">
    <select name="status" class="apple-input" style="width:auto; min-width:160px;">
        <option value="">All Statuses</option>
        <option value="received"   {{ request('status') === 'received'   ? 'selected' : '' }}>Received</option>
        <option value="confirmed"  {{ request('status') === 'confirmed'  ? 'selected' : '' }}>Confirmed</option>
        <option value="stitching"  {{ request('status') === 'stitching'  ? 'selected' : '' }}>Stitching</option>
        <option value="dispatched" {{ request('status') === 'dispatched' ? 'selected' : '' }}>Dispatched</option>
    </select>
    <button type="submit" class="btn-secondary">Filter</button>
    @if(request('status'))
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] text-sm hover:underline">Clear</a>
    @endif
</form>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Order #</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Amount</th>
                <th class="text-left">Status</th>
                <th class="text-left">Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            @php
                $statusBadge = [
                    'received'   => 'badge bg-blue-100 text-blue-700',
                    'confirmed'  => 'badge bg-yellow-100 text-yellow-700',
                    'stitching'  => 'badge bg-orange-100 text-orange-700',
                    'dispatched' => 'badge bg-green-100 text-green-700',
                ];
            @endphp
            <tr class="{{ $order->is_flagged ? 'border-l-4 border-l-[#FF3B30]' : '' }}">
                <td class="font-medium">
                    #{{ $order->id }}
                    @if($order->is_flagged)
                        <span class="text-[#FF3B30] text-xs ml-1">⚑</span>
                    @endif
                </td>
                <td>{{ $order->customer->name ?? '—' }}</td>
                <td>
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg overflow-hidden flex-shrink-0 border border-[#E8E8ED] bg-[#F5F5F7]">
                            @if($order->catalogue?->cover_photo)
                                <img src="{{ Storage::url($order->catalogue->cover_photo) }}"
                                     alt="{{ $order->catalogue->name }}"
                                     class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <span class="text-[#6E6E73] text-sm">{{ $order->catalogue->name ?? '—' }}</span>
                    </div>
                </td>
                <td>PKR {{ number_format($order->total_amount, 0) }}</td>
                <td>
                    <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">{{ $order->status }}</span>
                </td>
                <td class="text-[#86868B] text-xs">{{ $order->created_at->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">No orders found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $orders->links() }}</div>

@endsection
