@extends('layouts.app')
@section('title', 'Dispatch Optimizer')
@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dispatch Optimizer</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Recommended combination of pending orders to dispatch so packed inventory is used up as completely as possible.</p>
</div>

@if(!$catalogue)
<div class="card p-12 text-center">
    <p class="text-[#86868B]">No catalogue selected. Choose a catalogue from the sidebar to see a dispatch recommendation.</p>
</div>
@else

@php
    $stock        = $result['stock'];
    $remaining    = $result['remainingStock'];
    $recommended  = $result['recommended'];
    $piecesToDispatch = $recommended->sum('total_qty');

    $clearedCells = 0;
    $totalCells   = 0;
    foreach ($stock as $designId => $sizeQtys) {
        foreach ($sizes as $size) {
            if (($sizeQtys[$size] ?? 0) > 0) {
                $totalCells++;
                if (($remaining[$designId][$size] ?? 0) === 0) $clearedCells++;
            }
        }
    }
@endphp

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Orders Considered</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($result['consideredCount']) }}</p>
        <p class="text-[#86868B] text-xs mt-1">unpaid balance excluded</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Orders Recommended</p>
        <p class="text-3xl font-light text-[#0071E3]">{{ number_format($recommended->count()) }}</p>
        <p class="text-[#86868B] text-xs mt-1">to dispatch now</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pieces To Dispatch</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($piecesToDispatch) }}</p>
        <p class="text-[#86868B] text-xs mt-1">of {{ number_format(collect($stock)->flatMap(fn($s) => $s)->sum()) }} packed</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Size Lines Cleared</p>
        <p class="text-3xl font-light text-green-600">{{ $clearedCells }}/{{ $totalCells }}</p>
        <p class="text-[#86868B] text-xs mt-1">design+size cells reaching zero</p>
    </div>
</div>

<div class="mb-6">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
        Recommended Dispatch Plan
    </h2>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full apple-table min-w-[640px]">
            <thead>
                <tr>
                    <th class="text-left">Order #</th>
                    <th class="text-left">Customer</th>
                    @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                    <th class="text-right">Total Qty</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recommended as $row)
                @php
                    $bySize = array_fill_keys($sizes, 0);
                    foreach ($row['demand'] as $designSizes) {
                        foreach ($designSizes as $size => $qty) {
                            $bySize[$size] += $qty;
                        }
                    }
                @endphp
                <tr>
                    <td class="font-medium">#{{ $row['order_number'] }}</td>
                    <td>{{ $row['customer_name'] }}</td>
                    @foreach($sizes as $size)
                    <td class="text-right">{{ $bySize[$size] > 0 ? number_format($bySize[$size]) : '—' }}</td>
                    @endforeach
                    <td class="text-right font-bold text-[#0071E3]">
                        {{ number_format($row['total_qty']) }}
                        @if($row['total_qty'] < $row['order_remaining_qty'])
                        <span class="block text-[10px] font-normal text-[#86868B]">of {{ number_format($row['order_remaining_qty']) }} pending</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('dispatch.show', $row['order_id']) }}" class="btn-primary text-xs">Dispatch →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($sizes) + 4 }}" class="text-center py-8 text-[#86868B]">
                        No pending order has any demand that fits within the current packed inventory.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div>
        <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3">Current Packed Inventory</h2>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full apple-table min-w-[420px]">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($designs as $design)
                    @php $rowTotal = array_sum($stock[$design->id] ?? []); @endphp
                    @if($rowTotal > 0)
                    <tr>
                        <td class="font-medium">{{ $design->name }}</td>
                        @foreach($sizes as $size)
                        <td class="text-right">{{ ($stock[$design->id][$size] ?? 0) > 0 ? number_format($stock[$design->id][$size]) : '—' }}</td>
                        @endforeach
                        <td class="text-right font-bold text-[#0071E3]">{{ number_format($rowTotal) }}</td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3">Projected Leftover After Recommended Dispatch</h2>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full apple-table min-w-[420px]">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($designs as $design)
                    @php $rowTotal = array_sum($stock[$design->id] ?? []); @endphp
                    @if($rowTotal > 0)
                    @php $leftoverTotal = array_sum($remaining[$design->id] ?? []); @endphp
                    <tr>
                        <td class="font-medium">{{ $design->name }}</td>
                        @foreach($sizes as $size)
                        @php $cell = $remaining[$design->id][$size] ?? 0; @endphp
                        <td class="text-right {{ $cell === 0 && ($stock[$design->id][$size] ?? 0) > 0 ? 'bg-green-50 text-green-700 font-semibold' : '' }}">
                            {{ $cell > 0 ? number_format($cell) : (($stock[$design->id][$size] ?? 0) > 0 ? '0' : '—') }}
                        </td>
                        @endforeach
                        <td class="text-right font-bold {{ $leftoverTotal === 0 ? 'text-green-600' : 'text-[#1D1D1F]' }}">{{ number_format($leftoverTotal) }}</td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

@endif

@endsection
