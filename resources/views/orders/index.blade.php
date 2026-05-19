@extends('layouts.app')

@section('title', 'Orders')

@section('content')

{{-- ===== PAGE HEADER ===== --}}
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Orders</h1>
        <p class="text-[#6E6E73] text-sm mt-0.5">
            @if($selectedCatalogue)
                {{ $selectedCatalogue->name }} &mdash;
                {{ ($orders instanceof \Illuminate\Pagination\LengthAwarePaginator) ? $orders->total() : $orders->count() }} orders
            @else
                All catalogues — select a catalogue to see the full response sheet
            @endif
        </p>
    </div>
    @if($selectedCatalogue)
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('orders.excel') }}"
           class="btn-secondary flex items-center gap-1.5 text-sm"
           target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Excel
        </a>
        <a href="{{ route('orders.pdf') }}"
           class="btn-secondary flex items-center gap-1.5 text-sm"
           target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download Payment Sheet
        </a>
    </div>
    @endif
</div>

{{-- ===== FILTERS ===== --}}
<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div>
        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-wide mb-1">Status</label>
        <select name="status" class="apple-input" style="min-width:160px;">
            <option value="">All Statuses</option>
            <option value="received"             {{ request('status') === 'received'             ? 'selected' : '' }}>Received</option>
            <option value="confirmed"            {{ request('status') === 'confirmed'            ? 'selected' : '' }}>Confirmed</option>
            <option value="stitching"            {{ request('status') === 'stitching'            ? 'selected' : '' }}>Stitching</option>
            <option value="partially_dispatched" {{ request('status') === 'partially_dispatched' ? 'selected' : '' }}>Partially Dispatched</option>
            <option value="dispatched"           {{ request('status') === 'dispatched'           ? 'selected' : '' }}>Dispatched</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-wide mb-1">Search</label>
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Customer name or city…"
               class="apple-input" style="min-width:220px;">
    </div>
    <button type="submit" class="btn-primary self-end">Apply</button>
    @if(request()->hasAny(['status', 'search']))
        <a href="{{ route('orders.index') }}" class="text-[#0066CC] text-sm hover:underline self-end pb-2">Clear</a>
    @endif
</form>

{{-- ===== SUMMARY STRIP (shown only when a catalogue is selected) ===== --}}
@if($selectedCatalogue)
@php
    $qtyPerDesign = $selectedCatalogue->qty_per_design;
    $totalOrdered = $summary['total_pieces'];
    $remaining    = $qtyPerDesign - $totalOrdered;
    $pct          = $qtyPerDesign > 0 ? min(100, round($totalOrdered / $qtyPerDesign * 100)) : 0;
