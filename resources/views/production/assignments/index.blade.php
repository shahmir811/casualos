@extends('layouts.app')
@section('title', 'Production Assignments')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Production Assignments</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Route designs to Naeem Pakki or Stitching Unit</p>
    </div>
    <a href="{{ route('production-assignments.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Assignment
    </a>
</div>

{{-- ── Filters ─────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('production-assignments.index') }}"
      class="card p-4 mb-5 flex flex-wrap items-end gap-4">

    {{-- Destination --}}
    <div>
        <label class="block text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Destination</label>
        <div class="flex gap-1.5 flex-wrap">
            @foreach(['' => 'All', 'naeem_pakki' => 'Naeem Pakki', 'stitching_unit' => 'Stitching Unit'] as $val => $label)
            <a href="{{ route('production-assignments.index', array_merge(request()->query(), ['destination' => $val, 'stitching_unit_id' => $val === 'naeem_pakki' ? '' : $selectedUnit])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all
                      {{ $selectedDestination === $val
                           ? ($val === 'naeem_pakki' ? 'bg-amber-100 text-amber-700 border-amber-300' : ($val === 'stitching_unit' ? 'bg-purple-100 text-purple-700 border-purple-300' : 'bg-[#1D1D1F] text-white border-[#1D1D1F]'))
                           : 'bg-white text-[#6E6E73] border-[#E8E8ED] hover:border-[#C7C7CC]' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- Stitching Unit (hidden when Naeem Pakki is selected) --}}
    @if($selectedDestination !== 'naeem_pakki')
    <div>
        <label class="block text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Stitching Unit</label>
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('production-assignments.index', array_merge(request()->query(), ['stitching_unit_id' => ''])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all
                      {{ $selectedUnit === ''
                           ? 'border-[#AF52DE] text-[#AF52DE] bg-[#F5EEFF]'
                           : 'bg-white text-[#6E6E73] border-[#E8E8ED] hover:border-[#C7C7CC]' }}">
                All
            </a>
            @foreach($stitchingUnits as $unit)
            <a href="{{ route('production-assignments.index', array_merge(request()->query(), ['stitching_unit_id' => $unit->id])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all
                      {{ $selectedUnit == $unit->id
                           ? 'border-[#AF52DE] text-[#AF52DE] bg-[#F5EEFF]'
                           : 'bg-white text-[#6E6E73] border-[#E8E8ED] hover:border-[#C7C7CC]' }}">
                Unit {{ $unit->number }} — {{ $unit->name }}
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Clear filters --}}
    @if($selectedDestination || $selectedUnit)
    <a href="{{ route('production-assignments.index') }}"
       class="text-xs text-[#86868B] hover:text-[#FF3B30] transition-colors self-end pb-1.5">
        ✕ Clear filters
    </a>
    @endif

</form>

