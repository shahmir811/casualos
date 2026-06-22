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
    .stats { display: table; width: 100%; margin-bottom: 14px; }
    .stat-box { display: table-cell; width: 25%; border: 1px solid #E8E8ED; padding: 8px 10px; }
    .stat-label { font-size: 7px; color: #6E6E73; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 3px; }
    .stat-value { font-size: 13px; font-weight: 300; }
    .stat-value.green { color: #16A34A; }
    .stat-value.orange { color: #EA580C; }
    .stat-value.blue { color: #0071E3; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #F5F5F7; color: #6E6E73; font-size: 7.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; padding: 5px 6px; border: 1px solid #D2D2D7; }
    th.right, td.right { text-align: right; }
    td { padding: 5px 6px; border: 1px solid #D2D2D7; font-size: 8.5px; }
    tr:nth-child(even) td { background: #FAFAFA; }
    tfoot td { background: #F5F5F7 !important; font-weight: 700; font-size: 8.5px; border: 1px solid #D2D2D7; }
    .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7px; font-weight: 600; }
    .badge-green  { background: #DCFCE7; color: #16A34A; }
    .badge-purple { background: #F3E8FF; color: #7C3AED; }
    .badge-orange { background: #FFF7ED; color: #EA580C; }
    .badge-yellow { background: #FEFCE8; color: #CA8A04; }
    .muted { color: #86868B; }
    .remaining-zero { color: #16A34A; font-weight: 600; }
    .remaining-pos  { color: #EA580C; font-weight: 600; }
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
        <h1>Dispatch History — {{ $selectedCatalogue->name }}</h1>
        <p>Casualite — Per-customer dispatch status and remaining pieces</p>
        <p class="meta">Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>
</div>

@php
    $fullyDispatched   = $orders->where('status', 'dispatched')->count();
    $partiallyDisp     = $orders->where('status', 'partially_dispatched')->count();
    $pending           = $orders->whereIn('status', ['confirmed', 'stitching'])->count();
    $totalOrdered      = $orders->sum('total_ordered');
    $totalDispatched   = $orders->sum('total_dispatched');
    $totalRemaining    = $orders->sum('total_remaining');

    $statusLabels = [
        'confirmed'            => ['label' => 'Confirmed',    'class' => 'badge-yellow'],
        'stitching'            => ['label' => 'Stitching',    'class' => 'badge-orange'],
        'partially_dispatched' => ['label' => 'Partial',      'class' => 'badge-purple'],
        'dispatched'           => ['label' => 'Dispatched',   'class' => 'badge-green'],
    ];
@endphp

<div class="stats">
    <div class="stat-box">
        <div class="stat-label">Fully Dispatched</div>
        <div class="stat-value green">{{ $fullyDispatched }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Partially Dispatched</div>
        <div class="stat-value orange">{{ $partiallyDisp }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Pending Dispatch</div>
        <div class="stat-value blue">{{ $pending }}</div>
    </div>
    <div class="stat-box">
        <div class="stat-label">Pieces Remaining</div>
        <div class="stat-value {{ $totalRemaining > 0 ? 'orange' : 'green' }}">{{ number_format($totalRemaining) }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Customer</th>
            <th>City</th>
            <th>Order #</th>
            <th>Status</th>
            <th class="right">Ordered</th>
            <th class="right">Dispatched</th>
            <th class="right">Remaining</th>
            <th>First Dispatch</th>
        </tr>
    </thead>
    <tbody>
        @foreach($orders as $i => $order)
        @php $s = $statusLabels[$order->status] ?? ['label' => $order->status, 'class' => 'badge-yellow']; @endphp
        <tr>
            <td class="muted">{{ $i + 1 }}</td>
            <td>{{ $order->customer?->name ?? $order->submitted_name }}</td>
            <td class="muted">{{ $order->customer?->city ?? $order->submitted_city ?? '—' }}</td>
            <td>#{{ $order->order_number }}</td>
            <td><span class="badge {{ $s['class'] }}">{{ $s['label'] }}</span></td>
            <td class="right">{{ number_format($order->total_ordered) }}</td>
            <td class="right">{{ number_format($order->total_dispatched) }}</td>
            <td class="right {{ $order->total_remaining === 0 ? 'remaining-zero' : 'remaining-pos' }}">{{ number_format($order->total_remaining) }}</td>
            <td class="muted">{{ $order->first_dispatch?->format('d M Y') ?? '—' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5">Total</td>
            <td class="right">{{ number_format($totalOrdered) }}</td>
            <td class="right">{{ number_format($totalDispatched) }}</td>
            <td class="right">{{ number_format($totalRemaining) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

</body>
</html>
