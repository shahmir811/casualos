@extends('layouts.app')
@section('title', 'Fabric Batch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('fabric-batches.index') }}" class="text-[#0066CC] hover:underline text-sm">Fabric Batches</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">FB-{{ str_pad($fabricBatch->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Details Card --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Batch Details</h2>

            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Batch ID</p>
                <p class="text-[#1D1D1F] font-medium">FB-{{ str_pad($fabricBatch->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F] font-medium">{{ $fabricBatch->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Arrival Date</p>
                <p class="text-[#1D1D1F]">{{ $fabricBatch->arrival_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $fabricBatch->loggedBy->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged At</p>
                <p class="text-[#1D1D1F]">{{ $fabricBatch->created_at->format('d M Y, h:i A') }}</p>
            </div>
            @if($fabricBatch->notes)
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Notes</p>
                <p class="text-[#1D1D1F] text-sm">{{ $fabricBatch->notes }}</p>
            </div>
            @endif
        </div>

        {{-- Summary stat --}}
        <div class="stat-card text-center">
            <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Pieces</p>
            <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($fabricBatch->items->sum('total_pieces')) }}</p>
            <p class="text-[#86868B] text-xs mt-1">across {{ $fabricBatch->items->count() }} designs</p>
        </div>
    </div>

    {{-- Items Table --}}
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Fabric Pieces by Design</h2>
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        <th class="text-right">Total Pieces</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fabricBatch->items as $item)
                    <tr>
                        <td>{{ $item->design->name ?? '—' }}</td>
                        <td class="text-right font-medium">{{ number_format($item->total_pieces) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center text-[#86868B] py-8">No items recorded.</td>
                    </tr>
                    @endforelse
                    @if($fabricBatch->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold text-[#1D1D1F]">Total</td>
                        <td class="text-right font-bold text-[#1D1D1F]">{{ number_format($fabricBatch->items->sum('total_pieces')) }} pcs</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
