@extends('layouts.app')

@section('title', 'Bank Collection Report')

@section('content')

<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <a href="{{ route('reports.index') }}" class="text-[#0066CC] text-sm hover:underline">← Reports</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Bank Collection Report</h1>
        <p class="text-[#6E6E73] text-sm mt-0.5">
            @if($selectedCatalogue)
                {{ $selectedCatalogue->name }} — collected vs expected per bank account
            @else
                Select a catalogue from the sidebar to view the report
            @endif
        </p>
    </div>
    @if($selectedCatalogue)
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('reports.bank-collection.excel') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium text-white transition-colors"
           style="background:#16A34A;"
           target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download Excel
        </a>
        <a href="{{ route('reports.bank-collection.pdf') }}"
           class="btn-secondary inline-flex items-center gap-1.5 text-sm"
           target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download PDF
        </a>
    </div>
    @endif
</div>

@if(!$selectedCatalogue)

<div class="card p-12 text-center">
    <svg class="w-12 h-12 text-[#C7C7CC] mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
    </svg>
    <p class="text-[#6E6E73] text-sm">No catalogue selected. Use the sidebar to select a catalogue.</p>
</div>

@elseif(empty($rows))

<div class="card p-12 text-center">
    <p class="text-[#6E6E73] text-sm">No orders found for this catalogue.</p>
</div>

@else

