<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Ledger — {{ $customer->name }}</title>
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
        .doc-title .customer-name { font-size: 15px; font-weight: 600; color: #1D1D1F; margin-top: 4px; }
        .doc-title .meta { font-size: 12px; color: #6E6E73; margin-top: 3px; }

        /* ── Balance summary ── */
        .balance-bar {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }
        .balance-box {
            background: #F5F5F7;
            border-radius: 8px;
            padding: 12px 20px;
            text-align: right;
        }
        .balance-box .label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #6E6E73;
            margin-bottom: 4px;
        }
        .balance-box .amount { font-size: 18px; font-weight: 300; }
        .balance-debit  { color: #FF3B30; }
        .balance-credit { color: #30D158; }
        .balance-zero   { color: #1D1D1F; }

        /* ── Table ── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead tr {
            background: #1D1D1F;
        }
        th {
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 8px 10px;
            text-align: left;
        }
        th.right { text-align: right; }
        td {
            padding: 9px 10px;
            border-bottom: 1px solid #E8E8ED;
            font-size: 12px;
            vertical-align: middle;
        }
        td.right { text-align: right; font-family: monospace; }
        tr:last-child td { border-bottom: none; }
        tr:nth-child(even) td { background: #FAFAFA; }

        /* ── Type badges ── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .badge-advance_received { background: #DBEAFE; color: #1D4ED8; }
        .badge-order_charged    { background: #FEE2E2; color: #B91C1C; }
        .badge-payment_received { background: #D1FAE5; color: #065F46; }
        .badge-credit_applied   { background: #CCFBF1; color: #0F766E; }
        .badge-order_reduced    { background: #FEF9C3; color: #854D0E; }
        .badge-refund_issued    { background: #FFEDD5; color: #C2410C; }

        /* ── Amount colours ── */
        .amt-positive { color: #1D1D1F; }
        .amt-negative { color: #30D158; }
        .amt-zero     { color: #6E6E73; }

        /* ── Footer ── */
        .footer {
            margin-top: 24px;
            border-top: 1px solid #E8E8ED;
            padding-top: 10px;
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
            z-index: 100;
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
    <a href="{{ route('customers.ledger', $customer) }}" class="btn btn-back">← Back</a>
</div>

<!-- Header -->
<div class="header">
    <div>
        <img src="/images/casualite-logo.png" style="height:56px; width:auto; display:block;">
    </div>
    <div class="doc-title">
        <h1>Customer Ledger</h1>
        <div class="customer-name">{{ $customer->name }}</div>
        <div class="meta">Generated {{ now()->format('d M Y, H:i') }}</div>
    </div>
</div>

<!-- Outstanding Balance -->
<div class="balance-bar">
    <div class="balance-box">
        <div class="label">Outstanding Balance</div>
        @php
            $balanceClass = $balance > 0 ? 'balance-debit' : ($balance < 0 ? 'balance-credit' : 'balance-zero');
            $balanceLabel = $balance > 0 ? 'Debit' : ($balance < 0 ? 'Credit' : '');
        @endphp
        <div class="amount {{ $balanceClass }}">
            PKR {{ lacs_format(abs($balance), 0) }}
            @if($balanceLabel) <span style="font-size:13px;">{{ $balanceLabel }}</span> @endif
        </div>
    </div>
</div>

<!-- Ledger Table -->
@php
    $typeBadge = [
        'advance_received'   => 'badge-advance_received',
        'order_charged'      => 'badge-order_charged',
        'payment_received'   => 'badge-payment_received',
        'credit_applied'     => 'badge-credit_applied',
        'order_reduced'      => 'badge-order_reduced',
        'refund_issued'      => 'badge-refund_issued',
    ];
@endphp

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th>Catalogue</th>
            <th>Order</th>
            <th>By</th>
            <th class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse($entries as $entry)
        @php
            $refKey   = $entry->reference_type . ':' . $entry->reference_id;
            $orderRef = $orderMap[$refKey] ?? null;
            $prefix   = $entry->amount > 0 ? '+' : ($entry->amount < 0 ? '−' : '');
            $amtClass = $entry->amount > 0 ? 'amt-positive' : ($entry->amount < 0 ? 'amt-negative' : 'amt-zero');
        @endphp
        <tr>
            <td style="white-space:nowrap; color:#6E6E73;">{{ $entry->created_at->format('d M Y') }}</td>
            <td>
                <span class="badge {{ $typeBadge[$entry->transaction_type] ?? '' }}">
                    {{ str_replace('_', ' ', $entry->transaction_type) }}
                </span>
            </td>
            <td style="color:#1D1D1F; max-width:220px;">
                {{ $entry->notes ? Str::of($entry->notes)->explode("\n")->first() : '—' }}
            </td>
            <td style="color:#6E6E73; white-space:nowrap;">{{ $orderRef['catalogue'] ?? '—' }}</td>
            <td style="white-space:nowrap; font-family:monospace;">
                @if($orderRef)
                    #{{ $orderRef['number'] }}
                @else
                    <span style="color:#C7C7CC;">—</span>
                @endif
            </td>
            <td style="color:#6E6E73; white-space:nowrap;">{{ $entry->createdBy->name ?? '—' }}</td>
            <td class="right {{ $amtClass }}">{{ $prefix }}PKR {{ lacs_format(abs($entry->amount), 0) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align:center; color:#86868B; padding:24px;">No ledger entries.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<!-- Footer -->
<div class="footer">
    <span>Generated {{ now()->format('d M Y, H:i') }} — Casualite Operations</span>
    <span>{{ $entries->count() }} {{ Str::plural('entry', $entries->count()) }} · {{ $customer->name }}</span>
</div>

<script>
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
