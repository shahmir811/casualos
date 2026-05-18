@extends('layouts.app')
@section('title', 'Fabric Batches')
@section('content')

<div class="flex items-center justify-between mb-6">
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

{{-- ── Filter bar ──────────────────────────────────────────────────── --}}
<div class="card p-4 mb-6">
    <div class="flex flex-wrap items-end gap-x-6 gap-y-4">

        <form method="GET" action="{{ route('fabric-batches.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full sm:w-auto">
                <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Design</p>
                <select name="design_id"
                        onchange="this.form.submit();"
                        class="w-full sm:w-auto apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
                    <option value="">All designs</option>
                    @foreach($catalogueDesigns as $design)
                        <option value="{{ $design->id }}" @selected($design->id === $selectedDesignId)>{{ $design->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if($selectedDesignId)
            <a href="{{ route('fabric-batches.index') }}"
               class="text-xs text-[#86868B] hover:text-[#1D1D1F] whitespace-nowrap pb-2">
                × Clear design
            </a>
        @endif

    </div>
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
            <span class="text-xs font-semibold text-[#0071E3]">{{ lacs_format($totalReceived) }} pcs total</span>
        </div>
        {{-- Per-design rows --}}
        <div class="space-y-2">
            @foreach($designs as $design)
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5 flex-1 mr-3 min-w-0">
                    <p class="text-sm text-[#1D1D1F] truncate">{{ $design->design_name }}</p>
                    @if($design->needs_naeem_pakki)
                        <span class="text-[10px] font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded px-1 py-0.5 flex-shrink-0">NP</span>
                    @endif
                </div>
                <p class="text-sm font-semibold text-[#0071E3] flex-shrink-0">{{ lacs_format($design->qty) }} pcs</p>
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
                <td>
                    @php
                        $badgeColors = [
                            'bg-blue-50 text-blue-700',
                            'bg-violet-50 text-violet-700',
                            'bg-emerald-50 text-emerald-700',
                            'bg-rose-50 text-rose-700',
                            'bg-orange-50 text-orange-700',
                            'bg-indigo-50 text-indigo-700',
                            'bg-teal-50 text-teal-700',
                            'bg-pink-50 text-pink-700',
                        ];
                    @endphp
                    <div class="flex flex-col gap-1">
                        @foreach($batch->items as $item)
                            @php $color = $badgeColors[($item->design->id ?? $loop->index) % count($badgeColors)]; @endphp
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium {{ $color }} rounded px-2 py-0.5 w-fit">
                                {{ $item->design->name ?? '—' }}
                                <span class="opacity-60">· {{ lacs_format($item->quantity) }}</span>
                            </span>
                        @endforeach
                    </div>
                </td>
                <td>{{ lacs_format($batch->items->sum('quantity')) }} pcs</td>
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