@php
// Compute totals across all rows
$totXs = $totS = $totM = $totL = $totXl = $totTotalQty = $totOverAllQty = 0;
$totBankPayments = [];
$totMisc = 0;
foreach ($banks as $bank) {
    $totBankPayments[$bank->id] = 0;
}
foreach ($rows as $row) {
    $totXs         += $row['qty_xs'];
    $totS          += $row['qty_s'];
    $totM          += $row['qty_m'];
    $totL          += $row['qty_l'];
    $totXl         += $row['qty_xl'];
    $totTotalQty   += $row['total_qty'];
    $totOverAllQty += $row['over_all_qty'];
    $totMisc       += $row['misc'];
    foreach ($banks as $bank) {
        $totBankPayments[$bank->id] += ($row['bank_payments'][$bank->id] ?? 0);
    }
}

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
                    {{-- Fixed left columns --}}
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:36px;">#</th>
                    <th class="px-2 py-2 text-left font-semibold border border-[#333]" style="min-width:160px;">Customer Name</th>
                    <th class="px-2 py-2 text-left font-semibold border border-[#333]" style="min-width:90px;">City</th>

                    {{-- Size columns --}}
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:44px;">XS</th>
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:44px;">S</th>
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:44px;">M</th>
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:44px;">L</th>
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:44px;">XL</th>

                    {{-- Qty & Rate --}}
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:58px;">Total<br>Qty</th>
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:68px;">Over All<br>Total Qty</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:70px;">Rate</th>

                    {{-- Financial highlight columns --}}
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px; background:#D97706; color:#fff;">Total Bill</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px; background:#16A34A; color:#fff;">Amt Received</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px; background:#D97706; color:#fff;">Amt Receivable</th>

                    {{-- Bank assignment --}}
                    <th class="px-2 py-2 text-center font-semibold border border-[#333]" style="min-width:100px;">Title Given</th>

                    {{-- Per-bank columns --}}
                    @foreach($banks as $bank)
                    @php $bc = $bankColors[$bank->title] ?? ['bg' => '#F5F5F7', 'text' => '#1D1D1F']; @endphp
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px;">
                        <span style="display:inline-block; background:{{ $bc['bg'] }}; color:{{ $bc['text'] }}; border-radius:3px; padding:1px 5px; font-size:10px; font-weight:700;">
                            {{ strtoupper($bank->title) }}
                        </span>
                    </th>
                    @endforeach

                    {{-- Misc and right-side summary --}}
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px; border-left:2px solid #0071E3 !important;">Misc /<br>Prev. Bal.</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px;">Amt<br>Received</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px;">Amt<br>Receivable</th>
                    <th class="px-2 py-2 text-right font-semibold border border-[#333]" style="min-width:90px;">Total Bill</th>
                </tr>
            </thead>

            <tbody>
                @foreach($rows as $i => $row)
                <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-[#F9F9F9]' }} hover:bg-[#F0F4FF] transition-colors">
                    <td class="px-2 py-1.5 text-center text-[#86868B] border border-[#E8E8ED]">{{ $i + 1 }}</td>
                    <td class="px-2 py-1.5 text-left font-medium text-[#1D1D1F] border border-[#E8E8ED]">{{ $row['name'] }}</td>
                    <td class="px-2 py-1.5 text-left text-[#6E6E73] border border-[#E8E8ED]">{{ $row['city'] ?: '—' }}</td>

                    <td class="px-2 py-1.5 text-center border border-[#E8E8ED]">{{ $row['qty_xs'] ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-center border border-[#E8E8ED]">{{ $row['qty_s']  ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-center border border-[#E8E8ED]">{{ $row['qty_m']  ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-center border border-[#E8E8ED]">{{ $row['qty_l']  ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-center border border-[#E8E8ED]">{{ $row['qty_xl'] ?: '—' }}</td>

                    <td class="px-2 py-1.5 text-center font-medium border border-[#E8E8ED]">{{ $row['total_qty'] ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-center font-medium border border-[#E8E8ED]">{{ $row['over_all_qty'] ?: '—' }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums border border-[#E8E8ED]">{{ $row['rate'] ? number_format($row['rate']) : '—' }}</td>

                    {{-- Highlighted financial cells --}}
                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold border border-[#E8E8ED]" style="background:#FFFBEB;">
                        {{ number_format($row['total_bill']) }}
                    </td>
                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold border border-[#E8E8ED]" style="background:#F0FDF4;">
                        {{ $row['amount_received'] > 0 ? number_format($row['amount_received']) : '—' }}
                    </td>
                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold border border-[#E8E8ED]" style="background:#FFFBEB;">
                        @if($row['amount_receivable'] > 0)
                            <span style="color:#DC2626;">{{ number_format($row['amount_receivable']) }}</span>
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>

                    {{-- Title Given --}}
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

                    {{-- Per-bank payment columns --}}
                    @foreach($banks as $bank)
                    <td class="px-2 py-1.5 text-right tabular-nums border border-[#E8E8ED]">
                        @if(($row['bank_payments'][$bank->id] ?? 0) > 0)
                            {{ number_format($row['bank_payments'][$bank->id]) }}
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                    @endforeach

                    {{-- Right-side summary --}}
                    <td class="px-2 py-1.5 text-right tabular-nums border border-[#E8E8ED]" style="border-left:2px solid #0071E3 !important;">
                        @if($row['misc'] > 0)
                            {{ number_format($row['misc']) }}
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold border border-[#E8E8ED]">
                        {{ $row['amount_received'] > 0 ? number_format($row['amount_received']) : '—' }}
                    </td>
                    <td class="px-2 py-1.5 text-right tabular-nums border border-[#E8E8ED]">
                        @if($row['amount_receivable'] > 0)
                            <span style="color:#DC2626;">{{ number_format($row['amount_receivable']) }}</span>
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                    <td class="px-2 py-1.5 text-right tabular-nums font-semibold border border-[#E8E8ED]">
                        {{ number_format($row['total_bill']) }}
                    </td>
                </tr>
                @endforeach
            </tbody>

            {{-- Totals footer --}}
            <tfoot>

                {{-- Row 1: Detail totals --}}
                <tr style="background:#E5E5EA; border-top:2px solid #1D1D1F;">
                    <td class="px-2 py-2 text-center font-bold text-[#1D1D1F] border border-[#C7C7CC]">{{ count($rows) }}</td>
                    <td class="px-2 py-2 text-left font-bold text-[#1D1D1F] border border-[#C7C7CC]">Total</td>
                    <td class="px-2 py-2 border border-[#C7C7CC]"></td>

                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totXs }}</td>
                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totS }}</td>
                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totM }}</td>
                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totL }}</td>
                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totXl }}</td>
                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totTotalQty }}</td>
                    <td class="px-2 py-2 text-center font-bold border border-[#C7C7CC]">{{ $totOverAllQty }}</td>
                    <td class="px-2 py-2 border border-[#C7C7CC]"></td>

                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="background:#FEF3C7;">
                        {{ number_format($grandExpected) }}
                    </td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="background:#DCFCE7; color:#15803D;">
                        {{ number_format($grandCollected) }}
                    </td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="background:#FEF3C7; color:#DC2626;">
                        {{ number_format($grandReceivable) }}
                    </td>

                    <td class="px-2 py-2 border border-[#C7C7CC]"></td>

                    @foreach($banks as $bank)
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]">
                        @if(($totBankPayments[$bank->id] ?? 0) > 0)
                            {{ number_format($totBankPayments[$bank->id]) }}
                        @else
                            <span class="text-[#C7C7CC]">—</span>
                        @endif
                    </td>
                    @endforeach

                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="border-left:2px solid #0071E3 !important;">
                        {{ $totMisc > 0 ? number_format($totMisc) : '—' }}
                    </td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="color:#15803D;">
                        {{ number_format($grandCollected) }}
                    </td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]" style="color:#DC2626;">
                        {{ number_format($grandReceivable) }}
                    </td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#C7C7CC]">
                        {{ number_format($grandExpected) }}
                    </td>
                </tr>

                {{-- Row 2: Total Payment (per-bank expected / total bill per assigned bank) --}}
                <tr style="background:#DBEAFE;">
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>
                    <td class="px-2 py-2 text-left font-bold text-[#1D4ED8] border border-[#BFDBFE]">Total Payment</td>
                    <td colspan="9" class="px-2 py-2 border border-[#BFDBFE]"></td>

                    <td class="px-2 py-2 text-right tabular-nums font-bold text-[#1D4ED8] border border-[#BFDBFE]">
                        {{ number_format($grandExpected) }}
                    </td>
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>

                    @foreach($banks as $bank)
                    <td class="px-2 py-2 text-right tabular-nums font-bold text-[#1D4ED8] border border-[#BFDBFE]">
                        @if(($expected[$bank->id] ?? 0) > 0)
                            {{ number_format($expected[$bank->id]) }}
                        @else
                            <span class="text-[#93C5FD]">—</span>
                        @endif
                    </td>
                    @endforeach

                    <td class="px-2 py-2 border border-[#BFDBFE]" style="border-left:2px solid #0071E3 !important;"></td>
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>
                    <td class="px-2 py-2 border border-[#BFDBFE]"></td>
                </tr>

                {{-- Row 3: Receivable (per-bank outstanding balance) --}}
                <tr style="background:#FEF9C3;">
                    <td class="px-2 py-2 border border-[#FDE68A]"></td>
                    <td class="px-2 py-2 text-left font-bold text-[#92400E] border border-[#FDE68A]">Receivable</td>
                    <td colspan="9" class="px-2 py-2 border border-[#FDE68A]"></td>

                    <td class="px-2 py-2 border border-[#FDE68A]"></td>
                    <td class="px-2 py-2 border border-[#FDE68A]"></td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#FDE68A]" style="color:#DC2626;">
                        {{ number_format($grandReceivable) }}
                    </td>
                    <td class="px-2 py-2 border border-[#FDE68A]"></td>

                    @foreach($banks as $bank)
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#FDE68A]" style="color:#B45309;">
                        @if(($receivable[$bank->id] ?? 0) > 0)
                            {{ number_format($receivable[$bank->id]) }}
                        @else
                            <span class="text-[#D97706]" style="opacity:0.4;">—</span>
                        @endif
                    </td>
                    @endforeach

                    <td class="px-2 py-2 border border-[#FDE68A]" style="border-left:2px solid #0071E3 !important;"></td>
                    <td class="px-2 py-2 border border-[#FDE68A]"></td>
                    <td class="px-2 py-2 text-right tabular-nums font-bold border border-[#FDE68A]" style="color:#DC2626;">
                        {{ number_format($grandReceivable) }}
                    </td>
                    <td class="px-2 py-2 border border-[#FDE68A]"></td>
                </tr>

            </tfoot>

        </table>
    </div>
</div>

<p class="text-xs text-[#86868B] mt-3">
    Misc / Prev. Balance = cash payments + advance credits applied to this order (excluding any overpayment surplus credited to advance).
    Amount Received = amount collected against this order only (capped at Total Bill; overpayments are excluded).
</p>

@endif

@endsection
