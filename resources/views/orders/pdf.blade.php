<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 14mm 18mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7pt;
            color: #1D1D1F;
            padding: 0 6mm;
        }
        .header {
            margin-bottom: 0;
        }
        .header h1 {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 2pt;
        }
        .header p {
            font-size: 8pt;
            color: #6E6E73;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
            padding-bottom: 6pt;
            border-bottom: 1.5pt solid #1D1D1F;
        }
        .header-table td {
            border: none !important;
            padding: 0 !important;
            background: transparent !important;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th {
            background-color: #1D1D1F;
            color: #FFFFFF;
            padding: 3pt 2pt;
            text-align: center;
            font-size: 6.5pt;
            border: 0.5pt solid #444444;
            font-weight: bold;
            word-wrap: break-word;
        }
        td {
            padding: 2.5pt 2pt;
            border: 0.5pt solid #D1D1D6;
            font-size: 6.5pt;
            text-align: center;
            word-wrap: break-word;
        }
        .td-left  { text-align: left; }
        .td-right { text-align: right; }
        tr.even td { background-color: #F9F9F9; }
        .totals td {
            font-weight: bold;
            background-color: #E8E8ED;
            border-top: 1.5pt solid #1D1D1F;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="vertical-align:middle; width:25%;">
            @if($logoDataUri)
            <img src="{{ $logoDataUri }}" style="height:40pt; width:auto; display:block;">
            @endif
        </td>
        <td style="vertical-align:middle; text-align:right;">
            <div class="header">
                <h1>{{ $catalogue->name }} — Customer Payments Report</h1>
                <p>Generated: {{ now()->format('d M Y, H:i') }} &nbsp;|&nbsp; {{ $orders->count() }} orders</p>
            </div>
        </td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th style="width:2%">#</th>
            <th style="width:8%">Customer</th>
            <th style="width:5%">City</th>
            <th style="width:3%">XS</th>
            <th style="width:3%">S</th>
            <th style="width:3%">M</th>
            <th style="width:3%">L</th>
            <th style="width:3%">XL</th>
            <th style="width:3.5%">Qty/Dsn</th>
            <th style="width:3.5%">Total Qty</th>
            <th style="width:4%">Rate</th>
            <th style="width:5.5%">Total Bill</th>
            <th style="width:5.5%">Received</th>
            <th style="width:5.5%">Receivable</th>
            <th style="width:5%">Title Given</th>
            @foreach($bankAccounts as $bank)
            <th style="width:4.5%">{{ $bank->title }}</th>
            @endforeach
            <th style="width:4%">Misc</th>
        </tr>
    </thead>
    <tbody>

    @php
        $totals = [
            'xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0,
            'qty_per_design' => 0, 'total_qty' => 0,
            'total_bill' => 0, 'received' => 0, 'receivable' => 0,
            'misc' => 0,
        ];
        foreach ($bankAccounts as $bank) {
            $totals['bank_' . $bank->id] = 0;
        }
    @endphp

    @foreach($orders as $i => $order)
    @php
        $item         = $order->items->first();
        $xs           = (int) ($item?->qty_xs ?? 0);
        $s            = (int) ($item?->qty_s  ?? 0);
        $m            = (int) ($item?->qty_m  ?? 0);
        $l            = (int) ($item?->qty_l  ?? 0);
        $xl           = (int) ($item?->qty_xl ?? 0);
        $qtyPerDesign = $xs + $s + $m + $l + $xl;
        $totalQty     = $qtyPerDesign * $catalogue->number_of_designs;
        $rate         = $totalQty > 0 ? (int) round($order->total_amount / $totalQty) : 0;

        $bankPmts   = [];
        $miscAmt    = 0;
        $titleGiven = '';
        foreach ($order->payments as $payment) {
            if ($payment->payment_type === 'advance') {
                $miscAmt += $payment->amount;
            } elseif ($payment->payment_type === 'bank_transfer' && $payment->bank_account_id) {
                $bankPmts[$payment->bank_account_id] = ($bankPmts[$payment->bank_account_id] ?? 0) + $payment->amount;
            }
        }
        $titleGiven = $order->payments
            ->where('payment_type', 'bank_transfer')
            ->filter(fn($p) => $p->bankAccount)
            ->map(fn($p) => $p->bankAccount->title)
            ->unique()
            ->implode('/');

        $totals['xs']             += $xs;
        $totals['s']              += $s;
        $totals['m']              += $m;
        $totals['l']              += $l;
        $totals['xl']             += $xl;
        $totals['qty_per_design'] += $qtyPerDesign;
        $totals['total_qty']      += $totalQty;
        $totals['total_bill']     += $order->total_amount;
        $totals['received']       += $order->total_paid;
        $totals['receivable']     += $order->outstanding_balance;
        $totals['misc']           += $miscAmt;
        foreach ($bankAccounts as $bank) {
            $totals['bank_' . $bank->id] += ($bankPmts[$bank->id] ?? 0);
        }
    @endphp
    <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
        <td>{{ $i + 1 }}</td>
        <td class="td-left">{{ $order->customer?->name ?? $order->submitted_name }}</td>
        <td>{{ $order->submitted_city }}</td>
        <td>{{ $xs ?: '' }}</td>
        <td>{{ $s ?: '' }}</td>
        <td>{{ $m ?: '' }}</td>
        <td>{{ $l ?: '' }}</td>
        <td>{{ $xl ?: '' }}</td>
        <td>{{ $qtyPerDesign ?: '' }}</td>
        <td>{{ $totalQty ?: '' }}</td>
        <td class="td-right">{{ $rate > 0 ? lacs_format($rate) : '' }}</td>
        <td class="td-right">{{ lacs_format($order->total_amount, 0) }}</td>
        <td class="td-right">{{ $order->total_paid > 0 ? lacs_format($order->total_paid, 0) : '' }}</td>
        <td class="td-right">{{ $order->outstanding_balance > 0 ? lacs_format($order->outstanding_balance, 0) : '' }}</td>
        <td class="td-left">{{ $titleGiven }}</td>
        @foreach($bankAccounts as $bank)
        @php $bankAmt = $bankPmts[$bank->id] ?? 0; @endphp
        <td class="td-right">{{ $bankAmt > 0 ? lacs_format($bankAmt, 0) : '' }}</td>
        @endforeach
        <td class="td-right">{{ $miscAmt > 0 ? lacs_format($miscAmt, 0) : '' }}</td>
    </tr>
    @endforeach

    {{-- Totals row --}}
    <tr class="totals">
        <td colspan="3" class="td-left">TOTAL ({{ $orders->count() }} orders)</td>
        <td>{{ $totals['xs'] > 0 ? lacs_format($totals['xs']) : '' }}</td>
        <td>{{ $totals['s'] > 0 ? lacs_format($totals['s']) : '' }}</td>
        <td>{{ $totals['m'] > 0 ? lacs_format($totals['m']) : '' }}</td>
        <td>{{ $totals['l'] > 0 ? lacs_format($totals['l']) : '' }}</td>
        <td>{{ $totals['xl'] > 0 ? lacs_format($totals['xl']) : '' }}</td>
        <td>{{ $totals['qty_per_design'] > 0 ? lacs_format($totals['qty_per_design']) : '' }}</td>
        <td>{{ $totals['total_qty'] > 0 ? lacs_format($totals['total_qty']) : '' }}</td>
        <td></td>
        <td class="td-right">{{ lacs_format($totals['total_bill'], 0) }}</td>
        <td class="td-right">{{ lacs_format($totals['received'], 0) }}</td>
        <td class="td-right">{{ lacs_format($totals['receivable'], 0) }}</td>
        <td></td>
        @foreach($bankAccounts as $bank)
        <td class="td-right">{{ $totals['bank_' . $bank->id] > 0 ? lacs_format($totals['bank_' . $bank->id], 0) : '' }}</td>
        @endforeach
        <td class="td-right">{{ $totals['misc'] > 0 ? lacs_format($totals['misc'], 0) : '' }}</td>
    </tr>

    </tbody>
</table>

</body>
</html>
