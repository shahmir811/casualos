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
    <p class="text-[#6E6E73] text-sm mt-1">All pressed and packed pieces by design &mdash; <span class="font-medium text-[#1D1D1F]">{{ $selectedCatalogue->name }}</span></p>
</div>

@php
    $sizes       = ['xs', 's', 'm', 'l', 'xl'];
    $grandTotal  = $grouped->flatten()->sum('quantity');
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Packed</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($grandTotal) }}</p>
        <p class="text-[#86868B] text-xs mt-1">pieces across {{ $grouped->count() }} catalogue(s)</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Catalogues</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ $grouped->count() }}</p>
    </div>
</div>

@forelse($grouped as $catalogueId => $catItems)
@php
    $catName  = $catItems->first()->pressReturn->send->catalogue->name ?? 'Unknown';
    $catTotal = $catItems->sum('quantity');
    $byDesign = $catItems->groupBy('design_id');
@endphp
<div class="mb-6">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
        {{ $catName }}
        <span class="text-[#86868B] font-normal">— {{ number_format($catTotal) }} pcs total</span>
    </h2>
    <div class="card overflow-hidden">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Design</th>
                    @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byDesign as $designId => $designItems)
                @php $designTotal = $designItems->sum('quantity'); @endphp
                <tr>
                    <td class="font-medium">{{ $designItems->first()->design->name ?? '—' }}</td>
                    @foreach($sizes as $size)
                    <td class="text-right">{{ number_format($designItems->where('size', $size)->sum('quantity')) ?: '—' }}</td>
                    @endforeach
                    <td class="text-right font-bold text-[#0071E3]">{{ number_format($designTotal) }}</td>
                </tr>
                @endforeach
                <tr class="border-t border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-2 font-semibold text-xs text-[#6E6E73]" colspan="{{ count($sizes) + 1 }}">Catalogue Total</td>
                    <td class="px-5 py-2 text-right font-bold text-sm">{{ number_format($catTotal) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="card p-12 text-center text-[#86868B]">No packed inventory records found.</div>
@endforelse

@endsection
