@extends('layouts.app')
@section('title', 'Press & Pack')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Press & Pack</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Log finished pieces packed and ready for dispatch</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('packed-inventory.index') }}" class="btn-secondary">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            Packed Inventory
        </a>
        <a href="{{ route('press-pack.create') }}" class="btn-primary">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Log Pack
        </a>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Record #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-left">Packed Date</th>
                <th class="text-right">Total Pieces</th>
                <th class="text-left">Logged By</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $rec)
            <tr>
                <td class="font-medium text-[#0066CC]">PP-{{ str_pad($rec->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $rec->catalogue->name ?? '—' }}</td>
                <td>{{ $rec->design->name ?? '—' }}</td>
                <td>{{ $rec->packed_date->format('d M Y') }}</td>
                <td class="text-right font-medium">{{ number_format($rec->items->sum('quantity')) }} pcs</td>
                <td class="text-[#6E6E73] text-xs">{{ $rec->loggedBy->name ?? '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-[#86868B] py-12">No press pack records yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $records->links() }}</div>

@endsection
