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
                <td>{{ $batch->arrival_date->format('d M Y') }}</td>
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
