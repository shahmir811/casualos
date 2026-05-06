@extends('layouts.app')
@section('title', 'Stitching Returns')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Stitching Returns</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Daily returns from the stitching units</p>
    </div>
    <a href="{{ route('stitching-returns.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Log Return
    </a>
</div>

{{-- ── Per-unit summary cards ─────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($stitchingUnits as $unit)
    @php
        $uData    = $unitSummary[$unit->id] ?? null;
        $pieces   = $uData ? (int) $uData->total_pieces  : 0;
        $designs  = $uData ? (int) $uData->total_designs : 0;
        $aData    = $unitAssigned[$unit->id] ?? null;
        $assigned = $aData ? (int) $aData->total_assigned    : 0;
        $pending  = max(0, $assigned - $pieces);
    @endphp
    <div class="card p-5 {{ $unit->is_active ? '' : 'opacity-60' }}">
        <div class="flex items-center gap-2 mb-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#F5EEFF;">
                <svg class="w-4 h-4" style="color:#AF52DE;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-[#1D1D1F]">Unit {{ $unit->number }}</p>
                <p class="text-[10px] text-[#86868B] leading-tight">{{ $unit->name }}</p>
            </div>
        </div>
        <div class="space-y-2">
            {{-- Assigned --}}
            <div class="flex items-center justify-between">
                <span class="text-xs text-[#86868B]">Assigned</span>
                <span class="text-sm font-semibold {{ $assigned > 0 ? 'text-[#0066CC]' : 'text-[#D2D2D7]' }}">
                    {{ $assigned > 0 ? number_format($assigned) . ' pcs' : '—' }}
                </span>
            </div>
            {{-- Returned --}}
            <div class="flex items-center justify-between">
                <span class="text-xs text-[#86868B]">Returned</span>
                <span class="text-sm font-semibold {{ $pieces > 0 ? 'text-[#34C759]' : 'text-[#D2D2D7]' }}">
                    {{ $pieces > 0 ? number_format($pieces) . ' pcs' : '—' }}
                </span>
            </div>
            {{-- Pending --}}
            @if($assigned > 0)
            <div class="flex items-center justify-between pt-1.5 border-t border-[#F2F2F7]">
                <span class="text-xs text-[#86868B]">Outstanding</span>
                <span class="text-xs font-semibold {{ $pending > 0 ? 'text-[#FF9500]' : 'text-[#34C759]' }}">
                    {{ $pending > 0 ? number_format($pending) . ' pcs' : '✓ All returned' }}
                </span>
            </div>
            @endif
        </div>
        @if($assigned === 0 && $pieces === 0)
        <p class="mt-3 text-[10px] text-[#86868B] uppercase tracking-widest">No activity yet</p>
        @endif
    </div>
    @endforeach
</div>

{{-- ── Returns table ───────────────────────────────────────────────── --}}
<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Return #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-left">Unit</th>
                <th class="text-left">Return Date</th>
                <th class="text-right">Total Pieces</th>
                <th class="text-left">Logged By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($returns as $ret)
            <tr>
                <td class="font-medium text-[#0066CC]">SR-{{ str_pad($ret->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $ret->catalogue->name ?? '—' }}</td>
                <td>{{ $ret->design->name ?? '—' }}</td>
                <td>
                    @if($ret->stitchingUnit)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Unit {{ $ret->stitchingUnit->number }} — {{ $ret->stitchingUnit->name }}
                    </span>
                    @else
                    <span class="text-[#D2D2D7] text-xs">—</span>
                    @endif
                </td>
                <td>{{ $ret->return_date->format('d M Y') }}</td>
                <td class="text-right font-medium text-green-700">{{ number_format($ret->items->sum('quantity')) }} pcs</td>
                <td class="text-[#6E6E73] text-xs">{{ $ret->loggedBy->name ?? '—' }}</td>
                <td>
                    <a href="{{ route('stitching-returns.show', $ret) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-[#86868B] py-12">No stitching returns logged yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $returns->links() }}</div>

@endsection
