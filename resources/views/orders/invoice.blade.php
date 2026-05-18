<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        @page {
            margin: 16mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #1D1D1F;
            line-height: 1.45;
            padding: 14mm 16mm;
        }

        /* ── Header ── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
            padding-bottom: 12pt;
            border-bottom: 2pt solid #1D1D1F;
        }
        .invoice-label {
            text-align: right;
        }
        .invoice-label .title {
            font-size: 18pt;
            font-weight: bold;
            color: #0071E3;
            letter-spacing: 1pt;
        }
        .invoice-label .order-num {
            font-size: 10pt;
            font-weight: bold;
            color: #1D1D1F;
            margin-top: 3pt;
        }
        .invoice-label .meta {
            font-size: 8pt;
            color: #6E6E73;
            margin-top: 2pt;
        }

        /* ── Status pill ── */
        .status-pill {
            display: inline-block;
            padding: 2pt 8pt;
            border-radius: 20pt;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: capitalize;
        }
        .status-received   { background: #EBF4FF; color: #1D62C8; }
        .status-confirmed  { background: #FFF8E1; color: #B45309; }
        .status-stitching  { background: #FFF3E0; color: #C2410C; }
        .status-dispatched { background: #ECFDF5; color: #065F46; }

        /* ── Meta section ── */
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16pt;
        }
        .meta-table td {
            vertical-align: top;
            padding: 0;
            border: none;
        }
        .meta-table .divider-cell {
            width: 1pt;
            background: #E8E8ED;
            padding: 0 12pt;
        }
        .meta-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #86868B;
            margin-bottom: 4pt;
        }
        .meta-value {
            font-size: 9pt;
            color: #1D1D1F;
        }
        .meta-value.large {
            font-size: 10pt;
            font-weight: bold;
        }
        .meta-value.muted {
            color: #6E6E73;
            margin-top: 1pt;
        }
        .meta-spacer {
            margin-top: 8pt;
        }

        /* ── Section heading ── */
        .section-heading {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #6E6E73;
            margin-bottom: 6pt;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16pt;
        }
        .items-table th {
            background-color: #1D1D1F;
            color: #FFFFFF;
            padding: 5pt 5pt;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            border: 0.5pt solid #444;
        }
        .items-table td {
            padding: 5.5pt 5pt;
            border: 0.5pt solid #D2D2D7;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .items-table tr.even td {
            background-color: #F9F9F9;
        }
        .items-table .qty-zero {
            color: #C7C7CC;
            text-align: center;
        }
        .items-table .qty-val {
            text-align: center;
            font-weight: bold;
        }
        .items-table .price-cell {
            text-align: right;
        }
        .items-table .amount-cell {
            text-align: right;
            font-weight: bold;
        }
        .items-table tr.totals-row td {
            background-color: #F2F2F7;
            font-weight: bold;
            font-size: 8.5pt;
            border-top: 1.5pt solid #1D1D1F;
        }

        /* ── Summary ── */
        .summary-wrap {
            width: 100%;
            margin-bottom: 18pt;
        }
        .summary-inner {
            width: 220pt;
            float: right;
            border-collapse: collapse;
        }
        .summary-inner td {
            border: none;
            padding: 3pt 4pt;
            font-size: 9pt;
        }
        .summary-inner .label { color: #6E6E73; text-align: left; }
        .summary-inner .value { text-align: right; font-weight: bold; color: #1D1D1F; }
        .summary-inner tr.grand-total td {
            border-top: 1.5pt solid #1D1D1F;
            font-size: 11pt;
            padding-top: 5pt;
        }
        .value.paid { color: #059669; }
        .value.due  { color: #DC2626; }
        .clearfix { clear: both; }

        /* ── Payments ── */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16pt;
        }
        .payments-table th {
            background-color: #1D1D1F;
            color: #FFFFFF;
            padding: 5pt 5pt;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            border: 0.5pt solid #444;
        }
        .payments-table td {
            padding: 5pt 5pt;
            border: 0.5pt solid #D2D2D7;
            font-size: 8pt;
            vertical-align: middle;
        }
        .payments-table tr.even td {
            background-color: #F9F9F9;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 20pt;
            padding-top: 8pt;
            border-top: 1pt solid #E8E8ED;
            text-align: center;
            font-size: 7pt;
            color: #86868B;
        }
    </style>
</head>
<body>

{{-- ── Header ── --}}
<table class="header-table">
    <tr>
        <td style="vertical-align:top; width:50%;">
            <img src="{{ public_path('images/casualite-logo.png') }}" style="height:54pt; width:auto; display:block;">
        </td>
        <td style="vertical-align:top; text-align:right; width:50%;">
            <div class="invoice-label">
                <div class="title">INVOICE</div>
                <div class="order-num">Order #{{ $order->order_number }}</div>
                <div class="meta">Date: {{ $order->submitted_at->format('d M Y') }}</div>
                <div style="margin-top:5pt;">
                    <span class="status-pill status-{{ $order->status }}">{{ ucfirst($order->status) }}</span>
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- ── Bill To / Catalogue / Invoice Details ── --}}
<table class="meta-table">
    <tr>
        <td style="width:34%; padding-right:14pt; vertical-align:top;">
            <div class="meta-label">Bill To</div>
            <div class="meta-value large">{{ $order->submitted_name }}</div>
            <div class="meta-value muted">{{ $order->submitted_city }}</div>
            <div class="meta-value muted">{{ $order->submitted_email }}</div>
        </td>
        <td style="width:1pt; background:#E8E8ED; padding:0; border-left:1pt solid #E8E8ED;">&nbsp;</td>
        <td style="width:33%; padding-left:14pt; padding-right:14pt; vertical-align:top;">
            <div class="meta-label">Catalogue</div>
            <div class="meta-value large">{{ $order->catalogue->name }}</div>
            <div class="meta-value muted">{{ $order->items->count() }} design(s)</div>
        </td>
        <td style="width:1pt; background:#E8E8ED; padding:0; border-left:1pt solid #E8E8ED;">&nbsp;</td>
        <td style="width:32%; padding-left:14pt; vertical-align:top;">
            <div class="meta-label">Invoice Date</div>
            <div class="meta-value">{{ $order->submitted_at->format('d M Y') }}</div>
            <div class="meta-spacer">
                <div class="meta-label">Invoice #</div>
                <div class="meta-value">{{ $order->order_number }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ── Order Details ── --}}
<div class="section-heading">Order Details</div>

@php
    $sizes = ['xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'];
    $totalPieces = 0;
@endphp

<table class="items-table">
    <thead>
        <tr>
            <th style="text-align:left; width:28%;">Design</th>
            @foreach($sizes as $col => $label)
            <th style="text-align:center; width:7%;">{{ $label }}</th>
            @endforeach
            <th style="text-align:center; width:8%;">Qty</th>
            <th style="text-align:right; width:13%;">Unit Price</th>
            <th style="text-align:right; width:14%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->items as $i => $item)
        @php
            $rowQty = $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl;
            $totalPieces += $rowQty;
        @endphp
        <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
            <td style="text-align:left; font-weight:bold;">{{ $item->design->name ?? '—' }}</td>
            @foreach(array_keys($sizes) as $col)
            @php $qty = $item->{'qty_' . $col}; @endphp
            <td class="{{ $qty ? 'qty-val' : 'qty-zero' }}">{{ $qty ?: '—' }}</td>
            @endforeach
            <td class="qty-val">{{ lacs_format($rowQty) }}</td>
            <td class="price-cell">PKR {{ lacs_format($item->unit_price, 0) }}</td>
            <td class="amount-cell">PKR {{ lacs_format($item->total_amount, 0) }}</td>
        </tr>
        @endforeach
        <tr class="totals-row">
            <td style="text-align:left;" colspan="{{ 1 + count($sizes) }}">Total</td>
            <td style="text-align:center;">{{ lacs_format($totalPieces) }}</td>
            <td style="text-align:right;"></td>
            <td style="text-align:right;">PKR {{ lacs_format($order->total_amount, 0) }}</td>
        </tr>
    </tbody>
</table>

{{-- ── Financial Summary ── --}}
<div class="summary-wrap">
    <table class="summary-inner">
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">PKR {{ lacs_format($order->total_amount, 0) }}</td>
        </tr>
        <tr>
            <td class="label">Amount Paid</td>
            <td class="value paid">PKR {{ lacs_format($order->total_paid, 0) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="label">Grand Total</td>
            <td class="value">PKR {{ lacs_format($order->total_amount, 0) }}</td>
        </tr>
        <tr>
            <td class="label" style="padding-top:2pt;">Outstanding Balance</td>
            <td class="value {{ $order->outstanding_balance > 0 ? 'due' : 'paid' }}" style="padding-top:2pt;">
                @if($order->outstanding_balance > 0)
                    PKR {{ lacs_format($order->outstanding_balance, 0) }}
                @else
                    PAID IN FULL
                @endif
            </td>
        </tr>
    </table>
    <div class="clearfix"></div>
</div>

{{-- ── Payment History ── --}}
@if($order->payments->count())
<div class="section-heading">Payment History</div>
<table class="payments-table">
    <thead>
        <tr>
            <th style="text-align:left; width:18%;">Date</th>
            <th style="text-align:left; width:26%;">Method</th>
            <th style="text-align:left; width:36%;">Notes</th>
            <th style="text-align:right; width:20%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($order->payments as $i => $payment)
        <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
            <td>{{ $payment->payment_date->format('d M Y') }}</td>
            <td>
                {{ ucwords(str_replace('_', ' ', $payment->payment_type)) }}
                @if($payment->payment_type === 'bank_transfer' && $payment->bankAccount)
                    &middot; {{ $payment->bankAccount->title }}
                @endif
            </td>
            <td style="color:#6E6E73;">{{ $payment->notes ?? '—' }}</td>
            <td style="text-align:right; color:#059669; font-weight:bold;">PKR {{ lacs_format($payment->amount, 0) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- ── Footer ── --}}
<div class="footer">
    This is a computer-generated invoice and does not require a signature. &middot; Casualite &middot; Pakistan
</div>

</body>
</html>
