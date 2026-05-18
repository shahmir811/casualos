@extends('layouts.app')

@section('title', $customer->name)

@section('content')

<div class="flex items-start justify-between mb-7">
    <div>
        <a href="{{ route('customers.index') }}" class="text-[#0066CC] text-sm hover:underline">← Customers</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">{{ $customer->name }}</h1>
        <p class="text-[#6E6E73] text-sm mt-1">{{ $customer->city }} · {{ $customer->contact_number }} · {{ $customer->email }}</p>
    </div>
    @if(Auth::user()->role === 'admin')
    <div class="flex items-center gap-2.5">
        <a href="{{ route('customers.edit', $customer) }}" class="btn-secondary">Edit</a>
        <a href="{{ route('customers.ledger', $customer) }}" class="btn-secondary">Ledger</a>
    </div>
    @endif
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Orders</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ $customer->orders->count() }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Advance Credit</p>
        <p class="text-2xl font-light {{ $customer->advance_credit_balance > 0 ? 'text-[#30D158]' : 'text-[#1D1D1F]' }}">
            PKR {{ lacs_format($customer->advance_credit_balance, 0) }}
        </p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Customer Portal</p>
        <a href="{{ route('portal.show', $customer->portal_token) }}" target="_blank"
           class="text-[#0066CC] text-sm hover:underline">
            View Portal →
        </a>
    </div>
</div>

{{-- Recent Orders --}}
<div class="card">
    <div class="px-6 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Orders</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Order #</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-left">Amount</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customer->orders as $order)
                @php
                    $statusBadge = [
                        'received'   => 'badge bg-blue-100 text-blue-700',
                        'confirmed'  => 'badge bg-yellow-100 text-yellow-700',
                        'stitching'  => 'badge bg-orange-100 text-orange-700',
                        'dispatched' => 'badge bg-green-100 text-green-700',
                    ];
                @endphp
                <tr>
                    <td class="font-medium">#{{ $order->order_number }}</td>
                    <td class="text-[#6E6E73]">{{ $order->catalogue->name ?? '—' }}</td>
                    <td>PKR {{ lacs_format($order->total_amount, 0) }}</td>
                    <td>
                        <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">{{ $order->status }}</span>
                    </td>
                    <td class="text-[#86868B] text-xs">{{ $order->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] text-xs hover:underline">View →</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-[#86868B] py-10">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
