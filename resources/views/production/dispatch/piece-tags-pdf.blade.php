<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Piece Tags</title>
    <style>
        @page {
            size: 144pt 72pt;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
        }
        .tag {
            width: 144pt;
            padding: 4pt 8pt;
            box-sizing: border-box;
        }
        .brand {
            font-size: 10pt;
            font-weight: bold;
            line-height: 1;
        }
        table.details {
            width: 128pt;
            margin-top: 1pt;
            border-collapse: collapse;
        }
        table.details td {
            padding: 0;
            font-size: 9pt;
            line-height: 1;
        }
        table.details td.price {
            text-align: right;
            font-weight: bold;
        }
        .barcode {
            margin-top: 1pt;
            text-align: center;
            line-height: 0;
        }
        .barcode img {
            height: 20pt;
        }
        .barcode-text {
            margin-top: 1pt;
            text-align: center;
            font-size: 6pt;
            letter-spacing: 0.5pt;
            line-height: 1;
        }
    </style>
</head>
<body>

@foreach($pages as $page)
<div class="tag" @unless($loop->last) style="page-break-after: always;" @endunless>
    <div class="brand">Casualite</div>
    <table class="details">
        <tr>
            <td class="size">{{ $page['size'] }}</td>
            <td class="price">{{ $page['symbol'] }}{{ number_format($page['price'], 0) }}</td>
        </tr>
    </table>
    <div class="barcode">
        <img src="data:image/svg+xml;base64,{{ $page['barcode_svg'] }}">
    </div>
    <div class="barcode-text">{{ $page['barcode_text'] }}</div>
</div>
@endforeach

</body>
</html>
