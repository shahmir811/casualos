@extends('layouts.app')
@section('title', 'Dispatch History')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Dispatch History</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dispatch History</h1>
    <p class="text-[#6E6E73] text-sm mt-1">All dispatched orders and delivery records</p>
</div>

@php
    $totalDispatched = $dispatches->count();
@endphp

<div class="stat-card mb-6 inline-block">
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Dispatches</p>
    <p class="text-3xl font-light text-[#1D1D1F]">{{ $totalDispatched }}</p>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Batch #</th>
                <th class="text-left">Order #</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Catalogue</th>
                <th class="text-right">Order Amount</th>
                <th class="text-left">Dispatch Date</th>
                <th class="text-left">Cargo Doc</th>
                <th class="text-left">Address</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dispatches as $dispatch)
            <tr>
                <td class="font-medium text-[#0066CC]">{{ $dispatch->batch_number }}</td>
                <td>
                    <a href="{{ route('orders.show', $dispatch->order) }}" class="text-[#0066CC] hover:underline font-medium">
                        #{{ $dispatch->order->order_number }}
                    </a>
                </td>
                <td>{{ $dispatch->order->customer->name ?? '—' }}</td>
                <td class="text-[#6E6E73]">{{ $dispatch->order->catalogue->name ?? '—' }}</td>
                <td class="text-right font-medium">PKR {{ lacs_format($dispatch->order->total_amount, 0) }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $dispatch->dispatch_date->format('d M Y') }}</td>
                <td class="text-[#6E6E73] text-xs font-mono">{{ $dispatch->cargo_document ?? '—' }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $dispatch->shipping_address ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-[#86868B] py-12">No dispatches recorded yet.</td></tr>
            @endforelse
        </tbody>
        @if($dispatches->count())
        <tfoot>
            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                <td class="px-5 py-3 font-semibold text-sm" colspan="4">Total</td>
                <td class="px-5 py-3 text-right font-bold text-sm">PKR {{ lacs_format($dispatches->sum(fn($d) => $d->order->total_amount ?? 0), 0) }}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@endsection
