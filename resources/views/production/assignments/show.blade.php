@extends('layouts.app')
@section('title', 'Production Assignment')
@section('content')

@php
    $isNP         = $productionAssignment->destination === 'naeem_pakki';
    $isNewStyleNP = $isNP && $productionAssignment->npDesigns->isNotEmpty();
    $destLabel    = $isNP ? 'Naeem Pakki' : 'Stitching Unit';
    $destBadge    = $isNP ? 'bg-orange-100 text-orange-700' : 'bg-purple-100 text-purple-700';

    $totalPcs = $isNewStyleNP
        ? $productionAssignment->npDesigns->sum('quantity')
        : $productionAssignment->items->sum('quantity');

    $totalCost = $isNewStyleNP
        ? $productionAssignment->npDesigns->sum(fn($d) => $d->quantity * (float) $d->per_piece_price)
        : null;
@endphp

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('production-assignments.index') }}" class="text-[#0066CC] hover:underline text-sm">Assignments</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Details sidebar ──────────────────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Assignment Details</h2>

            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Assignment ID</p>
                <p class="font-medium text-[#1D1D1F]">PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->catalogue->name ?? '—' }}</p>
            </div>

            @if(!$isNewStyleNP && $productionAssignment->design)
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Design</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->design->name }}</p>
            </div>
            @endif

            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Destination</p>
                <span class="badge {{ $destBadge }}">{{ $destLabel }}</span>
            </div>

            @if(!$isNP && $productionAssignment->stitchingUnit)
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Stitching Unit</p>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Unit {{ $productionAssignment->stitchingUnit->number }} — {{ $productionAssignment->stitchingUnit->name }}
                </span>
            </div>
            @endif

            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Assignment Date</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->assignment_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->loggedBy->name ?? '—' }}</p>
            </div>
        </div>

        {{-- Summary stat card --}}
        <div class="stat-card text-center">
            <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Assigned</p>
            <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($totalPcs) }}</p>
            <p class="text-[#86868B] text-xs mt-1">pieces</p>
            @if($totalCost !== null && $totalCost > 0)
            <div class="mt-3 pt-3 border-t border-[#E8E8ED]">
                <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Payable</p>
                <p class="text-lg font-semibold text-[#FF9500]">Rs. {{ number_format($totalCost) }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Main panel ───────────────────────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

        @if($isNewStyleNP)
        {{-- ═══ New-style NP batch: per-design breakdown ════════════════ --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-[#1D1D1F]">Designs in this Naeem Pakki Batch</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">{{ $productionAssignment->npDesigns->count() }} design(s) sent for embroidery</p>
                </div>
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        <th class="text-right">Qty Assigned</th>
                        <th class="text-right">Returned</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">Rate (Rs./pc)</th>
                        <th class="text-right">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productionAssignment->npDesigns as $npDesign)
                    @php
                        $npReturned    = $npDesign->totalReturned();
                        $npOutstanding = $npDesign->outstandingPieces();
                    @endphp
                    <tr>
                        <td class="font-medium text-[#1D1D1F]">{{ $npDesign->design->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ number_format($npDesign->quantity) }} pcs</td>
                        <td class="text-right tabular-nums text-green-700">{{ number_format($npReturned) }} pcs</td>
                        <td class="text-right tabular-nums {{ $npOutstanding > 0 ? 'text-orange-600 font-semibold' : 'text-[#86868B]' }}">
                            {{ number_format($npOutstanding) }} pcs
                        </td>
                        <td class="text-right tabular-nums text-[#6E6E73]">
                            Rs. {{ number_format((float) $npDesign->per_piece_price, 0) }}
                        </td>
                        <td class="text-right tabular-nums font-semibold" style="color:#FF9500">
                            Rs. {{ number_format($npDesign->quantity * (float) $npDesign->per_piece_price) }}
                        </td>
                        <td>
                            @if($npOutstanding > 0)
                                <span class="text-orange-500 text-xs">{{ number_format($npOutstanding) }} pending</span>
                            @else
                                <span class="text-green-600 text-xs">✓ Done</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                        <td class="font-semibold text-[#1D1D1F] text-xs uppercase tracking-wide">Total</td>
                        <td class="text-right font-bold text-[#1D1D1F]">{{ number_format($totalPcs) }} pcs</td>
                        <td class="text-right font-bold text-green-700">
                            {{ number_format($productionAssignment->npDesigns->sum(fn($d) => $d->totalReturned())) }} pcs
                        </td>
                        <td class="text-right font-bold text-orange-600">
                            {{ number_format($productionAssignment->npDesigns->sum(fn($d) => $d->outstandingPieces())) }} pcs
                        </td>
                        <td class="text-right text-[#D2D2D7]">—</td>
                        <td class="text-right font-bold text-base" style="color:#FF9500">
                            Rs. {{ number_format($totalCost) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Return status per design --}}
        @php
            $allReturned = $productionAssignment->npDesigns->every(fn($d) => $d->outstandingPieces() === 0 && $d->quantity > 0);
            $anyPending  = $productionAssignment->npDesigns->some(fn($d) => $d->outstandingPieces() > 0);
        @endphp
        <div class="p-4 {{ $allReturned ? 'bg-green-50 border-green-200' : 'bg-orange-50 border-orange-200' }} border rounded-xl flex items-center justify-between">
            @if($allReturned)
                <p class="text-sm text-green-800">All pieces have been returned from <strong>Naeem Pakki</strong>.</p>
            @else
                <p class="text-sm text-orange-800">
                    Pieces sent to <strong>Naeem Pakki</strong>. Log returns when embroidery is complete.
                </p>
                <a href="{{ route('naeem-pakki-sends.index') }}" class="btn-primary text-xs">View Returns →</a>
            @endif
        </div>

        @else
        {{-- ═══ Stitching assignment OR old-style NP: per-size breakdown ═ --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">
                    @if($isNP) Pieces Assigned (Naeem Pakki)
                    @else Pieces by Size
                    @endif
                </h2>
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Size</th>
                        <th class="text-right">Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productionAssignment->items as $item)
                    <tr>
                        <td class="font-medium uppercase">{{ $item->size === 'np' ? 'Total' : $item->size }}</td>
                        <td class="text-right">{{ number_format($item->quantity) }} pcs</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="text-center text-[#86868B] py-8">No quantities recorded.</td>
                    </tr>
                    @endforelse
                    @if($productionAssignment->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold">Total</td>
                        <td class="text-right font-bold">{{ number_format($totalPcs) }} pcs</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($isNP)
        <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl flex items-center justify-between">
            <p class="text-sm text-orange-800">Routed to <strong>Naeem Pakki</strong>. Log returns when embroidery is complete.</p>
            <a href="{{ route('naeem-pakki-sends.index') }}" class="btn-primary text-xs">View Returns →</a>
        </div>
        @else
        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-center justify-between">
            <p class="text-sm text-blue-800">Routed to <strong>Unit {{ $productionAssignment->stitchingUnit?->number }} — {{ $productionAssignment->stitchingUnit?->name }}</strong>. Log stitching return when pieces come back.</p>
            <a href="{{ route('stitching-assignments.show', $productionAssignment) }}" class="btn-primary text-xs">Log Return →</a>
        </div>
        @endif

        @endif

    </div>
</div>

@endsection
