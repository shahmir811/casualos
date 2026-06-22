<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { margin: 10mm 15mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1D1D1F; background: #fff; padding: 0 12mm; }
    .header { margin-bottom: 14px; border-bottom: 1.5px solid #0071E3; padding-bottom: 8px; display: table; width: 100%; }
    .header-left { display: table-cell; vertical-align: middle; width: 80px; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .header h1 { font-size: 15px; font-weight: 700; color: #1D1D1F; }
    .header p { font-size: 9px; color: #6E6E73; margin-top: 2px; }
    .meta { font-size: 8px; color: #86868B; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #F5F5F7; color: #6E6E73; font-size: 7.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 5px 6px; border: 1px solid #D2D2D7; }
    th.right, td.right { text-align: right; }
    th.left, td.left { text-align: left; }
    td { padding: 5px 6px; border: 1px solid #D2D2D7; font-size: 8.5px; }
    tr:nth-child(even) td { background: #FAFAFA; }
    tfoot td { background: #F5F5F7 !important; font-weight: 700; font-size: 8.5px; border: 1px solid #D2D2D7; }
    .red { color: #DC2626; }
    .green { color: #16A34A; }
    .muted { color: #86868B; }
    .stats { display: table; width: 100%; margin-bottom: 14px; }
    .stat-box { display: table-cell; width: 33%; border: 1px solid #E8E8ED; padding: 8px 10px; }
    .stat-label { font-size: 7px; color: #6E6E73; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
    .stat-value { font-size: 13px; font-weight: 300; }
</style>
</head>
<body>

<div class="header">
    <div class="header-left">
        @if($logoDataUri)
        <img src="{{ $logoDataUri }}" style="height:40pt; width:auto; display:block;">
        @endif
    </div>
    <div class="header-right">
        <h1>Customer Order Bill — {{ $selectedCatalogue->name }}</h1>
        <p>Casualite — Per-customer bill, amount received, and outstanding balance</p>
        <p class="meta">Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>
</div>

@php
    $totXs      = $orders->sum('agg_xs');
    $totS       = $orders->sum('agg_s');
    $totM       = $orders->sum('agg_m');
    $totL       = $orders->sum('agg_l');
    $totXl      = $orders->sum('agg_xl');
    $totQty     = $orders->sum('agg_total');
    $totBill    = $orders->sum('total_amount');
    $totPaid    = $orders->sum('total_paid');
    $totBalance = $orders->sum('outstanding_balance');
@endphp

<div class="stats">
    <div class="stat-box">
        <div class="stat-label">Total Bill</div>
        <div class="stat-value">Rs. {{ number_format($totBill, 0) }}</div>
    </div>
    <div class="stat-box" style="border-left:none;">
        <div class="stat-label">Amount Received</div>
        <div class="stat-value green">Rs. {{ number_format($totPaid, 0) }}</div>
    </div>
    <div class="stat-box" style="border-left:none;">
        <div class="stat-label">Outstanding</div>
        <div class="stat-value {{ $totBalance > 0 ? 'red' : 'muted' }}">Rs. {{ number_format($totBalance, 0) }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left">#</th>
            <th class="left">Customer</th>
            <th class="left">City</th>
            <th class="right">XS</th>
            <th class="right">S</th>
            <th class="right">M</th>
            <th class="right">L</th>
            <th class="right">XL</th>
            <th class="right">Qty</th>
            <th class="right">Rate</th>
            <th class="right">Total Bill</th>
            <th class="right">Received</th>
            <th class="right">Receivable</th>
            <th class="left">Title Given</th>
        </tr>
    </thead>
    <tbody>
        @foreach($orders as $i => $order)
        <tr>
            <td class="muted">{{ $i + 1 }}</td>
            <td>{{ $order->customer?->name ?? $order->submitted_name }}</td>
            <td class="muted">{{ $order->customer?->city ?? $order->submitted_city }}</td>
            <td class="right">{{ $order->agg_xs ?: '—' }}</td>
            <td class="right">{{ $order->agg_s ?: '—' }}</td>
            <td class="right">{{ $order->agg_m ?: '—' }}</td>
            <td class="right">{{ $order->agg_l ?: '—' }}</td>
            <td class="right">{{ $order->agg_xl ?: '—' }}</td>
            <td class="right" style="font-weight:600">{{ number_format($order->agg_total) }}</td>
            <td class="right muted">{{ number_format($order->agg_rate) }}</td>
            <td class="right">{{ number_format($order->total_amount, 0) }}</td>
            <td class="right green">{{ number_format($order->total_paid, 0) }}</td>
            <td class="right {{ $order->outstanding_balance > 0 ? 'red' : 'muted' }}" style="{{ $order->outstanding_balance > 0 ? 'font-weight:600' : '' }}">
                {{ number_format($order->outstanding_balance, 0) }}
            </td>
            <td class="muted">{{ $order->title_given_label }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="left">Total</td>
            <td class="right">{{ $totXs ?: '—' }}</td>
            <td class="right">{{ $totS ?: '—' }}</td>
            <td class="right">{{ $totM ?: '—' }}</td>
            <td class="right">{{ $totL ?: '—' }}</td>
            <td class="right">{{ $totXl ?: '—' }}</td>
            <td class="right">{{ number_format($totQty) }}</td>
            <td></td>
            <td class="right">Rs. {{ number_format($totBill, 0) }}</td>
            <td class="right green">Rs. {{ number_format($totPaid, 0) }}</td>
            <td class="right {{ $totBalance > 0 ? 'red' : 'muted' }}">Rs. {{ number_format($totBalance, 0) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
