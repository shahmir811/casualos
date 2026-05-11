@extends('layouts.app')
@section('title', 'Production Tracker')

@section('content')
<div class="space-y-6">

    {{-- Header + catalogue selector --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <h2 class="text-[#1D1D1F] font-semibold text-lg">Production Tracker</h2>
            <p class="text-[#6E6E73] text-sm mt-0.5">Real-time piece locations across the full production pipeline.</p>
        </div>

        <form method="GET" action="{{ route('production.tracker') }}" class="flex items-center gap-2">
            <select name="catalogue_id" onchange="this.form.submit()"
                    class="apple-input" style="width:auto; min-width:200px; padding:0.55rem 1rem;">
                @foreach($catalogues as $cat)
                <option value="{{ $cat->id }}" {{ $catalogue?->id == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                    @if($cat->status === 'open') ● @endif
                </option>
                @endforeach
            </select>
        </form>
    </div>

    @if(! $catalogue)
        <div class="card p-8 text-center text-[#86868B]">No catalogue found. Create one first.</div>
    @else

    {{-- ── SUMMARY STAT CARDS ─────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">

        @php
        $cards = [
            ['label' => 'Expected',        'value' => $summary->expectedTotal, 'color' => '#6E6E73',  'note' => '{{ $catalogue->qty_per_design }} × in-house designs'],
            ['label' => 'Fabric / Received','value' => $summary->fabricTotal,   'color' => '#0071E3',  'note' => 'Arrived in factory'],
            ['label' => 'At Naeem Pakki',   'value' => $summary->atNaeemPakki,  'color' => '#FF9500',  'note' => 'Sent, not returned'],
            ['label' => 'At Stitching',     'value' => $summary->atStitching,   'color' => '#AF52DE',  'note' => 'Waiting to be stitched'],
            ['label' => 'In Factory',       'value' => $summary->inFactory,     'color' => '#34C759',  'note' => 'Stitched, awaiting tarpai'],
            ['label' => 'At Tarpai',        'value' => $summary->atTarpai,      'color' => '#FF6B35',  'note' => 'Sent, not returned'],
            ['label' => 'Post-Tarpai',      'value' => $summary->postTarpai,    'color' => '#007AFF',  'note' => 'Waiting for press / at press'],
            ['label' => 'Packed',           'value' => $summary->packed,        'color' => '#1D1D1F',  'note' => 'Press & pack done'],
        ];
        @endphp

        @foreach($cards as $card)
        <div class="stat-card text-center px-3 py-4">
            <p class="text-[11px] font-semibold text-[#86868B] uppercase tracking-widest leading-tight mb-2">{{ $card['label'] }}</p>
            <p class="text-2xl font-light" style="color:{{ $card['color'] }}">{{ number_format($card['value']) }}</p>
        </div>
        @endforeach

    </div>

    {{-- Expected formula callout --}}
    <div class="flex items-center gap-3 px-4 py-3 bg-[#F0F7FF] border border-[#C7E0FF] rounded-xl text-sm">
        <svg class="w-4 h-4 text-[#0071E3] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
        </svg>
        <span class="text-[#1D1D1F]">
            Expected pieces =
            <strong>{{ $catalogue->qty_per_design }} per design</strong>
            ×
            <strong>{{ $designs->where('type', 'in_house')->count() }} in-house designs</strong>
            =
            <strong class="text-[#0071E3]">{{ number_format($summary->expectedTotal) }} total pieces</strong>
        </span>
    </div>

    {{-- ── PER-DESIGN TABLE ────────────────────────────────────────── --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
            <h3 class="text-sm font-semibold text-[#1D1D1F]">Per-Design Breakdown</h3>
            <span class="text-xs text-[#86868B]">{{ $designs->count() }} designs · {{ $catalogue->name }}</span>
        </div>

        <div class="overflow-x-auto">
        <table class="apple-table w-full" style="min-width:900px;">
            <thead>
                <tr>
                    <th class="text-left">Design</th>
                    <th class="text-left">Route</th>
                    <th class="text-right">Expected</th>
                    <th class="text-right">Received</th>
                    <th class="text-right" style="color:#FF9500;">Naeem Pakki</th>
                    <th class="text-right" style="color:#AF52DE;">Stitching</th>
                    <th class="text-right" style="color:#34C759;">In Factory</th>
                    <th class="text-right" style="color:#FF6B35;">Tarpai</th>
                    <th class="text-right" style="color:#007AFF;">Post-Tarpai</th>
                    <th class="text-right" style="color:#1D1D1F;">Packed</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($designs as $row)
                @php
                    $allPacked = $row->packedQty >= $row->expected && $row->expected > 0;
                    $inProgress = $row->atNaeemPakki > 0 || $row->atStitching > 0 || $row->inFactory > 0 || $row->atTarpai > 0 || $row->postTarpai > 0;
                    $notStarted = $row->fabricQty === 0 && $row->assignedQty === 0;
                @endphp
                <tr>
                    {{-- Design name --}}
                    <td>
                        <p class="font-medium text-[#1D1D1F] text-sm">{{ $row->name }}</p>
                        <p class="text-[10px] text-[#86868B] uppercase tracking-wide mt-0.5">
                            {{ $row->type === 'in_house' ? 'In-House' : 'Outsourced' }}
                        </p>
                    </td>

                    {{-- Route badge --}}
                    <td>
                        @if($row->type === 'outsourced')
                            <span class="badge" style="background:#F5F5F7;color:#6E6E73;">Outsourced</span>
                        @elseif($row->destination === 'naeem_pakki')
                            <span class="badge" style="background:#FFF5E6;color:#FF9500;">Via NP</span>
                        @elseif($row->destination === 'stitching_unit')
                            <span class="badge" style="background:#F5EEFF;color:#AF52DE;">Direct Stitch</span>
                        @else
                            <span class="badge" style="background:#F5F5F7;color:#86868B;">—</span>
                        @endif
                    </td>

                    {{-- Numbers --}}
                    <td class="text-right text-[#6E6E73] font-medium">{{ number_format($row->expected) }}</td>
                    <td class="text-right font-medium {{ $row->fabricQty > 0 ? 'text-[#0071E3]' : 'text-[#D2D2D7]' }}">
                        {{ $row->fabricQty > 0 ? number_format($row->fabricQty) : '—' }}
                    </td>
                    <td class="text-right font-medium {{ $row->atNaeemPakki > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $row->atNaeemPakki > 0 ? 'color:#FF9500' : '' }}">
                        {{ $row->atNaeemPakki > 0 ? number_format($row->atNaeemPakki) : '—' }}
                    </td>
                    <td class="text-right font-medium {{ $row->atStitching > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $row->atStitching > 0 ? 'color:#AF52DE' : '' }}">
                        {{ $row->atStitching > 0 ? number_format($row->atStitching) : '—' }}
                    </td>
                    <td class="text-right font-medium {{ $row->inFactory > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $row->inFactory > 0 ? 'color:#34C759' : '' }}">
                        {{ $row->inFactory > 0 ? number_format($row->inFactory) : '—' }}
                    </td>
                    <td class="text-right font-medium {{ $row->atTarpai > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $row->atTarpai > 0 ? 'color:#FF6B35' : '' }}">
                        {{ $row->atTarpai > 0 ? number_format($row->atTarpai) : '—' }}
                    </td>
                    <td class="text-right font-medium {{ $row->postTarpai > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $row->postTarpai > 0 ? 'color:#007AFF' : '' }}">
                        {{ $row->postTarpai > 0 ? number_format($row->postTarpai) : '—' }}
                    </td>
                    <td class="text-right font-semibold {{ $row->packedQty > 0 ? 'text-[#1D1D1F]' : 'text-[#D2D2D7]' }}">
                        {{ $row->packedQty > 0 ? number_format($row->packedQty) : '—' }}
                    </td>

                    {{-- Status badge --}}
                    <td class="text-center">
                        @if($allPacked)
                            <span class="badge" style="background:#ECFDF5;color:#059669;">✓ Packed</span>
                        @elseif($notStarted)
                            <span class="badge" style="background:#F5F5F7;color:#86868B;">Not Started</span>
                        @elseif($inProgress)
                            <span class="badge" style="background:#FFF5E6;color:#FF9500;">In Progress</span>
                        @else
                            <span class="badge" style="background:#F0F7FF;color:#0071E3;">Assigned</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="text-center text-[#86868B] py-10">No designs found for this catalogue.</td>
                </tr>
                @endforelse
            </tbody>

            {{-- Totals footer --}}
            @if($designs->count() > 1)
            <tfoot>
                <tr style="background:#F5F5F7; border-top: 2px solid #E8E8ED;">
                    <td class="font-semibold text-[#1D1D1F]" colspan="2">Totals</td>
                    <td class="text-right font-semibold text-[#6E6E73]">{{ number_format($designs->sum('expected')) }}</td>
                    <td class="text-right font-semibold text-[#0071E3]">{{ number_format($designs->sum('fabricQty')) }}</td>
                    <td class="text-right font-bold" style="color:#FF9500">{{ number_format($designs->sum('atNaeemPakki')) }}</td>
                    <td class="text-right font-bold" style="color:#AF52DE">{{ number_format($designs->sum('atStitching')) }}</td>
                    <td class="text-right font-bold" style="color:#34C759">{{ number_format($designs->sum('inFactory')) }}</td>
                    <td class="text-right font-bold" style="color:#FF6B35">{{ number_format($designs->sum('atTarpai')) }}</td>
                    <td class="text-right font-bold" style="color:#007AFF">{{ number_format($designs->sum('postTarpai')) }}</td>
                    <td class="text-right font-bold text-[#1D1D1F]">{{ number_format($designs->sum('packedQty')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>

    {{-- ── PIPELINE LEGEND ─────────────────────────────────────────── --}}
    <div class="card p-5">
        <p class="text-xs font-semibold text-[#86868B] uppercase tracking-widest mb-3">Pipeline Flow</p>
        <div class="flex flex-wrap items-center gap-2 text-xs text-[#6E6E73]">
            <span class="font-medium text-[#0071E3]">Fabric Arrives</span>
            <span>→</span>
            <span class="font-medium text-[#1D1D1F]">Assign to Route</span>
            <span>→</span>
            <span class="font-medium" style="color:#FF9500">Naeem Pakki (embroidery)</span>
            <span>→</span>
            <span class="font-medium" style="color:#AF52DE">Stitching Unit</span>
            <span>→</span>
            <span class="font-medium" style="color:#34C759">In Factory</span>
            <span>→</span>
            <span class="font-medium" style="color:#FF6B35">Tarpai (finishing)</span>
            <span>→</span>
            <span class="font-medium" style="color:#007AFF">Press (send → return)</span>
            <span>→</span>
            <span class="font-medium text-[#1D1D1F]">Packed ✓</span>
        </div>
    </div>

    @endif
</div>
@endsection
