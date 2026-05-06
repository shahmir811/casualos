@extends('layouts.app')
@section('title', 'Dispatch')
@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dispatch</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Orders in stitching status ready for dispatch</p>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Order #</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Amount</th>
                <th class="text-left">Confirmed Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            <tr>
                <td class="font-medium">#{{ $order->order_number }}</td>
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
                <td>PKR {{ number_format($order->total_amount, 0) }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $order->updated_at->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('dispatch.create', $order) }}" class="btn-primary text-xs">Dispatch →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-[#86868B] py-12">
                    <svg class="w-8 h-8 mx-auto mb-3 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    No orders ready for dispatch.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $orders->links() }}</div>

@endsection