@endphp
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    {{-- Size totals --}}
    <div class="card p-4">
        <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-widest mb-3">Size Totals</p>
        <div class="grid grid-cols-3 gap-x-3 gap-y-2">
            @foreach(['XS' => $summary['xs'], 'S' => $summary['s'], 'M' => $summary['m'], 'L' => $summary['l'], 'XL' => $summary['xl']] as $size => $qty)
            <div>
                <p class="text-[10px] text-[#86868B]">{{ $size }}</p>
                <p class="text-sm font-semibold text-[#1D1D1F] tabular-nums">{{ lacs_format($qty) }}</p>
            </div>
            @endforeach
            <div>
                <p class="text-[10px] font-bold text-[#1D1D1F]">Total</p>
                <p class="text-sm font-bold text-[#1D1D1F] tabular-nums">{{ lacs_format($totalOrdered) }}</p>
            </div>
        </div>
    </div>

    {{-- Production --}}
    <div class="card p-4">
        <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-widest mb-3">Production</p>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-xs text-[#6E6E73]">Per Design Qty</span>
                <span class="text-sm font-semibold text-[#1D1D1F] tabular-nums">{{ lacs_format($qtyPerDesign) }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs text-[#6E6E73]">Ordered per design</span>
                <span class="text-sm font-semibold text-[#1D1D1F] tabular-nums">{{ lacs_format($totalOrdered) }}</span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-[#F2F2F7]">
                <span class="text-xs font-bold text-[#1D1D1F]">Left</span>
                <span class="text-sm font-bold tabular-nums {{ $remaining < 0 ? 'text-[#FF3B30]' : 'text-[#34C759]' }}">
                    {{ $remaining >= 0 ? '+' : '' }}{{ lacs_format($remaining) }}
                </span>
            </div>
        </div>
        <div class="mt-3">
            <div class="flex justify-between items-center mb-1">
                <span class="text-[10px] text-[#86868B]">Ordered</span>
                <span class="text-[10px] font-semibold {{ $pct >= 100 ? 'text-[#FF3B30]' : 'text-[#0071E3]' }}">{{ $pct }}%</span>
            </div>
            <div class="h-1.5 rounded-full bg-[#F2F2F7] overflow-hidden">
                <div class="h-full rounded-full {{ $pct >= 100 ? 'bg-[#FF3B30]' : 'bg-[#0071E3]' }}" style="width:{{ $pct }}%"></div>
            </div>
        </div>
    </div>

    {{-- Revenue --}}
    <div class="card p-4">
        <p class="text-[10px] font-bold text-[#86868B] uppercase tracking-widest mb-1">Total Revenue</p>
        <p class="text-2xl font-semibold text-[#1D1D1F] tabular-nums leading-tight mt-2">
            PKR {{ lacs_format($summary['total_bill'], 0) }}
        </p>
        <p class="text-xs text-[#86868B] mt-1">{{ ($orders instanceof \Illuminate\Pagination\LengthAwarePaginator ? $orders->total() : $orders->count()) }} orders</p>
    </div>

</div>
@endif

{{-- ===== ORDER TABLE ===== --}}
<div>
    <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full apple-table whitespace-nowrap">
                    <thead>
                        <tr>
                            <th class="text-left" style="min-width:130px;">Submitted</th>
                            <th class="text-left" style="min-width:170px;">Customer Name</th>
                            <th class="text-left" style="min-width:110px;">City</th>
                            <th class="text-center px-3" style="min-width:50px;">XS</th>
                            <th class="text-center px-3" style="min-width:50px;">S</th>
                            <th class="text-center px-3" style="min-width:50px;">M</th>
                            <th class="text-center px-3" style="min-width:50px;">L</th>
                            <th class="text-center px-3" style="min-width:50px;">XL</th>
                            <th class="text-center" style="min-width:80px;">Total Qty</th>
                            <th class="text-right" style="min-width:130px;">Total Bill</th>
                            <th class="text-center" style="min-width:96px;">Status</th>
                            <th style="min-width:56px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                        $statusBadge = [
                            'received'             => 'badge bg-blue-50 text-blue-600',
                            'confirmed'            => 'badge bg-yellow-50 text-yellow-700',
                            'stitching'            => 'badge bg-orange-50 text-orange-700',
                            'partially_dispatched' => 'badge bg-purple-100 text-purple-700',
                            'dispatched'           => 'badge bg-green-50 text-green-700',
                        ];
                        $statusLabel = [
                            'received'             => 'Received',
                            'confirmed'            => 'Confirmed',
                            'stitching'            => 'Stitching',
                            'partially_dispatched' => 'Partially Dispatched',
                            'dispatched'           => 'Dispatched',
                        ];
                        $orderList = ($orders instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            ? $orders->items()
                            : $orders->all();
                    @endphp

                    @forelse($orderList as $order)
                    @php
                        $firstItem   = $order->items->first();
                        $qxs = $firstItem?->qty_xs  ?? 0;
                        $qs  = $firstItem?->qty_s   ?? 0;
                        $qm  = $firstItem?->qty_m   ?? 0;
                        $ql  = $firstItem?->qty_l   ?? 0;
                        $qxl = $firstItem?->qty_xl  ?? 0;
                        $totalPieces = $qxs + $qs + $qm + $ql + $qxl;
                    @endphp
                    <tr class="{{ $order->is_flagged ? 'border-l-4 border-l-[#FF3B30]' : '' }}">

                        {{-- Submitted date --}}
                        <td class="text-[#6E6E73] text-xs tabular-nums">
                            {{ ($order->submitted_at ?? $order->created_at)->format('d/m/Y H:i') }}
                        </td>

                        {{-- Customer name --}}
                        <td>
                            <div class="flex items-center gap-1.5">
                                @if($order->is_flagged)
                                    <svg class="w-3 h-3 flex-shrink-0 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 6a1 1 0 011-1h10l-2 4 2 4H4a1 1 0 01-1-1V6z"/>
                                    </svg>
                                @endif
                                <span class="font-medium text-[#1D1D1F] text-sm">
                                    {{ $order->customer?->name ?? $order->submitted_name }}
                                </span>
                                <a href="{{ route('orders.invoice', $order) }}"
                                   target="_blank"
                                   title="Download Invoice"
                                   class="flex-shrink-0 text-[#FF3B30] hover:text-[#D70015] transition-colors">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L14 2.586A2 2 0 0012.586 2H4zm0 2h8v3a1 1 0 001 1h3v8H4V4zm5 5a1 1 0 00-1 1v3.586l-.293-.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l2-2a1 1 0 00-1.414-1.414l-.293.293V10a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </div>
                            @if(!$selectedCatalogue)
                            <p class="text-xs text-[#86868B]">{{ $order->catalogue->name ?? '—' }}</p>
                            @endif
                        </td>

                        {{-- City --}}
                        <td class="text-[#6E6E73] text-sm">{{ $order->submitted_city }}</td>

                        {{-- Size quantities (per design — same across all designs) --}}
                        <td class="text-center text-sm tabular-nums px-3 {{ $qxs ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qxs ?: '—' }}</td>
                        <td class="text-center text-sm tabular-nums px-3 {{ $qs  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qs  ?: '—' }}</td>
                        <td class="text-center text-sm tabular-nums px-3 {{ $qm  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qm  ?: '—' }}</td>
                        <td class="text-center text-sm tabular-nums px-3 {{ $ql  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $ql  ?: '—' }}</td>
                        <td class="text-center text-sm tabular-nums px-3 {{ $qxl ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qxl ?: '—' }}</td>

                        {{-- Total pieces --}}
                        <td class="text-center font-semibold text-[#1D1D1F] tabular-nums">
                            {{ lacs_format($totalPieces) }}
                        </td>

                        {{-- Total Bill --}}
                        <td class="text-right font-medium text-[#1D1D1F] tabular-nums">
                            PKR {{ lacs_format($order->total_amount, 0) }}
                        </td>

                        {{-- Status --}}
                        <td class="text-center">
                            <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                                {{ $statusLabel[$order->status] ?? ucfirst($order->status) }}
                            </span>
                        </td>

                        {{-- View link --}}
                        <td class="text-center">
                            <a href="{{ route('orders.show', $order) }}"
                               class="text-[#0066CC] text-sm hover:underline">View →</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="12" class="text-center text-[#86868B] py-12">
                            No orders found{{ $selectedCatalogue ? ' for ' . $selectedCatalogue->name : '' }}.
                        </td>
                    </tr>
                    @endforelse

                    {{-- Totals footer --}}
                    @if(count($orderList) > 0)
                    <tr style="background:#F5F5F7; border-top: 2px solid #E8E8ED;">
                        <td colspan="3" class="font-semibold text-[#1D1D1F] text-sm">
                            Totals — {{ count($orderList) }} orders
                        </td>
                        <td class="text-center font-bold text-[#1D1D1F] tabular-nums px-3">{{ lacs_format($summary['xs']) }}</td>
                        <td class="text-center font-bold text-[#1D1D1F] tabular-nums px-3">{{ lacs_format($summary['s']) }}</td>
                        <td class="text-center font-bold text-[#1D1D1F] tabular-nums px-3">{{ lacs_format($summary['m']) }}</td>
                        <td class="text-center font-bold text-[#1D1D1F] tabular-nums px-3">{{ lacs_format($summary['l']) }}</td>
                        <td class="text-center font-bold text-[#1D1D1F] tabular-nums px-3">{{ lacs_format($summary['xl']) }}</td>
                        <td class="text-center font-bold text-[#1D1D1F] tabular-nums">{{ lacs_format($summary['total_pieces']) }}</td>
                        <td class="text-right font-bold text-[#1D1D1F] tabular-nums">PKR {{ lacs_format($summary['total_bill'], 0) }}</td>
                        <td colspan="2"></td>
                    </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination (only shown when no catalogue filter) --}}
        @if($orders instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="mt-4">{{ $orders->appends(request()->query())->links() }}</div>
        @endif
</div>

@endsection
