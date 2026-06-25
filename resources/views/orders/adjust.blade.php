@extends('layouts.app')
@section('title', 'Adjust Order #' . $order->order_number)
@section('content')

@php
    $firstItem  = $order->items->first();
    $numDesigns = $order->items->count();
    $benchmark  = $order->catalogue->quantity_benchmark;

    // Normal total: sum of each design's selling_price
    $normalTotal = $order->items->sum(fn($item) => (float) ($item->design->selling_price ?? $item->unit_price));

    // Discount total: sum of each design's discount_price (if set), else selling_price
    $discountTotal = $order->items->sum(fn($item) => (float) (
        $item->design->discount_price ?? $item->design->selling_price ?? $item->unit_price
    ));

    $currentXS = (int) ($firstItem?->qty_xs ?? 0);
    $currentS  = (int) ($firstItem?->qty_s  ?? 0);
    $currentM  = (int) ($firstItem?->qty_m  ?? 0);
    $currentL  = (int) ($firstItem?->qty_l  ?? 0);
    $currentXL = (int) ($firstItem?->qty_xl ?? 0);
@endphp

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Adjust Order</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Adjust Order</h1>
<p class="text-[#6E6E73] text-sm mb-6">
    Order #{{ $order->order_number }} · {{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }}
</p>

{{-- Current Order Summary --}}
<div class="card mb-6 overflow-hidden">
    <div class="px-5 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Current Order</h2>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Catalogue</th>
                <th class="text-center px-3">XS</th>
                <th class="text-center px-3">S</th>
                <th class="text-center px-3">M</th>
                <th class="text-center px-3">L</th>
                <th class="text-center px-3">XL</th>
                <th class="text-center">Qty / Design</th>
                <th class="text-center">Total Pieces</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <p class="font-medium text-[#1D1D1F] text-sm">{{ $order->catalogue->name ?? '—' }}</p>
                    <p class="text-xs text-[#86868B] mt-0.5">{{ $numDesigns }} designs</p>
                </td>
                @foreach(['xs' => $currentXS, 's' => $currentS, 'm' => $currentM, 'l' => $currentL, 'xl' => $currentXL] as $size => $qty)
                <td class="text-center tabular-nums px-3 {{ $qty ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qty ?: '—' }}</td>
                @endforeach
                <td class="text-center font-semibold text-[#1D1D1F] tabular-nums">{{ $currentXS + $currentS + $currentM + $currentL + $currentXL }}</td>
                <td class="text-center font-semibold text-[#0071E3] tabular-nums">{{ ($currentXS + $currentS + $currentM + $currentL + $currentXL) * $numDesigns }}</td>
                <td class="text-right font-semibold text-[#1D1D1F] tabular-nums">PKR {{ number_format($order->total_amount, 0) }}</td>
            </tr>
        </tbody>
    </table>
</div>

