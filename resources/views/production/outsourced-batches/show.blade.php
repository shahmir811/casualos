@extends('layouts.app')
@section('title', 'Outsourced Batch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('outsourced-batches.index') }}" class="text-[#0066CC] hover:underline text-sm">Outsourced Batches</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">OB-{{ str_pad($outsourcedBatch->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Batch Details</h2>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Batch ID</p><p class="font-medium text-[#1D1D1F]">OB-{{ str_pad($outsourcedBatch->id, 4, '0', STR_PAD_LEFT) }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p><p class="text-[#1D1D1F]">{{ $outsourcedBatch->catalogue->name ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Received Date</p><p class="text-[#1D1D1F]">{{ $outsourcedBatch->received_date->format('d M Y') }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p><p class="text-[#1D1D1F]">{{ $outsourcedBatch->loggedBy->name ?? '—' }}</p></div>
            @if($outsourcedBatch->notes)
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Notes</p><p class="text-[#1D1D1F] text-sm">{{ $outsourcedBatch->notes }}</p></div>
            @endif
        </div>

        <div class="stat-card text-center">
            <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Pieces</p>
            <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($outsourcedBatch->items->sum('total_pieces')) }}</p>
            <p class="text-[#86868B] text-xs mt-1">across {{ $outsourcedBatch->items->count() }} designs</p>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Pieces by Design</h2>
            </div>
            <table class="w-full apple-table">
                <thead><tr><th class="text-left">Design</th><th class="text-right">Total Pieces</th></tr></thead>
                <tbody>
                    @forelse($outsourcedBatch->items as $item)
                    <tr>
                        <td>{{ $item->design->name ?? '—' }}</td>
                        <td class="text-right font-medium">{{ number_format($item->total_pieces) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-[#86868B] py-8">No items recorded.</td></tr>
                    @endforelse
                    @if($outsourcedBatch->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold">Total</td>
                        <td class="text-right font-bold">{{ number_format($outsourcedBatch->items->sum('total_pieces')) }} pcs</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
