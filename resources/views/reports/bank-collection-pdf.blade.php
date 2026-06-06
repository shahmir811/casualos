<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { margin: 8mm 10mm; size: A4 landscape; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 7px; color: #1D1D1F; background: #fff; }
    .header { margin-bottom: 10px; border-bottom: 1.5px solid #0071E3; padding-bottom: 6px; display: table; width: 100%; }
    .header-left  { display: table-cell; vertical-align: middle; width: 70px; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .header h1 { font-size: 12px; font-weight: 700; color: #1D1D1F; }
    .header p  { font-size: 7px; color: #6E6E73; margin-top: 1px; }
    .meta      { font-size: 6.5px; color: #86868B; margin-top: 1px; }

    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th {
        background: #1D1D1F;
        color: #fff;
        font-size: 6.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 3px 3px;
        border: 0.5px solid #444;
        text-align: center;
        vertical-align: middle;
        line-height: 1.3;
    }
    th.right  { text-align: right; }
    th.left   { text-align: left; }
    th.orange { background: #D97706; }
    th.green  { background: #16A34A; }

    td {
        padding: 2.5px 3px;
        border: 0.5px solid #D2D2D7;
        font-size: 6.5px;
        vertical-align: middle;
    }
    td.center { text-align: center; }
    td.right  { text-align: right; }
    td.left   { text-align: left; }
    td.name   { font-weight: 600; }
    td.muted  { color: #C7C7CC; text-align: center; }

    tr.even { background: #fff; }
    tr.odd  { background: #F9F9F9; }

    tfoot td {
        background: #E5E5EA !important;
        font-weight: 700;
        font-size: 6.5px;
        border-top: 1.5px solid #1D1D1F;
        border-bottom: 0.5px solid #999;
    }

    .bank-pill { display: inline-block; border-radius: 2px; padding: 0px 3px; font-size: 6px; font-weight: 700; }
    .divider { border-left: 1.5px solid #0071E3 !important; }
    .footnote { font-size: 6px; color: #86868B; margin-top: 6px; }

    .highlight-bill { background: #FFFBEB !important; }
    .highlight-recv { background: #F0FDF4 !important; }
    .red   { color: #DC2626; }
    .green-text { color: #15803D; }
    .bold  { font-weight: 700; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        @if($logoDataUri)
        <img src="{{ $logoDataUri }}" style="height:32pt; width:auto; display:block;">
        @endif
    </div>
    <div class="header-right">
        <h1>Bank Collection Report — {{ $selectedCatalogue->name }}</h1>
        <p>Casualite — Collected vs expected per bank account</p>
        <p class="meta">Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>
</div>

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

// Totals
$totXs = $totS = $totM = $totL = $totXl = $totTotalQty = $totOverAllQty = 0;
$totBankPayments = [];
$totMisc = 0;
foreach ($banks as $bank) { $totBankPayments[$bank->id] = 0; }
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

// Column widths (as percentages, totalling ~100)
// #(2) Name(13) City(7) XS(3) S(3) M(3) L(3) XL(3) TQty(4) OAQty(4) Rate(5)
// TBill(6) Recv(6) Rcvbl(6) Title(6) [banks 5% each] Misc(5) Recv(6) Rcvbl(6) TBill(6)
$bankCount = $banks->count();
// We'll let CSS handle this via table-layout:fixed and explicit widths
@endphp

@if(!empty($rows))
<table>
    <colgroup>
        <col style="width:2%">
        <col style="width:12%">
        <col style="width:6%">
        <col style="width:2.5%"><col style="width:2.5%"><col style="width:2.5%"><col style="width:2.5%"><col style="width:2.5%">
        <col style="width:3.5%">
        <col style="width:4%">
        <col style="width:4.5%">
        <col style="width:5.5%">
        <col style="width:5.5%">
        <col style="width:5.5%">
        <col style="width:5%">
        @foreach($banks as $bank)<col style="width:4.5%">@endforeach
        <col style="width:4.5%">
        <col style="width:5%">
        <col style="width:5%">
        <col style="width:5%">
    </colgroup>
    <thead>
        <tr>
            <th>#</th>
            <th class="left">Customer Name</th>
            <th class="left">City</th>
            <th>XS</th><th>S</th><th>M</th><th>L</th><th>XL</th>
            <th>Total<br>Qty</th>
            <th>OA<br>Qty</th>
            <th class="right">Rate</th>
            <th class="right orange">Total Bill</th>
            <th class="right green">Amt Recv.</th>
            <th class="right orange">Receivable</th>
            <th>Title</th>
            @foreach($banks as $bank)
            @php $bc = $bankColors[$bank->title] ?? null; @endphp
            <th class="right">
                @if($bc)
                    <span class="bank-pill" style="background:{{ $bc['bg'] }}; color:{{ $bc['text'] }};">
                        {{ strtoupper(substr($bank->title, 0, 8)) }}
                    </span>
                @else
                    {{ $bank->title }}
                @endif
            </th>
            @endforeach
            <th class="right divider">Misc /<br>Prev.</th>
            <th class="right">Amt<br>Recv.</th>
            <th class="right">Receivable</th>
            <th class="right">Total Bill</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        <tr class="{{ $i % 2 === 0 ? 'even' : 'odd' }}">
            <td class="center" style="color:#86868B;">{{ $i + 1 }}</td>
            <td class="left name">{{ $row['name'] }}</td>
            <td class="left" style="color:#6E6E73;">{{ $row['city'] ?: '' }}</td>

            <td class="center">{{ $row['qty_xs'] ?: '' }}</td>
            <td class="center">{{ $row['qty_s']  ?: '' }}</td>
            <td class="center">{{ $row['qty_m']  ?: '' }}</td>
            <td class="center">{{ $row['qty_l']  ?: '' }}</td>
            <td class="center">{{ $row['qty_xl'] ?: '' }}</td>

            <td class="center bold">{{ $row['total_qty'] ?: '' }}</td>
            <td class="center bold">{{ $row['over_all_qty'] ?: '' }}</td>
            <td class="right">{{ $row['rate'] ? lacs_format($row['rate']) : '' }}</td>

            <td class="right bold highlight-bill">{{ lacs_format($row['total_bill']) }}</td>
            <td class="right bold highlight-recv">{{ $row['amount_received'] > 0 ? lacs_format($row['amount_received']) : '' }}</td>
            <td class="right bold highlight-bill">
                @if($row['amount_receivable'] > 0)
                    <span class="red">{{ lacs_format($row['amount_receivable']) }}</span>
                @endif
            </td>

            <td class="center">
                @if($row['title_given'])
                    @php $bc = $bankColors[$row['title_given']] ?? null; @endphp
                    @if($bc)
                        <span class="bank-pill" style="background:{{ $bc['bg'] }}; color:{{ $bc['text'] }};">
                            {{ $row['title_given'] }}
                        </span>
                    @else
                        {{ $row['title_given'] }}
                    @endif
                @endif
            </td>

            @foreach($banks as $bank)
            <td class="right">
                @if(($row['bank_payments'][$bank->id] ?? 0) > 0)
                    {{ lacs_format($row['bank_payments'][$bank->id]) }}
                @endif
            </td>
            @endforeach

            <td class="right divider">{{ $row['misc'] > 0 ? lacs_format($row['misc']) : '' }}</td>
            <td class="right bold">{{ $row['amount_received'] > 0 ? lacs_format($row['amount_received']) : '' }}</td>
            <td class="right">
                @if($row['amount_receivable'] > 0)
                    <span class="red">{{ lacs_format($row['amount_receivable']) }}</span>
                @endif
            </td>
            <td class="right bold">{{ lacs_format($row['total_bill']) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        {{-- Row 1: Detail totals --}}
        <tr>
            <td class="center">{{ count($rows) }}</td>
            <td class="left">Total</td>
            <td></td>
            <td class="center">{{ $totXs }}</td>
            <td class="center">{{ $totS }}</td>
            <td class="center">{{ $totM }}</td>
            <td class="center">{{ $totL }}</td>
            <td class="center">{{ $totXl }}</td>
            <td class="center">{{ $totTotalQty }}</td>
            <td class="center">{{ $totOverAllQty }}</td>
            <td></td>
            <td class="right">{{ lacs_format($grandExpected) }}</td>
            <td class="right green-text">{{ lacs_format($grandCollected) }}</td>
            <td class="right red">{{ lacs_format($grandReceivable) }}</td>
            <td></td>
            @foreach($banks as $bank)
            <td class="right">
                @if(($totBankPayments[$bank->id] ?? 0) > 0)
                    {{ lacs_format($totBankPayments[$bank->id]) }}
                @endif
            </td>
            @endforeach
            <td class="right divider">{{ $totMisc > 0 ? lacs_format($totMisc) : '' }}</td>
            <td class="right green-text">{{ lacs_format($grandCollected) }}</td>
            <td class="right red">{{ lacs_format($grandReceivable) }}</td>
            <td class="right">{{ lacs_format($grandExpected) }}</td>
        </tr>
        {{-- Row 2: Total Payment (per-bank expected) --}}
        <tr style="background:#DBEAFE;">
            <td></td>
            <td class="left" style="color:#1D4ED8; font-weight:700;">Total Payment</td>
            <td colspan="9"></td>
            <td class="right" style="color:#1D4ED8;">{{ lacs_format($grandExpected) }}</td>
            <td></td><td></td><td></td>
            @foreach($banks as $bank)
            <td class="right" style="color:#1D4ED8;">
                @if(($expected[$bank->id] ?? 0) > 0){{ lacs_format($expected[$bank->id]) }}@endif
            </td>
            @endforeach
            <td class="divider"></td>
            <td></td><td></td><td></td>
        </tr>
        {{-- Row 3: Receivable (per-bank outstanding) --}}
        <tr style="background:#FEF9C3;">
            <td></td>
            <td class="left" style="color:#92400E; font-weight:700;">Receivable</td>
            <td colspan="9"></td>
            <td></td><td></td>
            <td class="right red">{{ lacs_format($grandReceivable) }}</td>
            <td></td>
            @foreach($banks as $bank)
            <td class="right" style="color:#B45309;">
                @if(($receivable[$bank->id] ?? 0) > 0){{ lacs_format($receivable[$bank->id]) }}@endif
            </td>
            @endforeach
            <td class="divider"></td>
            <td></td>
            <td class="right red">{{ lacs_format($grandReceivable) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<p class="footnote">
    OA Qty = Over All Total Qty (across all designs).
    Misc / Prev. = advance credits applied + cash payments not attributed to a specific bank.
    Amt Recv. = Amount Received. Receivable = Amount still outstanding.
</p>
@else
<p style="font-size:10px; color:#6E6E73; text-align:center; margin-top:30px;">No orders found for this catalogue.</p>
@endif

</body>
</html>
