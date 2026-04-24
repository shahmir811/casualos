@extends('layouts.app')
@section('title', 'Flagged Orders')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Flagged</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Flagged Orders</h1>
    <p class="text-[#6E6E73] text-sm mt-1">{{ $orders->total() }} orders flagged for attention</p>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Order #</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Catalogue</th>
                <th class="text-right">Amount</th>
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
            <tr class="border-l-4 border-l-[#FF3B30]">
                <td class="font-medium">
                    #{{ $order->id }}
                    <span class="text-[#FF3B30] ml-1">⚑</span>
                </td>
                <td>{{ $order->customer->name ?? '—' }}</td>
                <td>
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg overflow-hidden flex-shrink-0 border border-[#E8E8ED] bg-[#F5F5F7]">
                            @if($order->catalogue?->cover_photo)
                                <img src="{{ Storage::url($order->catalogue->cover_photo) }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                        </div>
                        <span class="text-sm text-[#6E6E73]">{{ $order->catalogue->name ?? '—' }}</span>
                    </div>
                </td>
                <td class="text-right">PKR {{ number_format($order->total_amount, 0) }}</td>
                <td><span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">{{ ucfirst($order->status) }}</span></td>
                <td class="text-[#86868B] text-xs">{{ $order->created_at->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">
                    <svg class="w-8 h-8 mx-auto mb-3 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    No flagged orders. All clear!
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $orders->links() }}</div>

@endsection
