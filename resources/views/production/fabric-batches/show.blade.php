@extends('layouts.app')
@section('title', 'Fabric Batch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('fabric-batches.index') }}" class="text-[#0066CC] hover:underline text-sm">Fabric Batches</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">FB-{{ str_pad($fabricBatch->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Details Card --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Batch Details</h2>

            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Batch ID</p>
                <p class="text-[#1D1D1F] font-medium">FB-{{ str_pad($fabricBatch->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F] font-medium">{{ $fabricBatch->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Arrival Date</p>
                <p class="text-[#1D1D1F]">{{ $fabricBatch->arrival_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $fabricBatch->loggedBy->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged At</p>
                <p class="text-[#1D1D1F]">{{ $fabricBatch->created_at->format('d M Y, h:i A') }}</p>
            </div>
            @if($fabricBatch->notes)
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Notes</p>
                <p class="text-[#1D1D1F] text-sm">{{ $fabricBatch->notes }}</p>
            </div>
            @endif
        </div>

    </div>

    {{-- Items Table + Naeem Pakki Tracking --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Formula callout --}}
        <div class="flex items-start gap-3 px-4 py-3 bg-[#F0F7FF] border border-[#C7E0FF] rounded-xl text-sm">
            <svg class="w-4 h-4 text-[#0071E3] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
            </svg>
            <div class="text-[#1D1D1F] leading-relaxed space-y-1">
                <div>
                    Expected from embroidery =
                    <strong>{{ $fabricBatch->catalogue->qty_per_design }} per design</strong>
                    ×
                    <strong>{{ $inHouseCount }} in-house designs</strong>
                    =
                    <strong class="text-[#0071E3]">{{ number_format($expectedTotal) }} pieces</strong>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#6E6E73]">
                    <span>Received: <strong class="text-[#0071E3]">{{ number_format($totalReceivedAllBatches) }}</strong></span>
                    <span class="text-[#D2D2D7]">|</span>
                    <span>→ Naeem Pakki: <strong style="color:#FF9500">{{ number_format($totalToNaeemPakki) }}</strong></span>
                    <span class="text-[#D2D2D7]">|</span>
                    <span>→ Stitching: <strong style="color:#AF52DE">{{ number_format($totalToStitching) }}</strong></span>
                    <span class="text-[#D2D2D7]">|</span>
                    <span>Available: <strong class="{{ $availableInFactory > 0 ? 'text-[#34C759]' : 'text-[#FF3B30]' }}">{{ number_format($availableInFactory) }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Fabric pieces received --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Fabric Pieces Received (In-House)</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">Per-design breakdown · Available = total received − (Naeem Pakki + Stitching assigned)</p>
            </div>
            <div class="overflow-x-auto">
            <table class="w-full apple-table" style="min-width:640px;">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        <th class="text-right">This Batch</th>
                        <th class="text-right" style="color:#0071E3;">Total Received</th>
                        <th class="text-right" style="color:#FF9500;">→ Naeem Pakki</th>
                        <th class="text-right" style="color:#AF52DE;">→ Stitching</th>
                        <th class="text-right" style="color:#34C759;">Available</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fabricBatch->items as $item)
                    @php
                        $designId        = $item->design_id;
                        $totalRec        = (int)($receivedPerDesign[$designId] ?? 0);
                        $toNP            = (int)($npAssignedPerDesign[$designId] ?? 0);
                        $toStitch        = (int)($stitchingAssignedPerDesign[$designId] ?? 0);
                        $designAvailable = max(0, $totalRec - $toNP - $toStitch);
                    @endphp
                    <tr>
                        <td>{{ $item->design->name ?? '—' }}</td>
                        <td class="text-right font-medium">{{ number_format($item->quantity) }}</td>
                        <td class="text-right font-medium text-[#0071E3]">{{ number_format($totalRec) }}</td>
                        <td class="text-right font-medium {{ $toNP > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $toNP > 0 ? 'color:#FF9500' : '' }}">
                            {{ $toNP > 0 ? number_format($toNP) : '—' }}
                        </td>
                        <td class="text-right font-medium {{ $toStitch > 0 ? '' : 'text-[#D2D2D7]' }}" style="{{ $toStitch > 0 ? 'color:#AF52DE' : '' }}">
                            {{ $toStitch > 0 ? number_format($toStitch) : '—' }}
                        </td>
                        <td class="text-right font-semibold {{ $designAvailable > 0 ? 'text-[#34C759]' : 'text-[#D2D2D7]' }}">
                            {{ number_format($designAvailable) }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-[#86868B] py-8">No items recorded.</td>
                    </tr>
                    @endforelse
                    @if($fabricBatch->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold text-[#1D1D1F]">Total</td>
                        <td class="text-right font-bold text-[#1D1D1F]">{{ number_format($fabricBatch->items->sum('quantity')) }}</td>
                        <td class="text-right font-bold text-[#0071E3]">{{ number_format($totalReceivedAllBatches) }}</td>
                        <td class="text-right font-bold" style="color:#FF9500">{{ number_format($totalToNaeemPakki) }}</td>
                        <td class="text-right font-bold" style="color:#AF52DE">{{ number_format($totalToStitching) }}</td>
                        <td class="text-right font-bold {{ $availableInFactory > 0 ? 'text-[#34C759]' : 'text-[#FF3B30]' }}">{{ number_format($availableInFactory) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
            </div>
        </div>

        {{-- Naeem Pakki Tracking --}}
        @if($naeemPakkiAssignments->count())
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-[#1D1D1F]">Naeem Pakki (Embroidery) Tracking</h2>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Expected pieces to return from embroidery factory</p>
                </div>
                @php $totalPending = $naeemPakkiAssignments->sum('pending_qty'); @endphp
                @if($totalPending > 0)
                <span class="badge bg-orange-50 text-orange-700">{{ $totalPending }} pcs pending return</span>
                @else
                <span class="badge bg-green-50 text-green-700">All returned</span>
                @endif
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        <th class="text-right">Rate</th>
                        <th class="text-center">Sent</th>
                        <th class="text-center">Returned</th>
                        <th class="text-center">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($naeemPakkiAssignments as $np)
                    <tr>
                        <td class="font-medium text-[#1D1D1F]">{{ $np['design'] }}</td>
                        <td class="text-right text-[#6E6E73] text-xs">
                            @if($np['rate'])
                                Rs. {{ number_format($np['rate'], 0) }}/pc
                            @else
                                <span class="text-[#FF3B30]">— not set</span>
                            @endif
                        </td>
                        <td class="text-center tabular-nums text-[#FF9500]">{{ number_format($np['assigned_qty']) }}</td>
                        <td class="text-center tabular-nums {{ $np['returned_qty'] > 0 ? 'text-[#30D158]' : 'text-[#D1D1D6]' }}">
                            {{ $np['returned_qty'] ?: '—' }}
                        </td>
                        <td class="text-center">
                            @if($np['pending_qty'] > 0)
                            <span class="badge bg-orange-50 text-orange-700">{{ $np['pending_qty'] }}</span>
                            @else
                            <span class="badge bg-green-50 text-green-700">✓</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                        <td colspan="2" class="font-semibold text-[#1D1D1F] text-xs uppercase tracking-wide">Totals</td>
                        <td class="text-center font-semibold text-[#FF9500]">{{ number_format($naeemPakkiAssignments->sum('assigned_qty')) }}</td>
                        <td class="text-center font-semibold text-[#30D158]">{{ number_format($naeemPakkiAssignments->sum('returned_qty')) }}</td>
                        <td class="text-center font-bold {{ $totalPending > 0 ? 'text-orange-600' : 'text-[#30D158]' }}">
                            {{ $totalPending > 0 ? number_format($totalPending) : '✓' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

    </div>
</div>

@endsection
