@extends('layouts.app')
@section('title', 'Production Assignment')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('production-assignments.index') }}" class="text-[#0066CC] hover:underline text-sm">Assignments</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

@php
    $dest = $productionAssignment->destination === 'naeem_pakki' ? 'Naeem Pakki' : 'Stitching Unit';
    $destColor = $productionAssignment->destination === 'naeem_pakki' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700';
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Assignment Details</h2>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Assignment ID</p>
                <p class="font-medium text-[#1D1D1F]">PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Design</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->design->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Destination</p>
                <span class="badge {{ $destColor }}">{{ $dest }}</span>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Assignment Date</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->assignment_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->loggedBy->name ?? '—' }}</p>
            </div>
        </div>

        <div class="stat-card text-center">
            <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Assigned</p>
            <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($productionAssignment->items->sum('quantity')) }}</p>
            <p class="text-[#86868B] text-xs mt-1">pieces</p>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Pieces by Size</h2>
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Size</th>
                        <th class="text-right">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productionAssignment->items as $item)
                    <tr>
                        <td class="font-medium uppercase">{{ $item->size }}</td>
                        <td class="text-right">{{ number_format($item->quantity) }} pcs</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center text-[#86868B] py-8">No size breakdown recorded.</td>
                    </tr>
                    @endforelse
                    @if($productionAssignment->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold">Total</td>
                        <td class="text-right font-bold">{{ number_format($productionAssignment->items->sum('quantity')) }} pcs</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Quick link if going to Naeem Pakki --}}
        @if($productionAssignment->destination === 'naeem_pakki')
        <div class="mt-4 p-4 bg-purple-50 border border-purple-200 rounded-xl flex items-center justify-between">
            <p class="text-sm text-purple-800">This design is routed to <strong>Naeem Pakki</strong>. Log the send when fabric is dispatched.</p>
            <a href="{{ route('naeem-pakki-sends.create') }}" class="btn-primary text-xs">Log Send →</a>
        </div>
        @else
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center justify-between">
            <p class="text-sm text-blue-800">Routed to <strong>Stitching Unit</strong>. Log stitching return when pieces come back.</p>
            <a href="{{ route('stitching-returns.create') }}" class="btn-primary text-xs">Log Return →</a>
        </div>
        @endif
    </div>
</div>

@endsection
