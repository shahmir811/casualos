@extends('layouts.app')
@section('title', 'Order Dispatch — #' . $order->order_number)
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('dispatch.index') }}" class="text-[#0066CC] hover:underline text-sm">Dispatch</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Order #{{ $order->order_number }}</span>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">
    {{ session('success') }}
</div>
@endif

@php
    $sizes = ['xs', 's', 'm', 'l', 'xl'];

    // Per-design, per-size totals already dispatched
    $dispatchedTotals = [];
    foreach ($order->dispatchBatches as $batch) {
        foreach ($batch->items as $item) {
            $dispatchedTotals[$item->design_id][$item->size] = ($dispatchedTotals[$item->design_id][$item->size] ?? 0) + $item->quantity;
        }
    }
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left column: order details + action --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Order Details</h2>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Customer</p>
                <p class="font-medium text-[#1D1D1F]">{{ $order->customer->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">City</p>
                <p class="text-[#1D1D1F]">{{ $order->customer->city ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F]">{{ $order->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Order Amount</p>
                <p class="font-semibold text-[#1D1D1F]">PKR {{ number_format($order->total_amount, 0) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Outstanding</p>
                @if($order->outstanding_balance > 0)
                    <p class="font-semibold text-red-600">PKR {{ number_format($order->outstanding_balance, 0) }}</p>
                @else
                    <p class="font-semibold text-green-600">Fully Paid</p>
                @endif
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Status</p>
                @php
                    $dispatchStatusBadge = [
                        'received'             => 'badge bg-blue-100 text-blue-700',
                        'confirmed'            => 'badge bg-yellow-100 text-yellow-700',
                        'stitching'            => 'badge bg-orange-100 text-orange-700',
                        'partially_dispatched' => 'badge bg-purple-100 text-purple-700',
                        'dispatched'           => 'badge bg-green-100 text-green-700',
                        'cancelled'            => 'badge bg-red-100 text-red-700',
                    ];
                    $dispatchStatusLabel = [
                        'received'             => 'Received',
                        'confirmed'            => 'Confirmed',
                        'stitching'            => 'Stitching',
                        'partially_dispatched' => 'Partially Dispatched',
                        'dispatched'           => 'Dispatched',
                        'cancelled'            => 'Cancelled',
                    ];
                @endphp
                <span class="{{ $dispatchStatusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                    {{ $dispatchStatusLabel[$order->status] ?? ucfirst($order->status) }}
                </span>
            </div>
        </div>

        @if($order->status !== 'dispatched' && Auth::user()->role !== 'creative_head')
        <a href="{{ route('dispatch.create', $order) }}" class="btn-primary w-full justify-center">
            {{ $order->dispatchBatches->count() ? 'Dispatch Again' : 'Record Dispatch' }}
        </a>
        @endif
    </div>

    {{-- Right column: progress + batch history --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Dispatch Progress --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Dispatch Progress</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full apple-table">
                    <thead>
                        <tr>
                            <th class="text-left">Design</th>
                            @foreach($sizes as $size)
                                <th class="text-right text-xs">{{ strtoupper($size) }}</th>
                            @endforeach
                            <th class="text-right">Total</th>
                            <th class="text-left text-xs">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $item)
                        @php
                            $did       = $item->design_id;
                            $orderedTotal    = 0;
                            $dispatchedTotal = 0;
                            $remainingCells  = [];
                            foreach ($sizes as $sz) {
                                $ord  = (int) $item->{'qty_' . $sz};
                                $disp = (int) ($dispatchedTotals[$did][$sz] ?? 0);
                                $rem  = max(0, $ord - $disp);
                                $orderedTotal    += $ord;
                                $dispatchedTotal += $disp;
                                $remainingCells[$sz] = ['ordered' => $ord, 'dispatched' => $disp, 'remaining' => $rem];
                            }
                            $remainingTotal = max(0, $orderedTotal - $dispatchedTotal);
                        @endphp
                        <tr>
                            <td class="font-medium">{{ $item->design->name ?? '—' }}</td>
                            @foreach($sizes as $sz)
                            @php $cell = $remainingCells[$sz]; @endphp
                            <td class="text-right text-xs">
                                @if($cell['ordered'] === 0)
                                    <span class="text-[#C7C7CC]">—</span>
                                @elseif($cell['remaining'] === 0)
                                    <span class="text-green-600 font-medium">✓ {{ $cell['ordered'] }}</span>
                                @else
                                    <span class="text-[#1D1D1F]">{{ $cell['dispatched'] }}</span><span class="text-[#86868B]">/{{ $cell['ordered'] }}</span>
                                @endif
                            </td>
                            @endforeach
                            <td class="text-right font-medium">{{ $dispatchedTotal }}<span class="text-[#86868B] font-normal">/{{ $orderedTotal }}</span></td>
                            <td>
                                @if($remainingTotal === 0 && $orderedTotal > 0)
                                    <span class="badge bg-green-100 text-green-700 text-xs">Done</span>
                                @elseif($dispatchedTotal > 0)
                                    <span class="badge bg-orange-100 text-orange-700 text-xs">Partial</span>
                                @else
                                    <span class="badge bg-[#F5F5F7] text-[#86868B] text-xs">Pending</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Batch History --}}
        @if($order->dispatchBatches->count())
        <div class="space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F] px-1">Dispatch Batches</h2>

            @foreach($order->dispatchBatches as $batch)
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-[#1D1D1F]">Batch #{{ $batch->batch_number }}</span>
                        <span class="text-[#6E6E73] text-sm">{{ $batch->dispatch_date->format('d M Y') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-[#6E6E73] text-xs">{{ $batch->totalPieces() }} {{ Str::plural('piece', $batch->totalPieces()) }}</span>
                        @if($batch->cargo_document)
                        @php $ext = strtolower(pathinfo($batch->cargo_document, PATHINFO_EXTENSION)); @endphp
                        <a href="{{ Storage::url($batch->cargo_document) }}" target="_blank" title="View Cargo Document">
                            @if(in_array($ext, ['jpg','jpeg','png','webp']))
                                <img src="{{ Storage::url($batch->cargo_document) }}"
                                     alt="Cargo Doc"
                                     class="h-10 w-10 object-cover rounded-lg border border-[#E8E8ED] hover:opacity-80 transition-opacity shadow-sm">
                            @else
                                <div class="h-10 w-10 flex flex-col items-center justify-center rounded-lg border border-[#E8E8ED] bg-red-50 hover:bg-red-100 transition-colors shadow-sm">
                                    <svg class="w-4 h-4 text-red-500 mb-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-[9px] font-bold text-red-500 leading-none">PDF</span>
                                </div>
                            @endif
                        </a>
                        @endif
                    </div>
                </div>

                @if($batch->shipping_address)
                <div class="px-5 py-2 border-b border-[#F2F2F7] bg-[#FAFAFA]">
                    <p class="text-xs text-[#86868B]">Shipping address: <span class="text-[#1D1D1F]">{{ $batch->shipping_address }}</span></p>
                </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full apple-table">
                        <thead>
                            <tr>
                                <th class="text-left">Design</th>
                                @foreach($sizes as $size)
                                    <th class="text-right text-xs">{{ strtoupper($size) }}</th>
                                @endforeach
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $batchByDesign = $batch->items->groupBy('design_id');
                                $batchSizeTotals = array_fill_keys($sizes, 0);
                                $batchGrandTotal = 0;
                            @endphp
                            @foreach($order->items as $item)
                            @php
                                $batchItems = $batchByDesign[$item->design_id] ?? collect();
                                $batchSizes = $batchItems->pluck('quantity', 'size');
                                $batchTotal = $batchItems->sum('quantity');
                            @endphp
                            @if($batchTotal > 0)
                            @php
                                foreach ($sizes as $sz) {
                                    $batchSizeTotals[$sz] += (int) ($batchSizes[$sz] ?? 0);
                                }
                                $batchGrandTotal += $batchTotal;
                            @endphp
                            <tr>
                                <td class="font-medium">{{ $item->design->name ?? '—' }}</td>
                                @foreach($sizes as $sz)
                                <td class="text-right text-sm">
                                    {{ ($batchSizes[$sz] ?? 0) > 0 ? $batchSizes[$sz] : '—' }}
                                </td>
                                @endforeach
                                <td class="text-right font-medium">{{ $batchTotal }}</td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                                <td class="px-4 py-2.5 text-xs font-semibold text-[#1D1D1F]">Total</td>
                                @foreach($sizes as $sz)
                                <td class="px-4 py-2.5 text-right text-xs font-semibold text-[#1D1D1F]">
                                    {{ $batchSizeTotals[$sz] > 0 ? $batchSizeTotals[$sz] : '—' }}
                                </td>
                                @endforeach
                                <td class="px-4 py-2.5 text-right text-xs font-semibold text-[#1D1D1F]">{{ $batchGrandTotal }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endforeach
        </div>
        @endif

    </div>
</div>

@endsection
