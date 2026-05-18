@extends('layouts.app')
@section('title', 'Production Tracker')

@push('styles')
<style>
    .tracker-table tbody td,
    .tracker-table tfoot td { vertical-align: middle !important; }
    .tracker-table tfoot td { padding: 0.85rem 1.25rem; font-size: 0.875rem; }
    .tracker-table thead th { vertical-align: bottom; white-space: nowrap; }
    .tracker-table td.num   { white-space: nowrap; }
</style>
@endpush

@section('content')

<div class="space-y-6">

    @if(! $catalogue)
        <div class="card p-8 text-center text-[#86868B]">No catalogue found. Create one first.</div>
    @else

    @php
        $inHouseDesigns    = $designs->where('type', 'in_house')->values();
        $outsourcedDesigns = $designs->where('type', 'outsourced')->values();
        $gb = 'border-left: 2px solid #E8E8ED;'; // group separator
    @endphp

    {{-- ── IN-HOUSE PRODUCTION TABLE ─────────────────────────────── --}}
    @if($inHouseDesigns->count())
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">In-House Production</h3>
                <span class="badge" style="background:#F0F7FF;color:#0071E3;">{{ $inHouseDesigns->count() }} designs</span>
            </div>
            <span class="text-xs text-[#86868B]">{{ $catalogue->name }}</span>
        </div>

        <div class="overflow-x-auto">
        <table class="apple-table tracker-table w-full" style="min-width:1120px;">
            <thead>
                {{-- Group header row --}}
                <tr style="background:#FAFAFA; border-bottom:1px solid #E8E8ED;">
                    <th class="text-left" rowspan="2" style="min-width:130px;">Design</th>
                    <th class="text-center" rowspan="2" style="min-width:90px;">Route</th>
                    <th class="text-right" rowspan="2">Expected</th>
                    <th class="text-right" rowspan="2">Received</th>
                    <th colspan="2" class="text-center" style="color:#FF9500; {{ $gb }} border-bottom:1px solid #FFD9A0; padding-bottom:4px;">Naeem Pakki</th>
                    <th colspan="2" class="text-center" style="color:#AF52DE; {{ $gb }} border-bottom:1px solid #DFC6F5; padding-bottom:4px;">Stitching</th>
                    <th colspan="2" class="text-center" style="color:#FF6B35; {{ $gb }} border-bottom:1px solid #FFD0C0; padding-bottom:4px;">Tarpai</th>
                    <th colspan="2" class="text-center" style="color:#1D1D1F; {{ $gb }} border-bottom:1px solid #E8E8ED; padding-bottom:4px;">Press</th>
                    <th class="text-right" rowspan="2" style="{{ $gb }} color:#34C759;">Dispatched</th>
                    <th class="text-center" rowspan="2">Status</th>
                </tr>
                {{-- Sub-header row --}}
                <tr style="background:#FAFAFA;">
                    <th class="text-right" style="color:#FF9500; {{ $gb }} font-size:11px; font-weight:400; padding-top:2px;">Sent</th>
                    <th class="text-right" style="color:#FF9500; font-size:11px; font-weight:400; padding-top:2px;">Returned</th>
                    <th class="text-right" style="color:#AF52DE; {{ $gb }} font-size:11px; font-weight:400; padding-top:2px;">Sent</th>
                    <th class="text-right" style="color:#AF52DE; font-size:11px; font-weight:400; padding-top:2px;">Returned</th>
                    <th class="text-right" style="color:#FF6B35; {{ $gb }} font-size:11px; font-weight:400; padding-top:2px;">Sent</th>
                    <th class="text-right" style="color:#FF6B35; font-size:11px; font-weight:400; padding-top:2px;">Returned</th>
                    <th class="text-right" style="color:#1D1D1F; {{ $gb }} font-size:11px; font-weight:400; padding-top:2px;">Sent</th>
                    <th class="text-right" style="color:#1D1D1F; font-size:11px; font-weight:400; padding-top:2px;">Returned</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inHouseDesigns as $row)
                @php
                    $allPacked  = $row->packedQty >= $row->expected && $row->expected > 0;
                    $inProgress = $row->npSentQty > 0 || $row->stitchingSentQty > 0 || $row->tarpaiSentQty > 0;
                    $notStarted = $row->fabricQty === 0 && $row->assignedQty === 0;
                @endphp
                <tr>
                    <td><span class="font-medium text-[#1D1D1F] text-sm">{{ $row->name }}</span></td>

                    <td class="text-center">
                        @if($row->destination === 'naeem_pakki')
                            <span class="badge" style="background:#FFF5E6;color:#FF9500;">Via NP</span>
                        @elseif($row->destination === 'stitching_unit')
                            <span class="badge" style="background:#F5EEFF;color:#AF52DE;">Direct Stitch</span>
                        @else
                            <span class="text-[#D2D2D7]">—</span>
                        @endif
                    </td>

                    <td class="num text-right text-[#6E6E73] font-medium">{{ lacs_format($row->expected) }}</td>
                    <td class="num text-right font-medium {{ $row->fabricQty > 0 ? 'text-[#0071E3]' : 'text-[#D2D2D7]' }}">
                        {{ $row->fabricQty > 0 ? lacs_format($row->fabricQty) : '—' }}
                    </td>

                    {{-- Naeem Pakki --}}
                    <td class="num text-right font-medium" style="{{ $gb }} color:{{ $row->npSentQty > 0 ? '#FF9500' : '#D2D2D7' }}">
                        {{ $row->npSentQty > 0 ? lacs_format($row->npSentQty) : '—' }}
                    </td>
                    <td class="num text-right font-medium" style="color:{{ $row->npReturnedQty > 0 ? '#FF9500' : '#D2D2D7' }}">
                        {{ $row->npReturnedQty > 0 ? lacs_format($row->npReturnedQty) : '—' }}
                    </td>

                    {{-- Stitching Sent --}}
                    <td class="num text-right font-medium" style="{{ $gb }} color:{{ $row->stitchingSentQty > 0 ? '#AF52DE' : '#D2D2D7' }}">
                        {{ $row->stitchingSentQty > 0 ? lacs_format($row->stitchingSentQty) : '—' }}
                    </td>

                    {{-- Stitching Returned — per-component breakdown --}}
                    <td class="text-right" style="color:#AF52DE;">
                        @if($row->stitchingComponents->isEmpty())
                            <span class="text-[#D2D2D7]">—</span>
                        @else
                        @php $cl = ['kameez' => 'K', 'shalwar' => 'S', 'dupatta' => 'D']; @endphp
                        <div class="inline-flex flex-col items-end gap-0.5">
                            @foreach($cl as $key => $abbr)
                                @if($row->stitchingComponents->has($key))
                                <div class="flex items-center gap-1.5 text-xs leading-tight whitespace-nowrap">
                                    <span class="text-[#B0B0B5] font-normal" style="font-size:10px;">{{ $abbr }}</span>
                                    <span class="font-semibold tabular-nums" style="color:#AF52DE;">{{ lacs_format($row->stitchingComponents[$key]) }}</span>
                                </div>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </td>

                    {{-- Tarpai --}}
                    <td class="num text-right font-medium" style="{{ $gb }} color:{{ $row->tarpaiSentQty > 0 ? '#FF6B35' : '#D2D2D7' }}">
                        {{ $row->tarpaiSentQty > 0 ? lacs_format($row->tarpaiSentQty) : '—' }}
                    </td>
                    <td class="num text-right font-medium" style="color:{{ $row->tarpaiRetQty > 0 ? '#FF6B35' : '#D2D2D7' }}">
                        {{ $row->tarpaiRetQty > 0 ? lacs_format($row->tarpaiRetQty) : '—' }}
                    </td>

                    {{-- Press --}}
                    <td class="num text-right font-medium" style="{{ $gb }} color:{{ $row->pressSentQty > 0 ? '#1D1D1F' : '#D2D2D7' }}">
                        {{ $row->pressSentQty > 0 ? lacs_format($row->pressSentQty) : '—' }}
                    </td>
                    <td class="num text-right font-semibold" style="color:{{ $row->packedQty > 0 ? '#1D1D1F' : '#D2D2D7' }}">
                        {{ $row->packedQty > 0 ? lacs_format($row->packedQty) : '—' }}
                    </td>

                    {{-- Dispatched --}}
                    <td class="num text-right font-semibold" style="{{ $gb }} color:{{ $row->dispatchedQty > 0 ? '#34C759' : '#D2D2D7' }}">
                        {{ $row->dispatchedQty > 0 ? lacs_format($row->dispatchedQty) : '—' }}
                    </td>

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
                @endforeach
            </tbody>
            @if($inHouseDesigns->count() > 1)
            <tfoot>
                <tr style="background:#F5F5F7; border-top: 2px solid #E8E8ED;">
                    <td class="font-semibold text-[#1D1D1F]" colspan="2">Totals</td>
                    <td class="num text-right font-semibold text-[#6E6E73]">{{ lacs_format($inHouseDesigns->sum('expected')) }}</td>
                    <td class="num text-right font-semibold text-[#0071E3]">{{ lacs_format($inHouseDesigns->sum('fabricQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#FF9500; {{ $gb }}">{{ lacs_format($inHouseDesigns->sum('npSentQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#FF9500">{{ lacs_format($inHouseDesigns->sum('npReturnedQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#AF52DE; {{ $gb }}">{{ lacs_format($inHouseDesigns->sum('stitchingSentQty')) }}</td>
                    <td class="text-right" style="color:#AF52DE;">
                        @php
                            $tK = $inHouseDesigns->sum(fn($d) => $d->stitchingComponents->get('kameez', 0));
                            $tS = $inHouseDesigns->sum(fn($d) => $d->stitchingComponents->get('shalwar', 0));
                            $tD = $inHouseDesigns->sum(fn($d) => $d->stitchingComponents->get('dupatta', 0));
                        @endphp
                        <div class="inline-flex flex-col items-end gap-0.5">
                            @if($tK) <div class="flex items-center gap-1.5 text-xs whitespace-nowrap"><span class="text-[#B0B0B5]" style="font-size:10px;">K</span><span class="font-bold tabular-nums">{{ lacs_format($tK) }}</span></div> @endif
                            @if($tS) <div class="flex items-center gap-1.5 text-xs whitespace-nowrap"><span class="text-[#B0B0B5]" style="font-size:10px;">S</span><span class="font-bold tabular-nums">{{ lacs_format($tS) }}</span></div> @endif
                            @if($tD) <div class="flex items-center gap-1.5 text-xs whitespace-nowrap"><span class="text-[#B0B0B5]" style="font-size:10px;">D</span><span class="font-bold tabular-nums">{{ lacs_format($tD) }}</span></div> @endif
                        </div>
                    </td>
                    <td class="num text-right font-bold" style="color:#FF6B35; {{ $gb }}">{{ lacs_format($inHouseDesigns->sum('tarpaiSentQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#FF6B35">{{ lacs_format($inHouseDesigns->sum('tarpaiRetQty')) }}</td>
                    <td class="num text-right font-bold text-[#1D1D1F]" style="{{ $gb }}">{{ lacs_format($inHouseDesigns->sum('pressSentQty')) }}</td>
                    <td class="num text-right font-bold text-[#1D1D1F]">{{ lacs_format($inHouseDesigns->sum('packedQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#34C759; {{ $gb }}">{{ lacs_format($inHouseDesigns->sum('dispatchedQty')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>
    @endif

    {{-- ── OUTSOURCED DESIGNS TABLE ────────────────────────────────── --}}
    @if($outsourcedDesigns->count())
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Outsourced Designs</h3>
                <span class="badge" style="background:#F5F5F7;color:#6E6E73;">{{ $outsourcedDesigns->count() }} designs</span>
            </div>
            <span class="text-xs text-[#86868B]">{{ $catalogue->name }}</span>
        </div>

        <div class="overflow-x-auto">
        <table class="apple-table tracker-table w-full" style="min-width:680px;">
            <thead>
                <tr style="background:#FAFAFA; border-bottom:1px solid #E8E8ED;">
                    <th class="text-left" rowspan="2" style="min-width:160px;">Design</th>
                    <th class="text-right" rowspan="2">Expected</th>
                    <th class="text-right" rowspan="2">Received</th>
                    <th colspan="2" class="text-center" style="color:#FF6B35; {{ $gb }} border-bottom:1px solid #FFD0C0; padding-bottom:4px;">Tarpai</th>
                    <th colspan="2" class="text-center" style="color:#1D1D1F; {{ $gb }} border-bottom:1px solid #E8E8ED; padding-bottom:4px;">Press</th>
                    <th class="text-right" rowspan="2" style="{{ $gb }} color:#34C759;">Dispatched</th>
                    <th class="text-center" rowspan="2">Status</th>
                </tr>
                <tr style="background:#FAFAFA;">
                    <th class="text-right" style="color:#FF6B35; {{ $gb }} font-size:11px; font-weight:400; padding-top:2px;">Sent</th>
                    <th class="text-right" style="color:#FF6B35; font-size:11px; font-weight:400; padding-top:2px;">Returned</th>
                    <th class="text-right" style="color:#1D1D1F; {{ $gb }} font-size:11px; font-weight:400; padding-top:2px;">Sent</th>
                    <th class="text-right" style="color:#1D1D1F; font-size:11px; font-weight:400; padding-top:2px;">Returned</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outsourcedDesigns as $row)
                @php
                    $allPacked  = $row->packedQty >= $row->expected && $row->expected > 0;
                    $inProgress = $row->tarpaiSentQty > 0;
                    $notStarted = $row->fabricQty === 0;
                @endphp
                <tr>
                    <td><span class="font-medium text-[#1D1D1F] text-sm">{{ $row->name }}</span></td>
                    <td class="num text-right text-[#6E6E73] font-medium">{{ lacs_format($row->expected) }}</td>
                    <td class="num text-right font-medium {{ $row->fabricQty > 0 ? 'text-[#0071E3]' : 'text-[#D2D2D7]' }}">
                        {{ $row->fabricQty > 0 ? lacs_format($row->fabricQty) : '—' }}
                    </td>
                    <td class="num text-right font-medium" style="{{ $gb }} color:{{ $row->tarpaiSentQty > 0 ? '#FF6B35' : '#D2D2D7' }}">
                        {{ $row->tarpaiSentQty > 0 ? lacs_format($row->tarpaiSentQty) : '—' }}
                    </td>
                    <td class="num text-right font-medium" style="color:{{ $row->tarpaiRetQty > 0 ? '#FF6B35' : '#D2D2D7' }}">
                        {{ $row->tarpaiRetQty > 0 ? lacs_format($row->tarpaiRetQty) : '—' }}
                    </td>
                    <td class="num text-right font-medium" style="{{ $gb }} color:{{ $row->pressSentQty > 0 ? '#1D1D1F' : '#D2D2D7' }}">
                        {{ $row->pressSentQty > 0 ? lacs_format($row->pressSentQty) : '—' }}
                    </td>
                    <td class="num text-right font-semibold" style="color:{{ $row->packedQty > 0 ? '#1D1D1F' : '#D2D2D7' }}">
                        {{ $row->packedQty > 0 ? lacs_format($row->packedQty) : '—' }}
                    </td>
                    <td class="num text-right font-semibold" style="{{ $gb }} color:{{ $row->dispatchedQty > 0 ? '#34C759' : '#D2D2D7' }}">
                        {{ $row->dispatchedQty > 0 ? lacs_format($row->dispatchedQty) : '—' }}
                    </td>
                    <td class="text-center">
                        @if($allPacked)
                            <span class="badge" style="background:#ECFDF5;color:#059669;">✓ Packed</span>
                        @elseif($notStarted)
                            <span class="badge" style="background:#F5F5F7;color:#86868B;">Not Started</span>
                        @elseif($inProgress)
                            <span class="badge" style="background:#FFF5E6;color:#FF9500;">In Progress</span>
                        @else
                            <span class="badge" style="background:#F0F7FF;color:#0071E3;">Received</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            @if($outsourcedDesigns->count() > 1)
            <tfoot>
                <tr style="background:#F5F5F7; border-top: 2px solid #E8E8ED;">
                    <td class="font-semibold text-[#1D1D1F]">Totals</td>
                    <td class="num text-right font-semibold text-[#6E6E73]">{{ lacs_format($outsourcedDesigns->sum('expected')) }}</td>
                    <td class="num text-right font-semibold text-[#0071E3]">{{ lacs_format($outsourcedDesigns->sum('fabricQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#FF6B35; {{ $gb }}">{{ lacs_format($outsourcedDesigns->sum('tarpaiSentQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#FF6B35">{{ lacs_format($outsourcedDesigns->sum('tarpaiRetQty')) }}</td>
                    <td class="num text-right font-bold text-[#1D1D1F]" style="{{ $gb }}">{{ lacs_format($outsourcedDesigns->sum('pressSentQty')) }}</td>
                    <td class="num text-right font-bold text-[#1D1D1F]">{{ lacs_format($outsourcedDesigns->sum('packedQty')) }}</td>
                    <td class="num text-right font-bold" style="color:#34C759; {{ $gb }}">{{ lacs_format($outsourcedDesigns->sum('dispatchedQty')) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>
    </div>
    @endif

    {{-- ── PIPELINE LEGEND ─────────────────────────────────────────── --}}
    <div class="card p-5">
        <p class="text-xs font-semibold text-[#86868B] uppercase tracking-widest mb-3">Pipeline Flow</p>
        <div class="flex flex-wrap items-center gap-2 text-xs text-[#6E6E73]">
            <span class="font-medium text-[#0071E3]">Fabric Arrives</span>
            <span>→</span>
            <span class="font-medium text-[#1D1D1F]">Assign to Route</span>
            <span>→</span>
            <span class="font-medium" style="color:#FF9500">Naeem Pakki</span>
            <span>→</span>
            <span class="font-medium" style="color:#AF52DE">Stitching Unit</span>
            <span>→</span>
            <span class="font-medium" style="color:#34C759">In Factory</span>
            <span>→</span>
            <span class="font-medium" style="color:#FF6B35">Tarpai</span>
            <span>→</span>
            <span class="font-medium" style="color:#1D1D1F">Press</span>
            <span>→</span>
            <span class="font-medium text-[#1D1D1F]">Packed ✓</span>
            <span>→</span>
            <span class="font-medium" style="color:#34C759">Dispatched</span>
        </div>
    </div>

    @endif
</div>
@endsection
