@extends('layouts.app')
@section('title', 'Order Dispatch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('dispatch.index') }}" class="text-[#0066CC] hover:underline text-sm">Dispatch</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Order #{{ $order->id }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Order Details</h2>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Customer</p><p class="font-medium text-[#1D1D1F]">{{ $order->customer->name ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">City</p><p class="text-[#1D1D1F]">{{ $order->customer->city ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p><p class="text-[#1D1D1F]">{{ $order->catalogue->name ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Order Amount</p><p class="font-semibold text-[#1D1D1F]">PKR {{ number_format($order->total_amount, 0) }}</p></div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Status</p>
                <span class="badge bg-orange-100 text-orange-700">{{ $order->status }}</span>
            </div>
        </div>

        @if($order->dispatchBatches->count())
        <a href="{{ route('dispatch.create', $order) }}" class="btn-primary w-full justify-center">Dispatch Again</a>
        @else
        <a href="{{ route('dispatch.create', $order) }}" class="btn-primary w-full justify-center">Create Dispatch</a>
        @endif
    </div>

    <div class="lg:col-span-2 space-y-4">
        {{-- Order Items --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]"><h2 class="text-sm font-semibold text-[#1D1D1F]">Order Items</h2></div>
            <table class="w-full apple-table">
                <thead><tr><th class="text-left">Design</th><th class="text-right">XS</th><th class="text-right">S</th><th class="text-right">M</th><th class="text-right">L</th><th class="text-right">XL</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td class="font-medium">{{ $item->design->name ?? '—' }}</td>
                        <td class="text-right">{{ $item->qty_xs }}</td>
                        <td class="text-right">{{ $item->qty_s }}</td>
                        <td class="text-right">{{ $item->qty_m }}</td>
                        <td class="text-right">{{ $item->qty_l }}</td>
                        <td class="text-right">{{ $item->qty_xl }}</td>
                        <td class="text-right">PKR {{ number_format($item->subtotal, 0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Previous Dispatches --}}
        @if($order->dispatchBatches->count())
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]"><h2 class="text-sm font-semibold text-[#1D1D1F]">Dispatch History</h2></div>
            <table class="w-full apple-table">
                <thead><tr><th class="text-left">Batch #</th><th class="text-left">Date</th><th class="text-left">Cargo Doc</th><th class="text-left">Address</th></tr></thead>
                <tbody>
                    @foreach($order->dispatchBatches as $d)
                    <tr>
                        <td class="font-medium">{{ $d->batch_number }}</td>
                        <td>{{ $d->dispatch_date->format('d M Y') }}</td>
                        <td class="text-[#6E6E73] text-xs">{{ $d->cargo_document ?? '—' }}</td>
                        <td class="text-[#6E6E73] text-xs">{{ $d->shipping_address ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
