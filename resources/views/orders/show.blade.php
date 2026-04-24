@extends('layouts.app')

@section('title', 'Order #' . $order->id)

@section('content')

@php
    $statusBadge = [
        'received'   => 'badge bg-blue-100 text-blue-700',
        'confirmed'  => 'badge bg-yellow-100 text-yellow-700',
        'stitching'  => 'badge bg-orange-100 text-orange-700',
        'dispatched' => 'badge bg-green-100 text-green-700',
    ];
@endphp

<div class="flex items-start justify-between mb-7">
    <div>
        <a href="{{ route('orders.index') }}" class="text-[#0066CC] text-sm hover:underline">← Orders</a>
        <div class="flex flex-wrap items-center gap-2.5 mt-3">
            <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Order #{{ $order->id }}</h1>
            <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                {{ $order->status }}
            </span>
            @if($order->is_flagged)
            <span class="badge bg-[#FFF0EF] text-[#FF3B30]">⚑ Flagged</span>
            @endif
        </div>
        <p class="text-[#6E6E73] text-sm mt-1">
            {{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }} · {{ $order->created_at->format('d M Y') }}
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        @if($order->status === 'received')
        <form method="POST" action="{{ route('orders.confirm', $order) }}">
            @csrf
            <button type="submit" onclick="return confirm('Confirm this order?')" class="btn-primary">
                Confirm Order
            </button>
        </form>
        @endif

        @if($order->status === 'confirmed')
        <form method="POST" action="{{ route('orders.stitch', $order) }}">
            @csrf
            <button type="submit" class="btn-primary" style="background:#FF9500;">
                Send to Stitching
            </button>
        </form>
        @endif

        @if(Auth::user()->role === 'admin')
        <a href="{{ route('orders.reduce', $order) }}" class="btn-secondary">
            Log Reduction
        </a>
        @endif
    </div>
</div>

{{-- Financials --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Amount</p>
        <p class="text-[#1D1D1F] text-2xl font-light">PKR {{ number_format($order->total_amount, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Amount Paid</p>
        <p class="text-[#30D158] text-2xl font-light">PKR {{ number_format($order->total_paid, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p>
        <p class="{{ $order->outstanding_balance > 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }} text-2xl font-light">
            PKR {{ number_format($order->outstanding_balance, 0) }}
        </p>
    </div>
</div>

{{-- Order Items --}}
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Order Items</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Design</th>
                    <th class="text-center">XS</th>
                    <th class="text-center">S</th>
                    <th class="text-center">M</th>
                    <th class="text-center">L</th>
                    <th class="text-center">XL</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td class="font-medium">{{ $item->design->name ?? '—' }}</td>
                    <td class="text-center text-[#6E6E73]">{{ $item->qty_xs ?: '—' }}</td>
                    <td class="text-center text-[#6E6E73]">{{ $item->qty_s  ?: '—' }}</td>
                    <td class="text-center text-[#6E6E73]">{{ $item->qty_m  ?: '—' }}</td>
                    <td class="text-center text-[#6E6E73]">{{ $item->qty_l  ?: '—' }}</td>
                    <td class="text-center text-[#6E6E73]">{{ $item->qty_xl ?: '—' }}</td>
                    <td class="text-right font-medium">PKR {{ number_format($item->total_amount, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Record Payment --}}
@if(in_array(Auth::user()->role, ['admin', 'accountant']) && $order->outstanding_balance > 0)
<div class="card mb-5" x-data="{ open: false }">
    <button @click="open = !open" class="w-full px-6 py-4 text-left flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Record Payment</h2>
        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-[#6E6E73] transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    <div x-show="open" class="px-6 pb-6 border-t border-[#F2F2F7] pt-5">
        <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Amount (PKR) <span class="text-[#FF3B30]">*</span></label>
                <input type="number" name="amount" required min="1" step="0.01"
                    class="apple-input" placeholder="Amount received">
            </div>
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Payment Method <span class="text-[#FF3B30]">*</span></label>
                <select name="payment_type" required class="apple-input">
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="easypaisa">Easypaisa</option>
                    <option value="jazzcash">JazzCash</option>
                    <option value="advance">From Advance Credit</option>
                </select>
            </div>
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Payment Date <span class="text-[#FF3B30]">*</span></label>
                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" required
                    class="apple-input">
            </div>
            <div class="flex items-end">
                <button type="submit" class="btn-primary w-full justify-center">
                    Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Payments History --}}
@if($order->payments->count())
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Payments ({{ $order->payments->count() }})</h2>
    </div>
    <table class="w-full apple-table">
        <tbody>
            @foreach($order->payments as $payment)
            <tr>
                <td class="text-[#6E6E73] text-xs">{{ $payment->created_at->format('d M Y') }}</td>
                <td>
                    <span class="badge bg-green-100 text-green-700">{{ str_replace('_', ' ', $payment->payment_type) }}</span>
                </td>
                <td class="text-[#6E6E73] text-sm">{{ $payment->notes ?? '—' }}</td>
                <td class="text-right text-[#30D158] font-mono font-medium">PKR {{ number_format($payment->amount, 0) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Notes --}}
@if($order->notes)
<div class="card p-5">
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-2">Notes</p>
    <p class="text-[#1D1D1F] text-sm">{{ $order->notes }}</p>
</div>
@endif

@endsection
