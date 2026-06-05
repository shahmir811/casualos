<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    @page { margin: 10mm 15mm; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8px; color: #1D1D1F; background: #fff; padding: 0 12mm; }
    .header { margin-bottom: 14px; border-bottom: 1.5px solid #0071E3; padding-bottom: 8px; display: table; width: 100%; }
    .header-left { display: table-cell; vertical-align: middle; width: 80px; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .header h1 { font-size: 15px; font-weight: 700; color: #1D1D1F; }
    .header p { font-size: 9px; color: #6E6E73; margin-top: 2px; }
    .meta { font-size: 8px; color: #86868B; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #F5F5F7; color: #6E6E73; font-size: 7px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 4px 5px; border: 1px solid #D2D2D7; }
    th.right, td.right { text-align: right; }
    th.left, td.left { text-align: left; }
    td { padding: 4px 5px; border: 1px solid #D2D2D7; font-size: 8px; }
    tr:nth-child(even) td { background: #FAFAFA; }
    tfoot td { background: #F5F5F7 !important; font-weight: 700; font-size: 8px; border: 1px solid #D2D2D7; }
    .red { color: #DC2626; }
    .green { color: #16A34A; }
    .muted { color: #86868B; }
    .stats { display: table; width: 100%; margin-bottom: 14px; }
    .stat-box { display: table-cell; width: 33%; border: 1px solid #E8E8ED; padding: 7px 10px; }
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
        <h1>Bank Account Breakdown — {{ $selectedCatalogue->name }}</h1>
        <p>Casualite — Payments per customer broken down by bank account</p>
        <p class="meta">Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>
</div>

@php
    $grandTotals = $bankAccounts->mapWithKeys(fn($ba) => [$ba->id => $orders->sum(fn($o) => $o->bank_totals[$ba->id] ?? 0)]);
    $grandMisc   = $orders->sum('misc_total');
    $grandPaid   = $orders->sum('total_paid');
    $grandBal    = $orders->sum('outstanding_balance');
@endphp

<div class="stats">
    <div class="stat-box">
        <div class="stat-label">Total Received</div>
        <div class="stat-value green">Rs. {{ lacs_format($grandPaid, 0) }}</div>
    </div>
    <div class="stat-box" style="border-left:none;">
        <div class="stat-label">Outstanding</div>
        <div class="stat-value {{ $grandBal > 0 ? 'red' : 'muted' }}">Rs. {{ lacs_format($grandBal, 0) }}</div>
    </div>
    <div class="stat-box" style="border-left:none;">
        <div class="stat-label">Orders</div>
        <div class="stat-value">{{ $orders->count() }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left">#</th>
            <th class="left">Customer</th>
            <th class="left">City</th>
            @foreach($bankAccounts as $ba)
                <th class="right">{{ $ba->title }}</th>
            @endforeach
            <th class="right">Cash/Adv.</th>
            <th class="right">Total Pymt</th>
            <th class="right">Net Recv.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($orders as $i => $order)
        <tr>
            <td class="muted">{{ $i + 1 }}</td>
            <td>{{ $order->customer?->name ?? $order->submitted_name }}</td>
            <td class="muted">{{ $order->customer?->city ?? $order->submitted_city }}</td>
            @foreach($bankAccounts as $ba)
                <td class="right">
                    {{ ($order->bank_totals[$ba->id] ?? 0) > 0 ? lacs_format($order->bank_totals[$ba->id], 0) : '—' }}
                </td>
            @endforeach
            <td class="right">{{ $order->misc_total > 0 ? lacs_format($order->misc_total, 0) : '—' }}</td>
            <td class="right" style="font-weight:600">{{ lacs_format($order->total_paid, 0) }}</td>
            <td class="right {{ $order->outstanding_balance > 0 ? 'red' : 'muted' }}" style="{{ $order->outstanding_balance > 0 ? 'font-weight:600' : '' }}">
                {{ lacs_format($order->outstanding_balance, 0) }}
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="left">Total</td>
            @foreach($bankAccounts as $ba)
                <td class="right">{{ $grandTotals[$ba->id] > 0 ? 'Rs. ' . lacs_format($grandTotals[$ba->id], 0) : '—' }}</td>
            @endforeach
            <td class="right">{{ $grandMisc > 0 ? 'Rs. ' . lacs_format($grandMisc, 0) : '—' }}</td>
            <td class="right green">Rs. {{ lacs_format($grandPaid, 0) }}</td>
            <td class="right {{ $grandBal > 0 ? 'red' : 'muted' }}">Rs. {{ lacs_format($grandBal, 0) }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
