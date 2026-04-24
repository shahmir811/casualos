@extends('layouts.app')
@section('title', 'Packed Inventory')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Packed Inventory</h1>
        <p class="text-[#6E6E73] text-sm mt-1">All pressed and packed pieces by design</p>
    </div>
    <a href="{{ route('press-pack.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Log Pack
    </a>
</div>

@php
    $grouped = $records->groupBy(fn($r) => $r->catalogue_id);
@endphp

@forelse($grouped as $catalogueId => $catalogueRecords)
@php $catName = $catalogueRecords->first()->catalogue->name ?? 'Unknown'; @endphp
<div class="mb-6">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
        {{ $catName }}
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
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="card p-12 text-center">
    <p class="text-[#86868B]">No packed inventory records yet.</p>
</div>
@endforelse

<div class="mt-5">{{ $records->links() }}</div>

@endsection
