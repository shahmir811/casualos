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

    {{-- Catalogue --}}
    <div class="flex-1 min-w-[180px]">
        <label class="block text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Catalogue</label>
        <select name="catalogue_id" class="apple-input" style="padding:0.5rem 1rem;">
            <option value="">All catalogues</option>
            @foreach($openCatalogues as $cat)
            <option value="{{ $cat->id }}" {{ $selectedCatalogueId == $cat->id ? 'selected' : '' }}>
                {{ $cat->name }} ●
            </option>
            @endforeach
        </select>
    </div>

    {{-- Destination --}}
    <div>
        <label class="block text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Destination</label>
        <div class="flex gap-1.5">
            @foreach(['' => 'All', 'naeem_pakki' => 'Naeem Pakki', 'stitching_unit' => 'Stitching Unit'] as $val => $label)
            <a href="{{ route('production-assignments.index', array_merge(request()->query(), ['destination' => $val, 'stitching_unit' => $val === 'naeem_pakki' ? '' : $selectedUnit])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all
                      {{ $selectedDestination === $val
                           ? ($val === 'naeem_pakki' ? 'bg-purple-100 text-purple-700 border-purple-300' : ($val === 'stitching_unit' ? 'bg-blue-100 text-blue-700 border-blue-300' : 'bg-[#1D1D1F] text-white border-[#1D1D1F]'))
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
        <div class="flex gap-1.5">
            @foreach(['' => 'All', '1' => 'Unit 1', '2' => 'Unit 2', '3' => 'Unit 3', '4' => 'Unit 4'] as $val => $label)
            <a href="{{ route('production-assignments.index', array_merge(request()->query(), ['stitching_unit' => $val])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all
                      {{ $selectedUnit === $val
                           ? 'border-[#AF52DE] text-[#AF52DE]' . ' ' . 'bg-[#F5EEFF]'
                           : 'bg-white text-[#6E6E73] border-[#E8E8ED] hover:border-[#C7C7CC]' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Clear filters --}}
    @if($selectedDestination || $selectedUnit || $selectedCatalogueId)
    <a href="{{ route('production-assignments.index') }}"
       class="text-xs text-[#86868B] hover:text-[#FF3B30] transition-colors self-end pb-1.5">
        ✕ Clear filters
    </a>
    @endif

</form>

{{-- ── Assignments table ───────────────────────────────────────────── --}}
<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Assignment #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-left">Destination</th>
                <th class="text-left">Stitching Unit</th>
                <th class="text-left">Date</th>
                <th class="text-right">Total Pieces</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $a)
            @php
                $isNP      = $a->destination === 'naeem_pakki';
                $dest      = $isNP ? 'Naeem Pakki' : 'Stitching Unit';
                $destColor = $isNP ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700';
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $a->catalogue->name ?? '—' }}</td>
                <td>{{ $a->design->name ?? '—' }}</td>
                <td><span class="badge {{ $destColor }}">{{ $dest }}</span></td>
                <td>
                    @if(!$isNP && $a->stitching_unit)
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Unit {{ $a->stitching_unit }}
                        </span>
                    @else
                        <span class="text-[#D2D2D7]">—</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $a->assignment_date->format('d M Y') }}</td>
                <td class="text-right">{{ number_format($a->items->sum('quantity')) }} pcs</td>
                <td>
                    <a href="{{ route('production-assignments.show', $a) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
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

<div class="mt-5">{{ $assignments->links() }}</div>

@endsection
