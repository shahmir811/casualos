<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cost Estimation — {{ $design->name }}</title>
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

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14pt;
            padding-bottom: 12pt;
            border-bottom: 2pt solid #1D1D1F;
        }
        .header-label {
            text-align: right;
        }
        .header-label .title {
            font-size: 18pt;
            font-weight: bold;
            color: #0071E3;
            letter-spacing: 1pt;
        }
        .header-label .subtitle {
            font-size: 10pt;
            font-weight: bold;
            color: #1D1D1F;
            margin-top: 3pt;
        }
        .header-label .meta {
            font-size: 8pt;
            color: #6E6E73;
            margin-top: 2pt;
        }

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

        .section-heading {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
            color: #6E6E73;
            margin: 12pt 0 6pt 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4pt;
        }
        .items-table th {
            background-color: #1D1D1F;
            color: #FFFFFF;
            padding: 4pt 5pt;
            font-size: 7pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            border: 0.5pt solid #444;
        }
        .items-table td {
            padding: 4.5pt 5pt;
            border: 0.5pt solid #D2D2D7;
            font-size: 8.5pt;
            vertical-align: middle;
        }
        .items-table tr.even td {
            background-color: #F9F9F9;
        }
        .items-table .num-cell {
            text-align: right;
        }
        .items-table tr.subtotal-row td {
            background-color: #F2F2F7;
            font-weight: bold;
            font-size: 8.5pt;
            border-top: 1.5pt solid #1D1D1F;
        }

        .summary-wrap {
            width: 100%;
            margin-top: 18pt;
        }
        .summary-inner {
            width: 260pt;
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
            font-size: 12pt;
            padding-top: 5pt;
        }
        .summary-inner .value.accent { color: #FF9500; }
        .clearfix { clear: both; }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40pt;
        }
        .signature-table td {
            border: none;
            width: 50%;
            padding-top: 20pt;
            border-top: 1pt solid #1D1D1F;
            font-size: 8pt;
            color: #6E6E73;
        }
        .signature-table .sig-left { padding-right: 20pt; }
        .signature-table .sig-right { padding-left: 20pt; }

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
            @if($logoDataUri)
            <img src="{{ $logoDataUri }}" style="height:54pt; width:auto; display:block;">
            @endif
        </td>
        <td style="vertical-align:top; width:50%;">
            <div class="header-label">
                <div class="title">COST ESTIMATION</div>
                <div class="subtitle">{{ $design->name }}</div>
                <div class="meta">{{ $catalogue->name }} &middot; {{ optional($costEstimation->estimation_date)->format('d M Y') ?? '—' }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- ── Meta ── --}}
<table class="meta-table">
    <tr>
        <td style="width:32%; padding-right:14pt; vertical-align:top;">
            <div class="meta-label">Catalogue</div>
            <div class="meta-value large">{{ $catalogue->name }}</div>
        </td>
        <td class="divider-cell">&nbsp;</td>
        <td style="width:32%; padding-left:14pt; padding-right:14pt; vertical-align:top;">
            <div class="meta-label">Production Qty</div>
            <div class="meta-value large">{{ number_format($costEstimation->production_plan_qty) }}</div>
            <div class="meta-label" style="margin-top:8pt;">Date</div>
            <div class="meta-value">{{ optional($costEstimation->estimation_date)->format('d M Y') ?? '—' }}</div>
        </td>
        <td class="divider-cell">&nbsp;</td>
        <td style="width:32%; padding-left:14pt; vertical-align:top;">
            <div class="meta-label">Stitched By</div>
            <div class="meta-value">{{ $costEstimation->stitched_by ?: '—' }}</div>
        </td>
    </tr>
</table>

{{-- ── Cost sections ── --}}
@foreach($categories as $key => $label)
    @if($itemsByCategory->has($key))
    @php $catItems = $itemsByCategory->get($key); $catSubtotal = $catItems->sum('amount'); @endphp
    <div class="section-heading">{{ $label }}</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="text-align:left; width:34%;">Particulars</th>
                <th style="text-align:right; width:14%;">Avg</th>
                <th style="text-align:right; width:14%;">Qty</th>
                <th style="text-align:right; width:19%;">Rate</th>
                <th style="text-align:right; width:19%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($catItems as $i => $item)
            <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
                <td>{{ $item->particulars ?: '—' }}</td>
                <td class="num-cell">{{ $item->avg !== null ? number_format($item->avg, 2) : '—' }}</td>
                <td class="num-cell">{{ $item->qty !== null ? number_format($item->qty, 2) : '—' }}</td>
                <td class="num-cell">{{ $item->rate !== null ? number_format($item->rate, 2) : '—' }}</td>
                <td class="num-cell">{{ number_format($item->amount, 0) }}</td>
            </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="4" style="text-align:left;">Subtotal</td>
                <td class="num-cell">PKR {{ number_format($catSubtotal, 0) }}</td>
            </tr>
        </tbody>
    </table>
    @endif
@endforeach

{{-- ── Summary ── --}}
<div class="summary-wrap">
    <table class="summary-inner">
        <tr>
            <td class="label">Market Rate</td>
            <td class="value">{{ $costEstimation->market_rate !== null ? 'PKR ' . number_format($costEstimation->market_rate, 0) : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Margin</td>
            <td class="value">{{ $costEstimation->margin !== null ? 'PKR ' . number_format($costEstimation->margin, 0) : '—' }}</td>
        </tr>
        <tr>
            <td class="label" style="padding-top:6pt;">Actual Cost Total</td>
            <td class="value" style="padding-top:6pt;">PKR {{ number_format($costEstimation->total_cost, 0) }}</td>
        </tr>
        <tr class="grand-total">
            <td class="label">Per Unit Cost</td>
            <td class="value accent">PKR {{ number_format($costEstimation->per_unit_cost, 0) }}</td>
        </tr>
    </table>
    <div class="clearfix"></div>
</div>

{{-- ── Signatures ── --}}
<table class="signature-table">
    <tr>
        <td class="sig-left">Prepared By — {{ $costEstimation->preparedBy->name ?? '—' }}</td>
        <td class="sig-right">Approved By — {{ $costEstimation->approved_by ?: '—' }}</td>
    </tr>
</table>

{{-- ── Footer ── --}}
<div class="footer">
    This is a system-generated cost estimation. &middot; Casualite &middot; Pakistan
</div>

</body>
</html>
