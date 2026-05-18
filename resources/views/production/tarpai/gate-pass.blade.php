<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass — TP-{{ str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            color: #1D1D1F;
            background: #fff;
            padding: 32px;
        }

        /* ── Header ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1D1D1F;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .doc-title { text-align: right; }
        .doc-title h1 { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .doc-title .ref { font-size: 13px; color: #6E6E73; margin-top: 4px; }

        /* ── Meta grid ── */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px 24px;
            background: #F5F5F7;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .meta-item label {
            display: block;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6E6E73;
            margin-bottom: 3px;
        }
        .meta-item .val { font-size: 13px; font-weight: 600; color: #1D1D1F; }
        .house-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            background: #EDE9FE;
            color: #5B21B6;
        }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th {
            background: #1D1D1F;
            color: #fff;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 8px 10px;
        }
        th.left { text-align: left; padding-left: 14px; }
        td {
            padding: 9px 10px;
            text-align: center;
            border-bottom: 1px solid #E8E8ED;
            font-size: 13px;
        }
        td.design-name { text-align: left; padding-left: 14px; font-weight: 600; }
        td.total-cell { font-weight: 700; }
        tr.grand-total td {
            background: #F5F5F7;
            font-weight: 700;
            font-size: 13px;
            border-top: 2px solid #1D1D1F;
            border-bottom: none;
        }
        td.zero { color: #C7C7CC; }

        /* ── Signature block ── */
        .signature-block {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-top: 40px;
        }
        .sig-box {
            flex: 1;
            border-top: 1px solid #1D1D1F;
            padding-top: 8px;
        }
        .sig-box .sig-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: #6E6E73; }
        .sig-box .sig-name  { font-size: 13px; font-weight: 600; margin-top: 4px; }

        /* ── Footer ── */
        .footer {
            margin-top: 32px;
            border-top: 1px solid #E8E8ED;
            padding-top: 12px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #86868B;
        }

        /* ── Print actions (screen only) ── */
        .print-actions {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print { background: #0071E3; color: #fff; }
        .btn-back  { background: #F5F5F7; color: #1D1D1F; }

        @media print {
            body { padding: 16px; }
            .print-actions { display: none !important; }
            @page { margin: 12mm; size: A4; }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <button class="btn btn-print" onclick="window.print()">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Print / Save PDF
    </button>
    <a href="{{ route('tarpai-sends.show', $tarpaiSend) }}" class="btn btn-back">← Back</a>
</div>

<!-- Header -->
<div class="header">
    <div>
        <img src="/images/casualite-logo.png" style="height:56px; width:auto; display:block;">
    </div>
    <div class="doc-title">
        <h1>Gate Pass</h1>
        <div class="ref">TP-{{ str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) }}</div>
    </div>
</div>

<!-- Meta -->
<div class="meta-grid">
    <div class="meta-item">
        <label>Date</label>
        <div class="val">{{ $tarpaiSend->sent_date->format('d M Y') }}</div>
    </div>
    <div class="meta-item">
        <label>Catalogue</label>
        <div class="val">{{ $tarpaiSend->catalogue->name ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Destination</label>
        <div class="val"><span class="house-badge">{{ $tarpaiSend->tarpaiHouseLabel() }}</span></div>
    </div>
    <div class="meta-item">
        <label>Per Piece Rate</label>
        <div class="val">Rs. {{ lacs_format($tarpaiSend->per_piece_price, 0) }} / piece</div>
    </div>
    <div class="meta-item">
        <label>Issued By</label>
        <div class="val">{{ $tarpaiSend->loggedBy->name ?? '—' }}</div>
    </div>
    <div class="meta-item">
        <label>Component</label>
        <div class="val">Kameez only</div>
    </div>
</div>

<!-- Pieces Table -->
@php $sizes = ['xs', 's', 'm', 'l', 'xl']; $grandTotal = 0; @endphp
<table>
    <thead>
        <tr>
            <th class="left">Design</th>
            @foreach($sizes as $size)<th>{{ strtoupper($size) }}</th>@endforeach
            <th>Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($designGroups as $designId => $items)
        @php
            $design   = $designsById[$designId] ?? null;
            $rowTotal = $items->sum('quantity');
            $grandTotal += $rowTotal;
        @endphp
        <tr>
            <td class="design-name">{{ $design?->name ?? "Design #{$designId}" }}</td>
            @foreach($sizes as $size)
            @php $qty = $items->where('size', $size)->sum('quantity'); @endphp
            <td class="{{ $qty === 0 ? 'zero' : '' }}">{{ $qty ?: '—' }}</td>
            @endforeach
            <td class="total-cell">{{ $rowTotal }}</td>
        </tr>
        @endforeach
        <tr class="grand-total">
            <td class="design-name" style="text-align:left">Grand Total</td>
            @foreach($sizes as $size)
            <td>{{ $designGroups->flatMap->all()->where('size', $size)->sum('quantity') ?: '—' }}</td>
            @endforeach
            <td>{{ $grandTotal }}</td>
        </tr>
    </tbody>
</table>

<!-- Cost Summary -->
<div style="text-align:right; margin-bottom: 32px; font-size:13px;">
    <span style="color:#6E6E73;">Total Pieces: <strong style="color:#1D1D1F;">{{ $grandTotal }}</strong></span>
    &nbsp;&nbsp;|&nbsp;&nbsp;
    <span style="color:#6E6E73;">Total Value: <strong style="color:#1D1D1F;">Rs. {{ lacs_format($grandTotal * $tarpaiSend->per_piece_price, 0) }}</strong></span>
</div>

<!-- Signatures -->
<div class="signature-block">
    <div class="sig-box">
        <div class="sig-label">Issued By (Factory)</div>
        <div class="sig-name">{{ $tarpaiSend->loggedBy->name ?? '—' }}</div>
        <div style="margin-top:32px; color:#6E6E73; font-size:11px;">Signature &amp; Date</div>
    </div>
    <div class="sig-box">
        <div class="sig-label">Received By ({{ $tarpaiSend->tarpaiHouseLabel() }})</div>
        <div class="sig-name">&nbsp;</div>
        <div style="margin-top:32px; color:#6E6E73; font-size:11px;">Signature &amp; Date</div>
    </div>
    <div class="sig-box">
        <div class="sig-label">Signed By Guard</div>
        <div class="sig-name">&nbsp;</div>
        <div style="margin-top:32px; color:#6E6E73; font-size:11px;">Signature &amp; Date</div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <span>Generated {{ now()->format('d M Y, H:i') }} — Casualite Operations</span>
    <span>Gate Pass No: TP-{{ str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

<script>
    // Auto-print when the page loads
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
