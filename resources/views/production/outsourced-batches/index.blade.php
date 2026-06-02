@extends('layouts.app')
@section('title', 'Outsourced Batches')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Outsourced Batches</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Designs manufactured externally and received</p>
    </div>
    <a href="{{ route('outsourced-batches.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Log Batch
    </a>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Batch #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Received Date</th>
                <th class="text-left">Designs</th>
                <th class="text-left">Total Pieces</th>
                <th class="text-left">Notes</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($batches as $batch)
            <tr>
                <td class="font-medium text-[#0066CC]">OB-{{ str_pad($batch->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $batch->catalogue->name ?? '—' }}</td>
                <td>{{ $batch->received_date->format('d M Y') }}</td>
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
                        @foreach($batch->items->groupBy('design_id') as $designId => $designItems)
                        @php $color = $badgeColors[$designId % count($badgeColors)]; @endphp
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium {{ $color }} rounded px-2 py-0.5 w-fit">
                            {{ $designItems->first()->design->name ?? '—' }}
                            <span class="opacity-60">· {{ number_format($designItems->sum('original_quantity')) }}</span>
                        </span>
                        @endforeach
                    </div>
                </td>
                <td>{{ lacs_format($batch->items->sum('original_quantity')) }} pcs</td>
                <td class="text-[#6E6E73] text-xs max-w-xs truncate">{{ $batch->notes ?? '—' }}</td>
                <td>
                    <a href="{{ route('outsourced-batches.show', $batch) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">No outsourced batches recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $batches->links() }}</div>

@endsection
