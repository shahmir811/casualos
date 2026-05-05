@extends('layouts.app')
@section('title', 'Customer Orders')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Customer Orders</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Customer Orders</h1>
    <p class="text-[#6E6E73] text-sm mt-1">All orders grouped by customer</p>
</div>

@forelse($customers as $customer)
@if($customer->orders->count())
<div class="mb-6">
    <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-semibold text-[#1D1D1F] flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
            {{ $customer->name }}
            <span class="text-[#86868B] font-normal">— {{ $customer->orders->count() }} orders · PKR {{ number_format($customer->orders->sum('total_amount'), 0) }}</span>
        </h2>
        <a href="{{ route('customers.show', $customer) }}" class="text-[#0066CC] text-xs hover:underline">View Customer →</a>
    </div>
    <div class="card overflow-hidden">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Order #</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-right">Amount</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($customer->orders as $order)
                @php
                    $statusColor = [
                        'received'   => 'bg-blue-100 text-blue-700',
                        'confirmed'  => 'bg-yellow-100 text-yellow-700',
                        'stitching'  => 'bg-orange-100 text-orange-700',
                        'dispatched' => 'bg-green-100 text-green-700',
                    ];
                @endphp
                <tr>
                    <td><a href="{{ route('orders.show', $order) }}" class="font-medium text-[#0066CC] hover:underline">#{{ $order->order_number }}</a></td>
                    <td class="text-[#6E6E73]">{{ $order->catalogue->name ?? '—' }}</td>
                    <td class="text-right font-medium">PKR {{ number_format($order->total_amount, 0) }}</td>
                    <td><span class="badge {{ $statusColor[$order->status] ?? 'bg-[#F5F5F7] text-[#6E6E73]' }}">{{ ucfirst($order->status) }}</span></td>
                    <td class="text-[#6E6E73] text-xs">{{ $order->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@empty
<div class="card p-12 text-center text-[#86868B]">No customers found.</div>
@endforelse

@endsection