{{-- ── Desktop table (md+) ─────────────────────────────────────────── --}}
<div class="card overflow-hidden hidden md:block">
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Assignment #</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-left">Design</th>
                    <th class="text-center">Destination</th>
                    <th class="text-center">Stitching Unit</th>
                    <th class="text-left">Date</th>
                    <th class="text-right">Total Pieces</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $a)
                @php
                    $isNP        = $a->destination === 'naeem_pakki';
                    $isNewStyleNP = $isNP && $a->npDesigns->isNotEmpty();
                    $designLabel = $isNewStyleNP
                        ? $a->npDesigns->count() . ' design' . ($a->npDesigns->count() > 1 ? 's' : '')
                        : ($a->design->name ?? '—');
                    $totalPcs    = $isNewStyleNP ? $a->npDesigns->sum('quantity') : $a->items->sum('quantity');
                    $npTotalCost = $isNewStyleNP
                        ? $a->npDesigns->sum(fn($d) => $d->quantity * (float) $d->per_piece_price)
                        : null;
                @endphp
                <tr>
                    <td class="font-medium text-[#0066CC]">PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $a->catalogue->name ?? '—' }}</td>
                    <td>
                        @if($isNewStyleNP)
                            <span class="font-medium text-[#1D1D1F]">{{ $designLabel }}</span>
                            <span class="ml-1 text-[10px] text-[#86868B]">
                                ({{ $a->npDesigns->pluck('design.name')->filter()->join(', ') }})
                            </span>
                        @else
                            {{ $designLabel }}
                        @endif
                    </td>
                    <td class="text-center">
                        @if($isNP)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                Naeem Pakki
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                Stitching Unit
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if(!$isNP && $a->stitchingUnit)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F5EEFF] text-[#AF52DE]">
                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                Unit {{ $a->stitchingUnit->number }} — {{ $a->stitchingUnit->name }}
                            </span>
                        @else
                            <span class="text-[#D2D2D7]">—</span>
                        @endif
                    </td>
                    <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $a->assignment_date->format('d M Y') }}</td>
                    <td class="text-right">
                        <span class="font-medium">{{ lacs_format($totalPcs) }} pcs</span>
                        @if($npTotalCost !== null)
                        <br><span class="text-[10px] text-[#FF9500] font-semibold">Rs. {{ lacs_format($npTotalCost) }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('production-assignments.show', $a) }}" class="text-[#0066CC] text-sm hover:underline whitespace-nowrap">View →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-[#86868B] py-12">No assignments found for the selected filters.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Mobile cards (below md) ─────────────────────────────────────── --}}
<div class="space-y-3 md:hidden">
    @forelse($assignments as $a)
    @php
        $isNP        = $a->destination === 'naeem_pakki';
        $isNewStyleNP = $isNP && $a->npDesigns->isNotEmpty();
        $designLabel = $isNewStyleNP
            ? $a->npDesigns->count() . ' design' . ($a->npDesigns->count() > 1 ? 's' : '')
            : ($a->design->name ?? '—');
        $totalPcs    = $isNewStyleNP ? $a->npDesigns->sum('quantity') : $a->items->sum('quantity');
        $npTotalCost = $isNewStyleNP
            ? $a->npDesigns->sum(fn($d) => $d->quantity * (float) $d->per_piece_price)
            : null;
    @endphp
    <div class="card p-4">
        {{-- Header row --}}
        <div class="flex items-start justify-between mb-3">
            <span class="text-[#0066CC] text-xs font-bold tracking-wide">PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }}</span>
            <span class="text-[#86868B] text-xs">{{ $a->assignment_date->format('d M Y') }}</span>
        </div>

        {{-- Catalogue + Design --}}
        <p class="text-[#86868B] text-xs mb-0.5">{{ $a->catalogue->name ?? '—' }}</p>
        <p class="text-[#1D1D1F] text-sm font-medium mb-1">
            {{ $designLabel }}
            @if($isNewStyleNP)
                <span class="text-[10px] text-[#86868B] font-normal">
                    ({{ $a->npDesigns->pluck('design.name')->filter()->join(', ') }})
                </span>
            @endif
        </p>

        {{-- Destination + Unit badges --}}
        <div class="flex flex-wrap items-center gap-2 mb-3">
            @if($isNP)
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                    Naeem Pakki
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                    Stitching Unit
                </span>
                @if($a->stitchingUnit)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-[#F5EEFF] text-[#AF52DE]">
                    <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Unit {{ $a->stitchingUnit->number }} — {{ $a->stitchingUnit->name }}
                </span>
                @endif
            @endif
        </div>

        {{-- Footer row --}}
        <div class="flex items-center justify-between">
            <div>
                <span class="text-[#1D1D1F] text-sm font-semibold">{{ lacs_format($totalPcs) }} pcs</span>
                @if($npTotalCost !== null)
                <span class="ml-2 text-xs text-[#FF9500] font-semibold">Rs. {{ lacs_format($npTotalCost) }}</span>
                @endif
            </div>
            <a href="{{ route('production-assignments.show', $a) }}" class="text-[#0066CC] text-sm font-medium hover:underline">View →</a>
        </div>
    </div>
    @empty
    <div class="card p-8 text-center text-[#86868B] text-sm">No assignments found for the selected filters.</div>
    @endforelse
</div>

<div class="mt-5">{{ $assignments->links() }}</div>

@endsection
