@extends('layouts.app')
@section('title', 'Outsourced Designs')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Outsourced Designs</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Outsourced Designs</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Designs manufactured externally &mdash; <span class="font-medium text-[#1D1D1F]">{{ $selectedCatalogue->name }}</span></p>
</div>

@if($batches->count())
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Batches</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ $batches->count() }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Pieces</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($batches->sum(fn($b) => $b->items->sum('original_quantity'))) }}</p>
    </div>
</div>
@endif

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Batch #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-right">XS</th>
                <th class="text-right">S</th>
                <th class="text-right">M</th>
                <th class="text-right">L</th>
                <th class="text-right">XL</th>
                <th class="text-right">Total</th>
                <th class="text-left">Received Date</th>
                <th class="text-left">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $batch)
            @php
                $badgeColors  = [
                    'bg-blue-50 text-blue-700',
                    'bg-violet-50 text-violet-700',
                    'bg-emerald-50 text-emerald-700',
                    'bg-rose-50 text-rose-700',
                    'bg-orange-50 text-orange-700',
                    'bg-indigo-50 text-indigo-700',
                    'bg-teal-50 text-teal-700',
                    'bg-pink-50 text-pink-700',
                ];
                $designGroups = $batch->items->groupBy('design_id');
            @endphp
                @forelse($designGroups as $designId => $sizeItems)
                @php
                    $design    = $sizeItems->first()->design;
                    $bySize    = $sizeItems->keyBy('size');
                    $qty       = fn(string $s) => (int) ($bySize[$s]->original_quantity ?? 0);
                    $rowTotal  = $qty('xs') + $qty('s') + $qty('m') + $qty('l') + $qty('xl');
                    $color     = $badgeColors[$designId % count($badgeColors)];
                @endphp
                <tr>
                    @if($loop->first)
                    <td class="font-medium text-[#0066CC]" rowspan="{{ $designGroups->count() }}">OB-{{ str_pad($batch->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td class="text-[#6E6E73]" rowspan="{{ $designGroups->count() }}">{{ $batch->catalogue->name ?? '—' }}</td>
                    @endif
                    <td><span class="inline-flex items-center text-[11px] font-medium {{ $color }} rounded px-2 py-0.5">{{ $design->name ?? '—' }}</span></td>
                    <td class="text-right text-[#6E6E73]">{{ $qty('xs') ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $qty('s') ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $qty('m') ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $qty('l') ?: '—' }}</td>
                    <td class="text-right text-[#6E6E73]">{{ $qty('xl') ?: '—' }}</td>
                    <td class="text-right font-semibold">{{ number_format($rowTotal) }}</td>
                    @if($loop->first)
                    <td class="text-[#6E6E73] text-xs" rowspan="{{ $designGroups->count() }}">{{ $batch->received_date->format('d M Y') }}</td>
                    <td class="text-[#6E6E73] text-xs max-w-xs truncate" rowspan="{{ $designGroups->count() }}">{{ $batch->notes ?? '—' }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td class="font-medium text-[#0066CC]">OB-{{ str_pad($batch->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $batch->catalogue->name ?? '—' }}</td>
                    <td colspan="9" class="text-[#86868B] text-xs">No items recorded</td>
                </tr>
                @endforelse
            @empty
            <tr><td colspan="11" class="text-center text-[#86868B] py-12">No outsourced batches recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