{{-- Designs reference table --}}
<div class="card mb-6 overflow-hidden">
    <div class="px-5 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Designs in This Order</h2>
        <p class="text-xs text-[#6E6E73] mt-0.5">The new quantities you enter will apply to every design below.</p>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">#</th>
                <th class="text-left">Design</th>
                <th class="text-right">Selling Price</th>
                @if($benchmark !== null)
                <th class="text-right">Discount Price</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
            <tr>
                <td class="text-[#6E6E73] text-xs tabular-nums">{{ $i + 1 }}</td>
                <td class="font-medium text-[#1D1D1F]">{{ $item->design->name ?? '—' }}</td>
                <td class="text-right tabular-nums text-[#6E6E73]">PKR {{ number_format($item->design->selling_price ?? $item->unit_price, 0) }}</td>
                @if($benchmark !== null)
                <td class="text-right tabular-nums text-[#34C759]">
                    {{ $item->design->discount_price !== null ? 'PKR ' . number_format($item->design->discount_price, 0) : '—' }}
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Adjust form --}}
<div class="max-w-2xl"
     x-data="{
        xs: {{ old('qty_xs', $currentXS) }},
        s:  {{ old('qty_s',  $currentS) }},
        m:  {{ old('qty_m',  $currentM) }},
        l:  {{ old('qty_l',  $currentL) }},
        xl: {{ old('qty_xl', $currentXL) }},
        benchmark:     {{ $benchmark !== null ? (int) $benchmark : 'null' }},
        normalTotal:   {{ (float) $normalTotal }},
        discountTotal: {{ (float) $discountTotal }},
        numDesigns: {{ $numDesigns }},

        get piecesPerDesign() {
            return (parseInt(this.xs)||0) + (parseInt(this.s)||0) + (parseInt(this.m)||0) + (parseInt(this.l)||0) + (parseInt(this.xl)||0);
        },
        get totalPieces() {
            return this.piecesPerDesign * this.numDesigns;
        },
        get useDiscount() {
            return this.benchmark !== null && this.piecesPerDesign > this.benchmark;
        },
        get effectiveUnitPrices() {
            return this.useDiscount ? this.discountTotal : this.normalTotal;
        },
        get newTotal() {
            return this.piecesPerDesign * this.effectiveUnitPrices;
        },
        formatPkr(n) {
            const str = String(Math.round(Math.abs(n)));
            if (str.length <= 3) return 'PKR ' + str;
            const last3 = str.slice(-3);
            let rem = str.slice(0, -3);
            const groups = [];
            while (rem.length > 0) {
                const take = Math.min(2, rem.length);
                groups.unshift(rem.slice(-take));
                rem = rem.slice(0, -take);
            }
            return 'PKR ' + groups.join(',') + ',' + last3;
        }
     }">

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('orders.adjust.store', $order) }}">
        @csrf

        <div class="card p-6 mb-5">
            <h2 class="text-[#1D1D1F] text-sm font-semibold mb-1">New Quantities Per Size</h2>
            <p class="text-[#6E6E73] text-xs mb-5">
                Enter how many pieces of each size this customer should receive from every design.
                Entering 2 for S means 2 S-size pieces from each of the {{ $numDesigns }} designs.
            </p>

            <div class="grid grid-cols-5 gap-3">
                @foreach(['xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'] as $key => $label)
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2 text-center">
                        {{ $label }}
                    </label>
                    <input type="number"
                           name="qty_{{ $key }}"
                           min="0"
                           x-model="{{ $key }}"
                           class="apple-input text-center text-lg font-semibold"
                           placeholder="0">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Live summary --}}
        <div class="card p-5 mb-6">
            <h2 class="text-[#1D1D1F] text-sm font-semibold mb-4">New Order Summary</h2>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-1">Pieces / Design</p>
                    <p class="text-[#1D1D1F] text-2xl font-light tabular-nums" x-text="piecesPerDesign"></p>
                </div>
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-1">Total Pieces</p>
                    <p class="text-[#0071E3] text-2xl font-light tabular-nums" x-text="totalPieces"></p>
                </div>
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-1">New Total Amount</p>
                    <p class="text-[#1D1D1F] text-2xl font-light tabular-nums" x-text="formatPkr(newTotal)"></p>
                    @if($benchmark !== null)
                    <p class="text-xs mt-1"
                       x-text="useDiscount ? 'Discount pricing active' : 'Normal pricing'"
                       :class="useDiscount ? 'text-[#34C759] font-medium' : 'text-[#6E6E73]'"></p>
                    @endif
                </div>
            </div>
            @if($benchmark !== null)
            <p class="text-xs text-[#6E6E73] text-center mt-4">
                Benchmark: {{ number_format($benchmark) }} pieces per design — discount pricing applies above this threshold.
            </p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary" :disabled="piecesPerDesign === 0">
                Save Adjusted Order
            </button>
            <a href="{{ route('orders.show', $order) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
