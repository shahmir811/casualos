@extends('layouts.app')
@section('title', 'Tarpai Finishing')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Tarpai Finishing</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Track Kameez pieces sent for tarpai and returns</p>
    </div>
    <a href="{{ route('tarpai-sends.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Log Send
    </a>
</div>

<div class="flex items-center gap-2 mb-4">
    <a href="{{ route('tarpai-sends.index') }}"
       class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors
              {{ !$house ? 'bg-[#1D1D1F] text-white border-[#1D1D1F]' : 'bg-white text-[#1D1D1F] border-[#D2D2D7] hover:border-[#1D1D1F]' }}">
        All
    </a>
    <a href="{{ route('tarpai-sends.index', ['house' => 'rashid_bhai']) }}"
       class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors
              {{ $house === 'rashid_bhai' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-[#1D1D1F] border-[#D2D2D7] hover:border-purple-400' }}">
        Rashid Bhai
    </a>
    <a href="{{ route('tarpai-sends.index', ['house' => 'yousaf_bhai']) }}"
       class="px-4 py-1.5 rounded-full text-sm font-medium border transition-colors
              {{ $house === 'yousaf_bhai' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-[#1D1D1F] border-[#D2D2D7] hover:border-indigo-400' }}">
        Yousaf Bhai
    </a>
</div>

{{-- ── Available Pieces Summary ────────────────────────────────── --}}
@foreach($designSummary as $catGroup)
<div class="card overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-[#F2F2F7] flex items-center justify-between">
        <h3 class="text-sm font-semibold text-[#1D1D1F]">
            Returned from Tarpai — {{ $catGroup['catalogue'] }}
            @if($house)
                <span class="ml-2 badge {{ $house === 'rashid_bhai' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' }}">
                    {{ $house === 'rashid_bhai' ? 'Rashid Bhai' : 'Yousaf Bhai' }}
                </span>
            @endif
        </h3>
        <span class="text-xs text-[#86868B]">Pieces back from tarpai, ready for press</span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full apple-table" style="min-width:600px;">
        <thead>
            <tr>
                <th class="text-left">Design</th>
                @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($catGroup['designs'] as $row)
            <tr>
                <td class="font-medium text-[#1D1D1F] text-sm">{{ $row['name'] }}</td>
                @foreach($sizes as $size)
                <td class="text-right text-sm {{ $row['sizes'][$size] > 0 ? 'font-medium text-[#1D1D1F]' : 'text-[#D2D2D7]' }}">
                    {{ $row['sizes'][$size] > 0 ? number_format($row['sizes'][$size]) : '—' }}
                </td>
                @endforeach
                <td class="text-right font-bold {{ $row['total'] > 0 ? 'text-[#0071E3]' : 'text-[#D2D2D7]' }}">
                    {{ $row['total'] > 0 ? number_format($row['total']) : '—' }}
                </td>
            </tr>
            @endforeach
            @if($catGroup['designs']->count() > 1)
            <tr style="background:#F5F5F7; border-top:2px solid #E8E8ED;">
                <td class="font-semibold text-xs text-[#6E6E73]">Totals</td>
                @foreach($sizes as $size)
                <td class="text-right font-semibold text-sm">
                    {{ number_format($catGroup['designs']->sum(fn($r) => $r['sizes'][$size])) }}
                </td>
                @endforeach
                <td class="text-right font-bold text-[#0071E3]">
                    {{ number_format($catGroup['designs']->sum('total')) }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>
    </div>
</div>
@endforeach

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Send #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">House</th>
                <th class="text-left">Sent Date</th>
                <th class="text-right">Sent</th>
                <th class="text-right">Returned</th>
                <th class="text-right">Rate</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sends as $send)
            @php
                $sent        = $send->items->sum('quantity');
                $returned    = $send->returns->flatMap->items->sum('quantity');
                $outstanding = max(0, $sent - $returned);
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">TP-{{ str_pad($send->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>
                    <div class="font-medium text-[#1D1D1F] text-sm">{{ $send->catalogue->name ?? '—' }}</div>
                    @php
                        $designs = $send->items->pluck('design')->filter()->unique('id');
                        $palette = [
                            'bg-blue-100 text-blue-700',
                            'bg-purple-100 text-purple-700',
                            'bg-emerald-100 text-emerald-700',
                            'bg-amber-100 text-amber-700',
                            'bg-rose-100 text-rose-700',
                            'bg-teal-100 text-teal-700',
                            'bg-orange-100 text-orange-700',
                        ];
                    @endphp
                    @if($designs->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($designs as $design)
                        <span class="badge text-[10px] {{ $palette[$design->id % count($palette)] }}">{{ $design->name }}</span>
                        @endforeach
                    </div>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $send->tarpai_house === 'rashid_bhai' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' }}">
                        {{ $send->tarpaiHouseLabel() }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $send->sent_date->format('d M Y') }}</td>
                <td class="text-right">{{ number_format($sent) }}</td>
                <td class="text-right text-green-700">{{ number_format($returned) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">Rs. {{ number_format($send->per_piece_price, 0) }}/pc</td>
                <td>
                    @if($outstanding === 0 && $sent > 0)
                        <span class="badge bg-green-100 text-green-700">Complete</span>
                    @elseif($outstanding > 0)
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @else
                        <span class="badge bg-[#F5F5F7] text-[#6E6E73]">—</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('tarpai.gate-pass', $send) }}" target="_blank"
                           class="text-[#6E6E73] hover:text-[#1D1D1F] text-xs flex items-center gap-1" title="Print Gate Pass">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Gate Pass
                        </a>
                        <a href="{{ route('tarpai-sends.show', $send) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-[#86868B] py-12">No tarpai sends recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $sends->links() }}</div>

@endsection
