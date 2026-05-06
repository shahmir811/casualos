@extends('layouts.app')
@section('title', 'Stitching Return')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('stitching-returns.index') }}" class="text-[#0066CC] hover:underline text-sm">Stitching Returns</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">SR-{{ str_pad($stitchingReturn->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-1">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Return Details</h2>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Return ID</p>
                <p class="font-medium text-[#1D1D1F]">SR-{{ str_pad($stitchingReturn->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F]">{{ $stitchingReturn->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Design</p>
                <p class="text-[#1D1D1F] font-medium">{{ $stitchingReturn->design->name ?? '—' }}</p>
            </div>
            @if($stitchingReturn->stitchingUnit)
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Stitching Unit</p>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Unit {{ $stitchingReturn->stitchingUnit->number }} — {{ $stitchingReturn->stitchingUnit->name }}
                </span>
            </div>
            @endif
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Return Date</p>
                <p class="text-[#1D1D1F]">{{ $stitchingReturn->return_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $stitchingReturn->loggedBy->name ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Pieces Returned by Size</h2>
            </div>
            <table class="w-full apple-table">
                <thead><tr><th class="text-left">Size</th><th class="text-right">Quantity</th></tr></thead>
                <tbody>
                    @forelse($stitchingReturn->items as $item)
                    <tr>
                        <td class="uppercase font-medium">{{ $item->size }}</td>
                        <td class="text-right text-green-700 font-medium">{{ $item->quantity }} pcs</td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-[#86868B] py-8">No size details recorded.</td></tr>
                    @endforelse
                    @if($stitchingReturn->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold">Total</td>
                        <td class="text-right font-bold text-green-700">{{ number_format($stitchingReturn->items->sum('quantity')) }} pcs</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
