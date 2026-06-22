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
    .red   { color: #DC2626; }
    .footnote { font-size: 6px; color: #86868B; margin-top: 6px; }
    .highlight-rcv { background: #FFFBEB !important; }
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
        <h1>Receivables by Bank — {{ $selectedCatalogue->name }}</h1>
        <p>Casualite — Outstanding balances grouped by assigned bank account</p>
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
@endphp

@if(!empty($rows))
<table>
    <colgroup>
        <col style="width:2%">
        <col style="width:18%">
        <col style="width:8%">
        <col style="width:8%">
        <col style="width:8%">
        @foreach($banks as $bank)<col style="width:{{ number_format(54 / $banks->count(), 1) }}%">@endforeach
        <col style="width:6%">
    </colgroup>
    <thead>
        <tr>
            <th>#</th>
            <th class="left">Customer Name</th>
            <th class="left">City</th>
            <th class="right orange">Receivable</th>
            <th>Title Given</th>
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
            <th class="right">Misc</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        <tr class="{{ $i % 2 === 0 ? 'even' : 'odd' }}">
            <td class="center" style="color:#86868B;">{{ $i + 1 }}</td>
            <td class="left name">{{ $row['name'] }}</td>
            <td class="left" style="color:#6E6E73;">{{ $row['city'] ?: '' }}</td>

            <td class="right highlight-rcv">
                @if($row['receivable'] > 0)
                    <span class="red" style="font-weight:600;">{{ number_format($row['receivable']) }}</span>
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
                @if(($row['bank_rcv'][$bank->id] ?? 0) > 0)
                    <span class="red" style="font-weight:600;">{{ number_format($row['bank_rcv'][$bank->id]) }}</span>
                @endif
            </td>
            @endforeach

            <td class="right">
                @if($row['misc'] > 0)
                    <span class="red" style="font-weight:600;">{{ number_format($row['misc']) }}</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td class="center">{{ count($rows) }}</td>
            <td class="left">Total</td>
            <td></td>
            <td class="right red">{{ number_format($grandReceivable) }}</td>
            <td></td>
            @foreach($banks as $bank)
            <td class="right red">
                {{ ($bankReceivables[$bank->id] ?? 0) > 0 ? number_format($bankReceivables[$bank->id]) : '' }}
            </td>
            @endforeach
            <td class="right red">{{ $miscReceivable > 0 ? number_format($miscReceivable) : '' }}</td>
        </tr>
    </tfoot>
</table>

<p class="footnote">
    Receivable = outstanding balance still to be collected. Amount appears in the column matching the assigned bank (Title Given).
    Misc = outstanding balance for orders with no assigned bank.
</p>
@else
<p style="font-size:10px; color:#6E6E73; text-align:center; margin-top:30px;">No orders found for this catalogue.</p>
@endif

</body>
</html>
