@extends('layouts.app')
@section('title', 'Stitching Returns')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Stitching Returns</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Track kameez, shalwar and dupatta returns per assignment</p>
    </div>
</div>

{{-- ── Per-unit summary cards ─────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($stitchingUnits as $unit)
    @php
        $assigned  = (int) ($unitAssigned[$unit->id]->total_assigned ?? 0);
        $unitRet   = $unitReturned[$unit->id] ?? collect();
        $kRet      = (int) ($unitRet->firstWhere('component', 'kameez')?->qty  ?? 0);
        $sRet      = (int) ($unitRet->firstWhere('component', 'shalwar')?->qty ?? 0);
        $dRet      = (int) ($unitRet->firstWhere('component', 'dupatta')?->qty ?? 0);
        $allDone   = $assigned > 0 && $kRet >= $assigned && $sRet >= $assigned && $dRet >= $assigned;
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

        @if($assigned === 0)
        <p class="mt-1 text-[10px] text-[#86868B] uppercase tracking-widest">No activity yet</p>
        @else
        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <span class="text-xs text-[#86868B]">Assigned</span>
                <span class="text-sm font-semibold text-[#0066CC]">{{ number_format($assigned) }} pcs</span>
            </div>
            <div class="pt-1.5 border-t border-[#F2F2F7] space-y-1">
                @foreach(['kameez' => $kRet, 'shalwar' => $sRet, 'dupatta' => $dRet] as $comp => $ret)
                <div class="flex items-center justify-between">
                    <span class="text-[11px] text-[#86868B] capitalize">{{ $comp }}</span>
                    <span class="text-[11px] font-semibold {{ $ret >= $assigned ? 'text-[#34C759]' : ($ret > 0 ? 'text-[#FF9500]' : 'text-[#D2D2D7]') }}">
                        {{ $ret > 0 ? number_format($ret) . ' pcs' : '—' }}
                    </span>
                </div>
                @endforeach
            </div>
            @if($allDone)
            <div class="pt-1.5 border-t border-[#F2F2F7]">
                <span class="text-[10px] font-semibold text-[#34C759]">✓ All returned</span>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endforeach
</div>

{{-- ── Assignments table ──────────────────────────────────────────── --}}
<div class="card overflow-hidden">

    {{-- Mobile card list --}}
    <div class="md:hidden divide-y divide-[#F2F2F7]">
        @forelse($assignments as $a)
        @php
            $statusLabel = $a->is_complete ? 'COMPLETE' : (($a->kameez_returned + $a->shalwar_returned + $a->dupatta_returned) > 0 ? 'PARTIAL' : 'PENDING');
            $statusColor = $a->is_complete ? '#34C759' : ($statusLabel === 'PARTIAL' ? '#FF9500' : '#86868B');
            $statusBg    = $a->is_complete ? '#F0FFF4' : ($statusLabel === 'PARTIAL' ? '#FFFBF0' : '#F5F5F7');
        @endphp
        <div class="p-4 space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-sm font-semibold text-[#0066CC]">PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="color:{{ $statusColor }}; background:{{ $statusBg }};">{{ $statusLabel }}</span>
            </div>
            <p class="text-xs text-[#6E6E73]">{{ $a->catalogue->name ?? '—' }} · {{ $a->design->name ?? '—' }}</p>
            @if($a->stitchingUnit)
            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                Unit {{ $a->stitchingUnit->number }} — {{ $a->stitchingUnit->name }}
            </span>
            @endif
            <div class="flex items-center gap-3 text-xs">
                <span class="text-[#86868B]">{{ $a->total_assigned }} pcs</span>
                <span class="text-[#D2D2D7]">·</span>
                <span class="{{ $a->kameez_returned >= $a->total_assigned ? 'text-[#34C759]' : 'text-[#86868B]' }}">K: {{ $a->kameez_returned }}</span>
                <span class="{{ $a->shalwar_returned >= $a->total_assigned ? 'text-[#34C759]' : 'text-[#86868B]' }}">S: {{ $a->shalwar_returned }}</span>
                <span class="{{ $a->dupatta_returned >= $a->total_assigned ? 'text-[#34C759]' : 'text-[#86868B]' }}">D: {{ $a->dupatta_returned }}</span>
            </div>
            <a href="{{ route('stitching-assignments.show', $a) }}" class="text-[#0066CC] text-sm">View →</a>
        </div>
        @empty
        <div class="p-8 text-center text-[#86868B] text-sm">No stitching assignments yet.</div>
        @endforelse
    </div>

    {{-- Desktop table --}}
    <table class="hidden md:table w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Assignment</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-left">Stitching Unit</th>
                <th class="text-left">Date</th>
                <th class="text-right">Assigned</th>
                <th class="text-center">Components</th>
                <th class="text-center">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $a)
            @php
                $statusLabel = $a->is_complete ? 'COMPLETE' : (($a->kameez_returned + $a->shalwar_returned + $a->dupatta_returned) > 0 ? 'PARTIAL' : 'PENDING');
                $statusColor = $a->is_complete ? '#34C759' : ($statusLabel === 'PARTIAL' ? '#FF9500' : '#86868B');
                $statusBg    = $a->is_complete ? '#F0FFF4' : ($statusLabel === 'PARTIAL' ? '#FFFBF0' : '#F5F5F7');
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $a->catalogue->name ?? '—' }}</td>
                <td class="font-medium text-[#1D1D1F]">{{ $a->design->name ?? '—' }}</td>
                <td>
                    @if($a->stitchingUnit)
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Unit {{ $a->stitchingUnit->number }} — {{ $a->stitchingUnit->name }}
                    </span>
                    @else
                    <span class="text-[#D2D2D7] text-xs">—</span>
                    @endif
                </td>
                <td>{{ $a->assignment_date->format('d M Y') }}</td>
                <td class="text-right font-medium tabular-nums">{{ number_format($a->total_assigned) }} pcs</td>
                <td>
                    {{-- Component indicators: K / S / D --}}
                    <div class="flex items-center justify-center gap-1">
                        @foreach(['K' => $a->kameez_returned, 'S' => $a->shalwar_returned, 'D' => $a->dupatta_returned] as $lbl => $ret)
                        @php
                            $done = $a->total_assigned > 0 && $ret >= $a->total_assigned;
                            $partial = !$done && $ret > 0;
                        @endphp
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                              style="{{ $done ? 'background:#F0FFF4; color:#34C759;' : ($partial ? 'background:#FFFBF0; color:#FF9500;' : 'background:#F5F5F7; color:#C7C7CC;') }}">
                            {{ $lbl }}
                        </span>
                        @endforeach
                    </div>
                </td>
                <td class="text-center">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                          style="color:{{ $statusColor }}; background:{{ $statusBg }};">
                        {{ $statusLabel }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('stitching-assignments.show', $a) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-[#86868B] py-12">No stitching assignments yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $assignments->links() }}</div>

@endsection
