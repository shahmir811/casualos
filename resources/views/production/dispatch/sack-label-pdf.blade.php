<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sack Label — Order #{{ $order->order_number }}</title>
    <style>
        @page {
            margin: 20mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1D1D1F;
            padding: 0 12mm;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24pt;
            padding-bottom: 14pt;
            border-bottom: 2pt solid #1D1D1F;
        }
        .title {
            font-size: 20pt;
            font-weight: bold;
            color: #0071E3;
            letter-spacing: 1pt;
            text-align: right;
        }
        .order-num {
            font-size: 12pt;
            font-weight: bold;
            color: #1D1D1F;
            text-align: right;
            margin-top: 4pt;
        }

        .field-row {
            border-bottom: 1pt solid #E8E8ED;
            padding: 16pt 0;
        }
        .field-label {
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: 1pt;
            color: #6E6E73;
            margin-bottom: 6pt;
        }
        .field-value {
            font-size: 20pt;
            font-weight: bold;
            color: #1D1D1F;
        }

        .sack-row {
            border-bottom: 1pt solid #E8E8ED;
            margin-top: 24pt;
            padding: 20pt 0;
        }
        .pieces-row {
            border-bottom: none;
            margin-top: 24pt;
            padding: 20pt 0 0 0;
        }
        .blank-box {
            border: 2pt solid #1D1D1F;
            border-radius: 8pt;
            padding: 20pt;
            margin-top: 8pt;
            height: 60pt;
        }
    </style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="vertical-align:top; width:50%;">
            @if($logoDataUri)
            <img src="{{ $logoDataUri }}" style="height:54pt; width:auto; display:block;">
            @endif
        </td>
        <td style="vertical-align:top; width:50%;">
            <div class="title">SACK LABEL</div>
            <div class="order-num">Order #{{ $order->order_number }}</div>
        </td>
    </tr>
</table>

<div class="field-row">
    <div class="field-label">Customer Name</div>
    <div class="field-value">{{ $order->customer->name ?? '—' }}</div>
</div>

<div class="field-row">
    <div class="field-label">Contact Number</div>
    <div class="field-value">{{ $order->customer->contact_number ?? '—' }}</div>
</div>

<div class="field-row">
    <div class="field-label">Address</div>
    <div class="field-value">{{ $order->customer->address ?? '—' }}</div>
</div>

<div class="field-row">
    <div class="field-label">City, Country</div>
    <div class="field-value">{{ $order->customer->city ?? '—' }}, {{ $order->customer->country ?? '—' }}</div>
</div>

<div class="field-row sack-row">
    <div class="field-label">Sack #</div>
    <div class="blank-box">&nbsp;</div>
</div>

<div class="field-row pieces-row">
    <div class="field-label">Total Pieces</div>
    <div class="blank-box">&nbsp;</div>
</div>

</body>
</html>
