@extends('layouts.app')
@section('title', 'Packed Inventory')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Packed Inventory</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Packed Inventory</h1>
    <p class="text-[#6E6E73] text-sm mt-1">All pressed and packed pieces by catalogue and design</p>
</div>

@php
    $totalPieces = $records->sum(fn($r) => $r->items->sum('quantity'));
    $grouped = $records->groupBy('catalogue_id');
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Packed</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($totalPieces) }}</p>
        <p class="text-[#86868B] text-xs mt-1">pieces across {{ $records->count() }} records</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Catalogues</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ $grouped->count() }}</p>
    </div>
</div>

@forelse($grouped as $catalogueId => $catalogueRecords)
@php $catName = $catalogueRecords->first()->catalogue->name ?? 'Unknown'; @endphp
<div class="mb-6">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
        {{ $catName }}
        <span class="text-[#86868B] font-normal">— {{ number_format($catalogueRecords->sum(fn($r) => $r->items->sum('quantity'))) }} pcs total</span>
    </h2>
    <div class="card overflow-hidden">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Design</th>
                    <th class="text-left">Packed Date</th>
                    <th class="text-right">XS</th>
                    <th class="text-right">S</th>
                    <th class="text-right">M</th>
                    <th class="text-right">L</th>
                    <th class="text-right">XL</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($catalogueRecords as $rec)
                @php
                    $sizes = $rec->items->keyBy('size');
                    $total = $rec->items->sum('quantity');
                @endphp
                <tr>
                    <td class="font-medium">{{ $rec->design->name ?? '—' }}</td>
                    <td class="text-[#6E6E73] text-xs">{{ $rec->packed_date->format('d M Y') }}</td>
                    <td class="text-right">{{ $sizes['xs']->quantity ?? 0 }}</td>
                    <td class="text-right">{{ $sizes['s']->quantity ?? 0 }}</td>
                    <td class="text-right">{{ $sizes['m']->quantity ?? 0 }}</td>
                    <td class="text-right">{{ $sizes['l']->quantity ?? 0 }}</td>
                    <td class="text-right">{{ $sizes['xl']->quantity ?? 0 }}</td>
                    <td class="text-right font-bold text-[#0071E3]">{{ number_format($total) }}</td>
                </tr>
                @endforeach
                <tr class="border-t border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-2 font-semibold text-xs text-[#6E6E73]" colspan="7">Catalogue Total</td>
                    <td class="px-5 py-2 text-right font-bold text-sm">{{ number_format($catalogueRecords->sum(fn($r) => $r->items->sum('quantity'))) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="card p-12 text-center text-[#86868B]">No packed inventory records found.</div>
@endforelse

@endsection
