@extends('layouts.app')

@section('title', 'Receivables by Bank')

@section('content')

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="{{ route('reports.index') }}" class="text-[#0066CC] text-sm hover:underline">← Reports</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Receivables by Bank</h1>
        <p class="text-[#6E6E73] text-sm mt-0.5">
            {{ $selectedCatalogue->name }} — outstanding balances grouped by assigned bank
        </p>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('reports.receivables-by-bank.excel') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium text-white transition-colors"
           style="background:#16A34A;"
           target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download Excel
        </a>
        <a href="{{ route('reports.receivables-by-bank.pdf') }}"
           class="btn-secondary inline-flex items-center gap-1.5 text-sm"
           target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download PDF
        </a>
    </div>
</div>

@if(empty($rows))

<div class="card p-12 text-center">
    <p class="text-[#6E6E73] text-sm">No orders found for this catalogue.</p>
</div>

@else

@php
$bankColors = [
    'Ehsan SB' => ['bg' => '#F3E8FF', 'text' => '#7E22CE'],
    'HBL'      => ['bg' => '#DCFCE7', 'text' => '#15803D'],
    'Meezan'   => ['bg' => '#DBEAFE', 'text' => '#1D4ED8'],
    'Adnan'    => ['bg' => '#FFEDD5', 'text' => '#C2410C'],
    'Saleem'   => ['bg' => '#E0E7FF', 'text' => '#4338CA'],
    'Farhan'   => ['bg' => '#CCFBF1', 'text' => '#0F766E'],
    'Osama'    => ['bg' => '#FFE4E6', 'text' => '#BE123C'],
    'Akram'    => ['bg' => '#FEF3C7', 'text' => '#B45309'],
];
@endphp

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-xs whitespace-nowrap border-collapse" style="font-family: 'SF Pro Text', Helvetica Neue, Arial, sans-serif;">

            <thead>
                <tr style="background:#1D1D1F; color:#fff;">
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:36px;">#</th>
                    <th class="px-2 py-2 text-left font-semibold border border-[#333]" style="min-width:160px;">Customer Name</th>
                    <th class="px-2 py-2 text-left font-semibold border border-[#333]" style="min-width:90px;">City</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:100px; background:#D97706; color:#fff;">Receivable</th>
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:100px;">Title Given</th>
                    @foreach($banks as $bank)
                        @php $bc = $bankColors[$bank->title] ?? ['bg' => '#F5F5F7', 'text' => '#1D1D1F']; @endphp
                        <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:100px;">
                            <span style="display:inline-block; background:{{ $bc['bg'] }}; color:{{ $bc['text'] }}; border-radius:3px; padding:1px 5px; font-size:10px; font-weight:700;">
                                {{ strtoupper($bank->title) }}
                            </span>
                        </th>
                    @endforeach
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px;">Misc</th>
                </tr>
            </thead>

            <tbody>
                @foreach($rows as $i => $row)
                <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-[#F9F9F9]' }} hover:bg-[#F0F4FF] transition-colors">
                    <td class="px-2 py-1.5 text-center text-[#86868B] border border-[#E8E8ED]">{{ $i + 1 }}</td>
                    <td class="px-2 py-1.5 text-left font-medium text-[#1D1D1F] border border-[#E8E8ED]">{{ $row['name'] }}</td>
                    <td class="px-2 py-1.5 text-left text-[#6E6E73] border border-[#E8E8ED]">{{ $row['city'] ?: '—' }}</td>

                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold border border-[#E8E8ED]" style="background:#FFFBEB;">
                        @if($row['receivable'] > 0)
                            <span style="color:#DC2626;">{{ lacs_format($row['receivable']) }}</span>
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>

                    <td class="px-2 py-1.5 text-center border border-[#E8E8ED]">
                        @if($row['title_given'])
                            @php $bc = $bankColors[$row['title_given']] ?? null; @endphp
                            @if($bc)
                                <span style="display:inline-block; background:{{ $bc['bg'] }}; color:{{ $bc['text'] }}; border-radius:3px; padding:1px 5px; font-size:10px; font-weight:600;">
                                    {{ $row['title_given'] }}
                                </span>
                            @else
                                {{ $row['title_given'] }}
                            @endif
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>

                    @foreach($banks as $bank)
                    <td class="px-2 py-1.5 text-right tabular-nums border border-[#E8E8ED]">
                        @if(($row['bank_rcv'][$bank->id] ?? 0) > 0)
                            <span style="color:#DC2626; font-weight:600;">{{ lacs_format($row['bank_rcv'][$bank->id]) }}</span>
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                    @endforeach

                    <td class="px-2 py-1.5 text-right tabular-nums border border-[#E8E8ED]">
                        @if($row['misc'] > 0)
                            <span style="color:#DC2626; font-weight:600;">{{ lacs_format($row['misc']) }}</span>
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr style="background:#E5E5EA; border-top:2px solid #1D1D1F;">
                    <td class="px-2 py-2 text-center font-bold text-[#1D1D1F] border border-[#C7C7CC]">{{ count($rows) }}</td>
                    <td class="px-2 py-2 text-left font-bold text-[#1D1D1F] border border-[#C7C7CC]">Total</td>
                    <td class="px-2 py-2 border border-[#C7C7CC]"></td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="background:#FEF3C7; color:#DC2626;">
                        {{ lacs_format($grandReceivable) }}
                    </td>
                    <td class="px-2 py-2 border border-[#C7C7CC]"></td>
                    @foreach($banks as $bank)
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="color:#DC2626;">
                        @if(($bankReceivables[$bank->id] ?? 0) > 0)
                            {{ lacs_format($bankReceivables[$bank->id]) }}
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                    @endforeach
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="color:#DC2626;">
                        {{ $miscReceivable > 0 ? lacs_format($miscReceivable) : '—' }}
                    </td>
                </tr>
            </tfoot>

        </table>
    </div>
</div>

<p class="text-xs text-[#86868B] mt-3">
    Receivable = outstanding balance still to be collected. Amount is placed in the bank column matching the assigned Title Given.
    Misc = outstanding balance for orders with no assigned bank.
</p>

@endif

@endsection
