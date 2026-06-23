<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stitching Return Report — PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            color: #1D1D1F;
            background: #fff;
            padding: 32px 40px;
            max-width: 900px;
            margin: 0 auto;
        }

        /* ── Print button (hidden when printing) ── */
        .print-bar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 28px;
        }
        .print-bar button {
            padding: 8px 20px;
            border: none;
            border-radius: 980px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-print  { background: #0071E3; color: #fff; }
        .btn-close  { background: #F5F5F7; color: #1D1D1F; }

        @media print {
            .print-bar { display: none; }
            body { padding: 20px 24px; }
        }

        /* ── Header ── */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1D1D1F;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .brand { font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #6E6E73; margin-bottom: 4px; }
        .report-title { font-size: 22px; font-weight: 300; color: #1D1D1F; }
        .report-meta  { text-align: right; color: #6E6E73; font-size: 11px; line-height: 1.7; }

        /* ── Info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px 24px;
            background: #F5F5F7;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .info-item label { display: block; font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #86868B; margin-bottom: 2px; }
        .info-item span  { font-size: 13px; color: #1D1D1F; font-weight: 500; }

        /* ── Section headings ── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6E6E73;
            margin-bottom: 10px;
            margin-top: 24px;
        }

        /* ── Tables ── */
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th {
            background: #F5F5F7;
            padding: 7px 10px;
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6E6E73;
            border-bottom: 1px solid #E8E8ED;
        }
        th.right, td.right { text-align: right; }
        th.center, td.center { text-align: center; }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #F2F2F7;
            color: #1D1D1F;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tfoot td {
            background: #F5F5F7;
            border-top: 2px solid #E8E8ED;
            font-weight: 700;
            font-size: 11px;
            border-bottom: none;
        }

        /* ── Status badges ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 980px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .badge-done    { background: #F0FFF4; color: #2D8B4E; }
        .badge-partial { background: #FFFBF0; color: #CC7A00; }
        .badge-pending { background: #F5F5F7; color: #86868B; }

        /* ── Cell colour coding ── */
        .done    { color: #2D8B4E; font-weight: 600; }
        .partial { color: #CC7A00; font-weight: 600; }
        .zero    { color: #C7C7CC; }

        /* ── Component label in return history ── */
        .comp-label {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            background: #E8F4FD;
            color: #0066CC;
            font-size: 9px;
            font-weight: 700;
            margin-right: 3px;
            text-transform: uppercase;
        }

        /* ── Return batch card ── */
        .batch {
            border: 1px solid #E8E8ED;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .batch-id   { font-size: 11px; font-weight: 700; color: #6E6E73; letter-spacing: 0.06em; }
        .batch-date { font-size: 11px; color: #6E6E73; margin-left: 8px; }
        .batch-total{ font-size: 13px; font-weight: 700; color: #2D8B4E; }

        .comp-block { margin-top: 8px; }
        .comp-block-title { font-size: 9px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #86868B; margin-bottom: 4px; }
        .size-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .size-chip { font-size: 12px; }
        .size-chip span { font-weight: 700; text-transform: uppercase; }

        .batch-by { font-size: 10px; color: #86868B; margin-top: 8px; }

        /* ── Footer ── */
        .report-footer {
            margin-top: 32px;
            padding-top: 12px;
            border-top: 1px solid #E8E8ED;
            font-size: 10px;
            color: #86868B;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

@php
    $paId       = 'PA-' . str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT);
    $sizes      = ['xs', 's', 'm', 'l', 'xl'];
    $components = ['kameez', 'shalwar', 'dupatta'];
    $compLabels = ['kameez' => 'Kameez', 'shalwar' => 'Shalwar', 'dupatta' => 'Dupatta'];
@endphp

{{-- Print / Close buttons --}}
<div class="print-bar">
    <button class="btn-close" onclick="window.close()">Close</button>
    <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
</div>

{{-- Header --}}
<div class="report-header">
    <div>
        <div class="brand">Casualite — CasualiteOS</div>
        <div class="report-title">Stitching Return Report</div>
        <div style="font-size:13px; color:#6E6E73; margin-top:4px;">{{ $paId }}</div>
    </div>
    <div class="report-meta">
        <div>Generated: {{ now()->format('d M Y, h:i A') }}</div>
        <div>By: {{ auth()->user()->name }}</div>
    </div>
</div>

{{-- Assignment info --}}
<div class="info-grid">
    <div class="info-item">
        <label>Assignment ID</label>
        <span>{{ $paId }}</span>
    </div>
    <div class="info-item">
        <label>Catalogue</label>
        <span>{{ $productionAssignment->catalogue->name ?? '—' }}</span>
    </div>
    <div class="info-item">
        <label>Design</label>
        <span>{{ $productionAssignment->design->name ?? '—' }}</span>
    </div>
    <div class="info-item">
        <label>Stitching Unit</label>
        <span>
            @if($productionAssignment->stitchingUnit)
                Unit {{ $productionAssignment->stitchingUnit->number }} — {{ $productionAssignment->stitchingUnit->name }}
            @else
                —
            @endif
        </span>
    </div>
    <div class="info-item">
        <label>Assignment Date</label>
        <span>{{ $productionAssignment->assignment_date->format('d M Y') }}</span>
    </div>
    <div class="info-item">
        <label>Total Assigned</label>
        <span>{{ number_format($totalAssigned) }} pieces</span>
    </div>
</div>

{{-- Component × Size breakdown --}}
<div class="section-title">Component Breakdown — Assigned vs Returned vs Outstanding</div>

<table>
    <thead>
        <tr>
            <th>Component</th>
            @foreach($sizes as $size)
            <th class="center">{{ strtoupper($size) }}</th>
            @endforeach
            <th class="right">Returned</th>
            <th class="right">Outstanding</th>
            <th class="center">Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($components as $comp)
        @php
            $compReturned    = collect($sizes)->sum(fn($s) => $matrix[$s][$comp]['returned']);
            $compOutstanding = collect($sizes)->sum(fn($s) => $matrix[$s][$comp]['outstanding']);
            $compDone        = $totalAssigned > 0 && $compOutstanding === 0;
            $compPartial     = !$compDone && $compReturned > 0;
        @endphp
        <tr>
            <td><strong>{{ $compLabels[$comp] }}</strong></td>
            @foreach($sizes as $size)
            @php
                $cell = $matrix[$size][$comp];
                $isDone = $cell['assigned'] > 0 && $cell['outstanding'] === 0;
            @endphp
            <td class="center">
                @if($cell['assigned'] === 0)
                    <span class="zero">—</span>
                @else
                    <span class="{{ $isDone ? 'done' : ($cell['returned'] > 0 ? 'partial' : '') }}">
                        {{ $cell['returned'] }}
                    </span>
                    <span class="zero">/{{ $cell['assigned'] }}</span>
                @endif
            </td>
            @endforeach
            <td class="right {{ $compDone ? 'done' : ($compPartial ? 'partial' : '') }}">
                {{ $compReturned }} / {{ $totalAssigned }}
            </td>
            <td class="right {{ $compOutstanding > 0 ? 'partial' : 'done' }}">
                {{ $compOutstanding }}
            </td>
            <td class="center">
                @if($compDone)
                    <span class="badge badge-done">Done</span>
                @elseif($compPartial)
                    <span class="badge badge-partial">Partial</span>
                @else
                    <span class="badge badge-pending">Pending</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td>Assigned</td>
            @foreach($sizes as $size)
            <td class="center">{{ $matrix[$size]['kameez']['assigned'] > 0 ? $matrix[$size]['kameez']['assigned'] : '—' }}</td>
            @endforeach
            <td class="right" colspan="3">{{ $totalAssigned }} pcs total</td>
        </tr>
    </tfoot>
</table>

{{-- Return history --}}
@if($stitchingReturns->isNotEmpty())
<div class="section-title">Return History — {{ $stitchingReturns->count() }} batch{{ $stitchingReturns->count() > 1 ? 'es' : '' }}</div>

@foreach($stitchingReturns as $ret)
@php
    $batchComponents = $ret->items->pluck('component')->unique()->values();
    $batchTotal      = $ret->items->sum('quantity');
@endphp
<div class="batch">
    <div class="batch-header">
        <div>
            <span class="batch-id">SR-{{ str_pad($ret->id, 4, '0', STR_PAD_LEFT) }}</span>
            <span class="batch-date">{{ $ret->return_date->format('d M Y') }}</span>
            @foreach($batchComponents as $comp)
            <span class="comp-label">{{ $comp }}</span>
            @endforeach
        </div>
        <span class="batch-total">{{ number_format($batchTotal) }} pcs total</span>
    </div>

    @foreach($batchComponents as $comp)
    @php
        $sizeOrder = ['xs' => 0, 's' => 1, 'm' => 2, 'l' => 3, 'xl' => 4];
        $compItems = $ret->items->where('component', $comp)->sortBy(fn($i) => $sizeOrder[$i->size] ?? 99);
    @endphp
    <div class="comp-block">
        <div class="comp-block-title">{{ $compLabels[$comp] ?? ucfirst($comp) }}</div>
        <div class="size-row">
            @foreach($compItems as $item)
            <div class="size-chip"><span>{{ strtoupper($item->size) }}</span>: {{ $item->quantity }}</div>
            @endforeach
        </div>
    </div>
    @endforeach

    <div class="batch-by">Logged by {{ $ret->loggedBy->name ?? '—' }}</div>
</div>
@endforeach

@else
<p style="color:#86868B; font-size:12px; margin-top:12px;">No return batches logged yet.</p>
@endif

{{-- Footer --}}
<div class="report-footer">
    <span>Casualite — CasualiteOS Internal Report</span>
    <span>{{ $paId }} · {{ $productionAssignment->catalogue->name ?? '' }} · {{ $productionAssignment->design->name ?? '' }}</span>
</div>

</body>
</html>
