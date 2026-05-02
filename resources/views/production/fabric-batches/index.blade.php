@extends('layouts.app')
@section('title', 'Fabric Batches')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Fabric Batches</h1>
        <p class="text-[#6E6E73] text-sm mt-1">{{ $batches->total() }} total batches logged</p>
    </div>
    <a href="{{ route('fabric-batches.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Log Batch Arrival
    </a>
</div>

{{-- Per-catalogue summary cards --}}
@if($receivedPerCatalogue->count())
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    @foreach($receivedPerCatalogue as $catId => $totalReceived)
    @php
        $catName    = $batches->firstWhere('catalogue_id', $catId)?->catalogue->name ?? 'Catalogue';
        $designs    = $receivedPerDesignByCatalogue[$catId] ?? collect();
    @endphp
    <div class="card p-5">
        {{-- Catalogue header --}}
        <div class="flex items-center justify-between mb-3 pb-3 border-b border-[#F2F2F7]">
            <p class="text-xs font-semibold text-[#86868B] uppercase tracking-widest">{{ $catName }}</p>
            <span class="text-xs font-semibold text-[#0071E3]">{{ number_format($totalReceived) }} pcs total</span>
        </div>
        {{-- Per-design rows --}}
        <div class="space-y-2">
            @foreach($designs as $design)
            <div class="flex items-center justify-between">
                <p class="text-sm text-[#1D1D1F] truncate flex-1 mr-3">{{ $design->design_name }}</p>
                <p class="text-sm font-semibold text-[#0071E3] flex-shrink-0">{{ number_format($design->qty) }} pcs</p>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endif

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Batch #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Arrival Date</th>
                <th class="text-left">Designs</th>
                <th class="text-left">Total Pieces</th>
                <th class="text-left">Logged By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $batch)
            <tr>
                <td class="font-medium text-[#0066CC]">FB-{{ str_pad($batch->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $batch->catalogue->name ?? '—' }}</td>
                <td>
                    <p class="text-[#1D1D1F]">{{ $batch->arrival_date->format('d M Y') }}</p>
                    <p class="text-[10px] text-[#86868B] mt-0.5">Logged {{ $batch->created_at->diffForHumans() }}</p>
                </td>
                <td>{{ $batch->items->count() }}</td>
                <td>{{ number_format($batch->items->sum('quantity')) }} pcs</td>
                <td class="text-[#6E6E73] text-xs">{{ $batch->loggedBy->name ?? '—' }}</td>
                <td>
                    <a href="{{ route('fabric-batches.show', $batch) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">No fabric batches logged yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $batches->links() }}</div>

@endsection
