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
@if($expectedPerCatalogue->count())
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    @foreach($expectedPerCatalogue as $catId => $expected)
    @php
        $received  = (int)($receivedPerCatalogue[$catId] ?? 0);
        $assigned  = (int)($assignedPerCatalogue[$catId] ?? 0);
        $toNP      = (int)($npPerCatalogue[$catId] ?? 0);
        $toStitch  = (int)($stitchingPerCatalogue[$catId] ?? 0);
        $available = max(0, $received - $assigned);
        $pct       = $expected > 0 ? round($received / $expected * 100) : 0;
        $catName   = $batches->firstWhere('catalogue_id', $catId)?->catalogue->name ?? 'Catalogue';
    @endphp
    <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-[#86868B] uppercase tracking-widest">{{ $catName }}</p>
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $received >= $expected && $expected > 0 ? 'bg-[#ECFDF5] text-[#059669]' : 'bg-[#FFF5E6] text-[#FF9500]' }}">
                {{ $pct }}% received
            </span>
        </div>
        {{-- Top row: Expected / Received / Remaining / Available --}}
        @php $remaining = max(0, $expected - $received); @endphp
        <div class="grid grid-cols-4 gap-2 text-center">
            <div>
                <p class="text-[10px] text-[#86868B] uppercase tracking-widest mb-0.5">Expected</p>
                <p class="text-lg font-light text-[#6E6E73]">{{ number_format($expected) }}</p>
            </div>
            <div>
                <p class="text-[10px] text-[#86868B] uppercase tracking-widest mb-0.5">Received</p>
                <p class="text-lg font-light text-[#0071E3]">{{ number_format($received) }}</p>
            </div>
            <div>
                <p class="text-[10px] text-[#86868B] uppercase tracking-widest mb-0.5">Remaining</p>
                <p class="text-lg font-light {{ $remaining > 0 ? 'text-[#FF3B30]' : 'text-[#34C759]' }}">{{ number_format($remaining) }}</p>
            </div>
            <div>
                <p class="text-[10px] text-[#86868B] uppercase tracking-widest mb-0.5">Available</p>
                <p class="text-lg font-semibold {{ $available > 0 ? 'text-[#34C759]' : 'text-[#D2D2D7]' }}">{{ number_format($available) }}</p>
            </div>
        </div>
        {{-- Progress bar --}}
        <div class="h-1.5 bg-[#F2F2F7] rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all" style="width:{{ min(100,$pct) }}%; background:#0071E3;"></div>
        </div>
        {{-- Bottom row: NP + Stitching breakdown --}}
        @if($toNP > 0 || $toStitch > 0)
        <div class="flex items-center gap-4 pt-1 border-t border-[#F2F2F7] text-xs">
            <span class="text-[#86868B]">Assigned:</span>
            @if($toNP > 0)
            <span class="flex items-center gap-1">
                <span class="inline-block w-2 h-2 rounded-full" style="background:#FF9500"></span>
                <span class="font-semibold" style="color:#FF9500">{{ number_format($toNP) }}</span>
                <span class="text-[#86868B]">Naeem Pakki</span>
            </span>
            @endif
            @if($toStitch > 0)
            <span class="flex items-center gap-1">
                <span class="inline-block w-2 h-2 rounded-full" style="background:#AF52DE"></span>
                <span class="font-semibold" style="color:#AF52DE">{{ number_format($toStitch) }}</span>
                <span class="text-[#86868B]">Stitching</span>
            </span>
            @endif
        </div>
        @endif
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
