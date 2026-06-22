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
    <p class="text-[#6E6E73] text-sm mt-1">Designs manufactured externally</p>
</div>

@php
    use App\Models\OutsourcedBatch;
    $batches = OutsourcedBatch::with(['catalogue', 'items.design', 'loggedBy'])->latest()->get();
@endphp

@if($batches->count())
<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Batches</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ $batches->count() }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Pieces</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($batches->sum(fn($b) => $b->items->sum('total_pieces'))) }}</p>
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
                <th class="text-right">Pieces</th>
                <th class="text-left">Received Date</th>
                <th class="text-left">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $batch)
                @forelse($batch->items as $item)
                <tr>
                    @if($loop->first)
                    <td class="font-medium text-[#0066CC]" rowspan="{{ $batch->items->count() }}">OB-{{ str_pad($batch->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td class="text-[#6E6E73]" rowspan="{{ $batch->items->count() }}">{{ $batch->catalogue->name ?? '—' }}</td>
                    @endif
                    <td>{{ $item->design->name ?? '—' }}</td>
                    <td class="text-right font-medium">{{ number_format($item->total_pieces) }}</td>
                    @if($loop->first)
                    <td class="text-[#6E6E73] text-xs" rowspan="{{ $batch->items->count() }}">{{ $batch->received_date->format('d M Y') }}</td>
                    <td class="text-[#6E6E73] text-xs max-w-xs truncate" rowspan="{{ $batch->items->count() }}">{{ $batch->notes ?? '—' }}</td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td class="font-medium text-[#0066CC]">OB-{{ str_pad($batch->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $batch->catalogue->name ?? '—' }}</td>
                    <td colspan="4" class="text-[#86868B] text-xs">No items recorded</td>
                </tr>
                @endforelse
            @empty
            <tr><td colspan="6" class="text-center text-[#86868B] py-12">No outsourced batches recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
