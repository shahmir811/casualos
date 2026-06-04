@extends('layouts.app')
@section('title', 'Packed Inventory')
@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Packed Inventory</h1>
    <p class="text-[#6E6E73] text-sm mt-1">All packed pieces ready for dispatch — in-house (from press) and outsourced</p>
</div>

@php
    $grandTotal = 0;
    foreach ($data as $catDesigns) {
        foreach ($catDesigns as $sizeQtys) {
            $grandTotal += array_sum($sizeQtys);
        }
    }
@endphp

<div class="grid grid-cols-2 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Packed</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ lacs_format($grandTotal) }}</p>
        <p class="text-[#86868B] text-xs mt-1">pieces across {{ count($data) }} catalogue(s)</p>
    </div>
</div>

@forelse($data as $catalogueId => $designs)
@php
    $catName     = $catalogueNames[$catalogueId] ?? 'Unknown';
    $catTotal    = 0;
    $catBySize   = [];
    foreach ($sizes as $size) { $catBySize[$size] = 0; }
    foreach ($designs as $sizeQtys) {
        $catTotal += array_sum($sizeQtys);
        foreach ($sizes as $size) {
            $catBySize[$size] += $sizeQtys[$size] ?? 0;
        }
    }
@endphp
<div class="mb-6">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
        {{ $catName }}
        <span class="text-[#86868B] font-normal">— {{ lacs_format($catTotal) }} pcs total</span>
    </h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full apple-table min-w-[480px]">
            <thead>
                <tr>
                    <th class="text-left">Design</th>
                    @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($designs as $designId => $sizeQtys)
                @php $designTotal = array_sum($sizeQtys); @endphp
                <tr>
                    <td class="font-medium">{{ $designNames[$designId] ?? '—' }}</td>
                    @foreach($sizes as $size)
                    <td class="text-right">{{ ($sizeQtys[$size] ?? 0) > 0 ? lacs_format($sizeQtys[$size]) : '—' }}</td>
                    @endforeach
                    <td class="text-right font-bold text-[#0071E3]">{{ lacs_format($designTotal) }}</td>
                </tr>
                @endforeach
                <tr class="border-t border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-2 font-semibold text-xs text-[#6E6E73]">Catalogue Total</td>
                    @foreach($sizes as $size)
                    <td class="px-5 py-2 text-right font-semibold text-sm">{{ $catBySize[$size] > 0 ? lacs_format($catBySize[$size]) : '—' }}</td>
                    @endforeach
                    <td class="px-5 py-2 text-right font-bold text-sm">{{ lacs_format($catTotal) }}</td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
</div>
@empty
<div class="card p-12 text-center">
    <p class="text-[#86868B]">No packed inventory yet. Log press returns or outsourced batch arrivals to build inventory.</p>
</div>
@endforelse

@endsection
