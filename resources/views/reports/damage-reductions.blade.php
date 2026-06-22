@extends('layouts.app')
@section('title', 'Damage & Reductions')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Damage &amp; Reductions</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Damage &amp; Reductions</h1>
    <p class="text-[#6E6E73] text-sm mt-1">All order reductions due to damage, short delivery, or adjustments</p>
</div>

@php
    $totalReduced = $reductions->sum(fn($r) => $r->items->sum('reduction_amount'));
    $totalPieces  = $reductions->sum(fn($r) => $r->items->sum('qty_reduced'));
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Reductions</p>
        <p class="text-3xl font-light text-[#FF3B30]">{{ $reductions->count() }}</p>
        <p class="text-[#86868B] text-xs mt-1">records</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Amount Reduced</p>
        <p class="text-3xl font-light text-[#FF3B30]">PKR {{ number_format($totalReduced, 0) }}</p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Reduction #</th>
                <th class="text-left">Order</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Designs Affected</th>
                <th class="text-right">Amount Reduced</th>
                <th class="text-left">By</th>
                <th class="text-left">Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reductions as $reduction)
            <tr>
                <td class="font-medium text-[#0066CC]">OR-{{ str_pad($reduction->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>
                    <a href="{{ route('orders.show', $reduction->order) }}" class="text-[#0066CC] hover:underline font-medium">
                        #{{ $reduction->order->order_number }}
                    </a>
                </td>
                <td>{{ $reduction->order->customer->name ?? '—' }}</td>
                <td class="text-[#6E6E73]">{{ $reduction->order->catalogue->name ?? '—' }}</td>
                <td>
                    <div class="space-y-0.5">
                        @foreach($reduction->items as $item)
                        <p class="text-xs text-[#6E6E73]">{{ $item->design->name ?? '—' }}
                            <span class="text-[#86868B]">— {{ $item->qty_reduced ?? 0 }} pcs</span>
                        </p>
                        @endforeach
                    </div>
                </td>
                <td class="text-right font-semibold text-[#FF3B30]">PKR {{ number_format($reduction->items->sum('reduction_amount'), 0) }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $reduction->reducedBy->name ?? '—' }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $reduction->created_at->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="8" class="text-center text-[#86868B] py-12">No damage reductions recorded.</td></tr>
            @endforelse
        </tbody>
        @if($reductions->count())
        <tfoot>
            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                <td class="px-5 py-3 font-semibold text-sm" colspan="5">Total</td>
                <td class="px-5 py-3 text-right font-bold text-sm text-[#FF3B30]">PKR {{ number_format($totalReduced, 0) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@endsection
