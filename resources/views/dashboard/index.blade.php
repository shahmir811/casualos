@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dashboard</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Welcome back, {{ Auth::user()->name }}</p>
</div>

{{-- ADMIN / ACCOUNTANT STATS --}}
@if(in_array(Auth::user()->role, ['admin', 'accountant']))
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">

    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-2">Customers</p>
        <p class="text-[#1D1D1F] text-3xl font-light">{{ $data['totalCustomers'] ?? 0 }}</p>
    </div>

    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-2">Open Catalogues</p>
        <p class="text-[#1D1D1F] text-3xl font-light">{{ $data['openCatalogues'] ?? 0 }}</p>
    </div>

    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-2">Pending Orders</p>
        <p class="text-[#1D1D1F] text-3xl font-light">{{ $data['pendingOrders'] ?? 0 }}</p>
    </div>

    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-2">Flagged Orders</p>
        <p class="text-3xl font-light {{ ($data['flaggedOrders'] ?? 0) > 0 ? 'text-[#FF3B30]' : 'text-[#1D1D1F]' }}">
            {{ $data['flaggedOrders'] ?? 0 }}
        </p>
    </div>

</div>

{{-- Recent Orders Table --}}
@if(!empty($data['recentOrders']) && $data['recentOrders']->count())
<div class="card mb-7">
    <div class="px-6 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Recent Orders</h2>
        <a href="{{ route('orders.index') }}" class="text-[#0066CC] text-xs hover:underline">View All →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Order #</th>
                    <th class="text-left">Customer</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['recentOrders'] as $order)
                @php
                    $statusBadge = [
                        'received'             => 'badge bg-blue-100 text-blue-700',
                        'confirmed'            => 'badge bg-yellow-100 text-yellow-700',
                        'stitching'            => 'badge bg-orange-100 text-orange-700',
                        'partially_dispatched' => 'badge bg-purple-100 text-purple-700',
                        'dispatched'           => 'badge bg-green-100 text-green-700',
                        'cancelled'            => 'badge bg-red-100 text-red-700',
                    ];
                @endphp
                <tr>
                    <td class="font-medium">#{{ $order->order_number }}</td>
                    <td>{{ $order->customer->name ?? '—' }}</td>
                    <td class="text-[#6E6E73]">{{ $order->catalogue->name ?? '—' }}</td>
                    <td>
                        <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                            {{ $order->status }}
                        </span>
                    </td>
                    <td class="text-[#6E6E73] text-xs">{{ $order->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endif

{{-- PRODUCTION MANAGER STATS --}}
@if(in_array(Auth::user()->role, ['admin', 'production_manager']))
<div class="card mb-7">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Open Catalogues</h2>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($data['activeCatalogues'] ?? [] as $cat)
        <a href="{{ route('catalogues.show', $cat) }}"
           class="block border border-[#E8E8ED] rounded-xl p-4 hover:border-[#0071E3] hover:shadow-sm transition-all">
            <p class="text-[#1D1D1F] text-sm font-medium mb-1">{{ $cat->name }}</p>
            <p class="text-[#6E6E73] text-xs">{{ $cat->designs->count() }} designs · {{ lacs_format($cat->qty_per_design) }} qty/design · {{ lacs_format($cat->qty_per_design * $cat->number_of_designs) }} total pcs</p>
        </a>
        @empty
        <p class="text-[#86868B] text-sm col-span-3">No open catalogues.</p>
        @endforelse
    </div>
</div>
@endif

{{-- CREATIVE HEAD VIEW --}}
@if(Auth::user()->role === 'creative_head')
<div class="card">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">All Catalogues</h2>
    </div>
    <div class="p-5 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($data['catalogues'] ?? [] as $cat)
        <a href="{{ route('catalogues.show', $cat) }}"
           class="block border border-[#E8E8ED] rounded-xl p-4 hover:border-[#0071E3] hover:shadow-sm transition-all">
            <p class="text-[#1D1D1F] text-sm font-medium mb-1">{{ $cat->name }}</p>
            <span class="badge {{ $cat->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                {{ $cat->status }}
            </span>
        </a>
        @empty
        <p class="text-[#86868B] text-sm">No catalogues found.</p>
        @endforelse
    </div>
</div>
@endif

@endsection
