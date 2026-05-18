@extends('layouts.app')
@section('title', 'Dispatch Workspace — #' . $order->order_number)
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('dispatch.index') }}" class="text-[#0066CC] hover:underline text-sm">Dispatch</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('dispatch.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">Order #{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Workspace</span>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">
    {{ session('success') }}
</div>
@endif

@php
    // Build Alpine vals and maxes keyed by "designId_size"
    $alpineVals  = [];
    $alpineMaxes = [];
    foreach ($order->items as $item) {
        foreach ($sizes as $size) {
            $key = $item->design_id . '_' . $size;
            $alpineVals[$key]  = 0;
            $alpineMaxes[$key] = min(
                $remaining[$item->design_id][$size] ?? 0,
                $inStock[$item->design_id][$size]   ?? 0
            );
        }
    }

    $totalOrdered    = $order->items->sum('total_qty');
    $totalDispatched = 0;
    foreach ($dispatchedTotals as $bySizes) {
        foreach ($bySizes as $qty) { $totalDispatched += $qty; }
    }
    $totalRemaining = max(0, $totalOrdered - $totalDispatched);
@endphp

{{-- Page header --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dispatch Workspace</h1>
        <p class="text-[#6E6E73] text-sm mt-1">
            {{ $order->customer->name }} &middot; {{ $order->catalogue->name }}
            &middot; Order #{{ $order->order_number }}
        </p>
    </div>
    <div class="flex items-center gap-3 text-sm">
        @if($order->outstanding_balance > 0)
            <span class="badge bg-red-100 text-red-700">PKR {{ lacs_format($order->outstanding_balance, 0) }} outstanding</span>
        @else
            <span class="badge bg-green-100 text-green-700">Fully paid</span>
        @endif
        @if($totalRemaining === 0)
            <span class="badge bg-green-100 text-green-700">All dispatched</span>
        @else
            <span class="badge bg-orange-100 text-orange-700">{{ $totalRemaining }} pcs remaining</span>
        @endif
    </div>
</div>

<div x-data="{
    vals:  {{ Js::from($alpineVals) }},
    maxes: {{ Js::from($alpineMaxes) }},
    isOver(key) {
        return (this.maxes[key] ?? 0) > 0 && parseInt(this.vals[key] ?? 0) > this.maxes[key];
    },
    get hasOverflow() {
        return Object.keys(this.maxes).some(k => this.isOver(k));
    },
    get hasAnyQty() {
        return Object.values(this.vals).some(v => parseInt(v) > 0);
    },
    rowTotal(designId) {
        return ['xs','s','m','l','xl'].reduce((sum, s) => {
            return sum + (parseInt(this.vals[designId + '_' + s]) || 0);
        }, 0);
    }
}">

    {{-- ── SECTION 1: DISPATCH PROGRESS ─────────────────────────────── --}}
    <div class="card overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
            <h2 class="font-semibold text-[#1D1D1F]">Dispatch Progress</h2>
            <span class="text-[#6E6E73] text-sm">
                {{ $totalDispatched }} / {{ $totalOrdered }} pieces dispatched
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        @foreach($sizes as $size)
                            <th class="text-center text-xs" colspan="2">{{ strtoupper($size) }}</th>
                        @endforeach
                        <th class="text-right">Total</th>
                        <th class="text-left">Status</th>
                    </tr>
                    <tr class="border-b border-[#F2F2F7]">
                        <th></th>
                        @foreach($sizes as $size)
                            <th class="text-right text-[10px] text-[#86868B] font-normal pb-2">disp</th>
                            <th class="text-right text-[10px] text-[#86868B] font-normal pb-2">rem</th>
                        @endforeach
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    @php
                        $did          = $item->design_id;
                        $orderedTotal    = 0;
                        $dispatchedTotal = 0;
                        foreach ($sizes as $sz) {
                            $ord  = (int) $item->{'qty_' . $sz};
                            $disp = (int) ($dispatchedTotals[$did][$sz] ?? 0);
                            $orderedTotal    += $ord;
                            $dispatchedTotal += $disp;
                        }
                        $remainingTotal = max(0, $orderedTotal - $dispatchedTotal);
                    @endphp
                    <tr>
                        <td class="font-medium">{{ $item->design->name ?? '—' }}</td>
                        @foreach($sizes as $sz)
                        @php
                            $ord  = (int) $item->{'qty_' . $sz};
                            $disp = (int) ($dispatchedTotals[$did][$sz] ?? 0);
                            $rem  = max(0, $ord - $disp);
                        @endphp
                        <td class="text-right text-sm {{ $disp > 0 ? 'text-green-600 font-medium' : 'text-[#C7C7CC]' }}">
                            {{ $ord === 0 ? '—' : ($disp ?: '0') }}
                        </td>
                        <td class="text-right text-sm {{ $rem > 0 ? 'text-orange-600 font-medium' : 'text-[#C7C7CC]' }}">
                            {{ $ord === 0 ? '—' : ($rem ?: '✓') }}
                        </td>
                        @endforeach
                        <td class="text-right font-medium text-sm">
                            {{ $dispatchedTotal }}<span class="text-[#86868B] font-normal">/{{ $orderedTotal }}</span>
                        </td>
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

    {{-- Previous batches (collapsed summary) --}}
    @if($order->dispatchBatches->count())
    <div class="card overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-[#F2F2F7]">
            <h2 class="font-semibold text-[#1D1D1F]">Previous Batches
                <span class="text-[#6E6E73] text-sm font-normal ml-2">{{ $order->dispatchBatches->count() }} {{ Str::plural('batch', $order->dispatchBatches->count()) }}</span>
            </h2>
        </div>
        <div class="divide-y divide-[#F2F2F7]">
            @foreach($order->dispatchBatches as $batch)
            <div class="px-5 py-3 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="font-medium text-sm text-[#1D1D1F]">Batch #{{ $batch->batch_number }}</span>
                    <span class="text-[#6E6E73] text-xs">{{ $batch->dispatch_date->format('d M Y') }}</span>
                    <span class="text-[#6E6E73] text-xs">{{ $batch->shipping_address }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-[#1D1D1F]">{{ $batch->totalPieces() }} pcs</span>
                    @if($batch->cargo_document)
                    @php $ext = strtolower(pathinfo($batch->cargo_document, PATHINFO_EXTENSION)); @endphp
                    <a href="{{ Storage::url($batch->cargo_document) }}" target="_blank" title="View Cargo Document">
                        @if(in_array($ext, ['jpg','jpeg','png','webp']))
                            <img src="{{ Storage::url($batch->cargo_document) }}"
                                 alt="Cargo Doc"
                                 class="h-9 w-9 object-cover rounded-lg border border-[#E8E8ED] hover:opacity-80 transition-opacity shadow-sm">
                        @else
                            <div class="h-9 w-9 flex flex-col items-center justify-center rounded-lg border border-[#E8E8ED] bg-red-50 hover:bg-red-100 transition-colors shadow-sm">
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
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── SECTION 2: RECORD NEW BATCH ──────────────────────────────── --}}
    @if($totalRemaining > 0)
    <form method="POST" action="{{ route('dispatch.store', $order) }}"
          enctype="multipart/form-data"
          autocomplete="off"
          x-init="$nextTick(() => $el.querySelectorAll('input[type=number]').forEach(el => { el.value = 0; }))">
        @csrf

        @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- Batch metadata --}}
        <div class="card p-6 space-y-5 mb-5">
            <h2 class="font-semibold text-[#1D1D1F]">New Dispatch Batch</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Dispatch Date</label>
                    <input type="date" name="dispatch_date" value="{{ old('dispatch_date', date('Y-m-d')) }}" class="apple-input" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Shipping Address</label>
                    <input type="text" name="shipping_address" value="{{ old('shipping_address', $order->customer->city ?? '') }}" class="apple-input" placeholder="City / full address" required>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Cargo Document <span class="font-normal normal-case">(optional — PDF or image)</span>
                </label>
                <input type="file" name="cargo_document" accept=".pdf,.jpg,.jpeg,.png" class="apple-input">
            </div>
        </div>

        {{-- Per-design per-size quantities --}}
        <div class="card overflow-hidden mb-5">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces to Dispatch</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Only designs with remaining pieces are shown. "Rem" is what's left to send; "Stock" is what's packed and ready.</p>
            </div>

            @foreach($order->items as $dIndex => $item)
            @php
                $did         = $item->design_id;
                $designTotal = 0;
                foreach ($sizes as $sz) {
                    $designTotal += min(
                        $remaining[$did][$sz] ?? 0,
                        $inStock[$did][$sz]   ?? 0
                    );
                }
            @endphp
            @if($designTotal === 0) @continue @endif

            <div class="px-5 py-5 {{ !$loop->last ? 'border-b border-[#F2F2F7]' : '' }}">
                <p class="text-sm font-semibold text-[#1D1D1F] mb-4">{{ $item->design->name ?? '—' }}</p>
                <input type="hidden" name="designs[{{ $dIndex }}][design_id]" value="{{ $item->design_id }}">

                <div class="grid grid-cols-5 gap-3 mb-1">
                    @foreach($sizes as $sIndex => $size)
                    @php
                        $rem          = $remaining[$did][$size] ?? 0;
                        $stock        = $inStock[$did][$size]   ?? 0;
                        $effectiveMax = min($rem, $stock);
                        $alphaKey     = $did . '_' . $size;
                    @endphp
                    <div>
                        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1 text-center">
                            {{ strtoupper($size) }}
                        </label>
                        <input type="hidden" name="designs[{{ $dIndex }}][items][{{ $sIndex }}][size]" value="{{ $size }}">
                        @if($effectiveMax === 0)
                        <input type="number"
                               name="designs[{{ $dIndex }}][items][{{ $sIndex }}][qty]"
                               value="0"
                               class="apple-input text-center opacity-40 cursor-not-allowed"
                               disabled readonly>
                        @else
                        <input type="number"
                               name="designs[{{ $dIndex }}][items][{{ $sIndex }}][qty]"
                               min="0"
                               max="{{ $effectiveMax }}"
                               value="0"
                               autocomplete="off"
                               @input="vals['{{ $alphaKey }}'] = parseFloat($event.target.value) || 0"
                               :class="isOver('{{ $alphaKey }}')
                                   ? 'apple-input text-center ring-2 ring-red-400 bg-red-50 text-red-600'
                                   : 'apple-input text-center'">
                        @endif
                        <p class="text-xs text-center mt-1 {{ $effectiveMax === 0 ? 'text-[#C7C7CC]' : '' }}"
                           :class="isOver('{{ $alphaKey }}') ? 'text-red-500 font-semibold' : ''">
                            <span class="block">Rem: {{ $rem }}</span>
                            <span class="block {{ $stock === 0 ? 'text-red-400' : 'text-[#34C759]' }}">Stock: {{ $stock }}</span>
                        </p>
                    </div>
                    @endforeach
                </div>

                <div class="text-right mt-2">
                    <span class="text-xs text-[#6E6E73]">This batch:</span>
                    <span class="text-sm font-semibold text-[#0071E3] ml-1" x-text="rowTotal({{ $item->design_id }})"></span>
                    <span class="text-xs text-[#86868B]"> pcs</span>
                </div>
            </div>
            @endforeach
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="btn-primary"
                    :disabled="!hasAnyQty || hasOverflow"
                    :class="(!hasAnyQty || hasOverflow) ? 'opacity-50 cursor-not-allowed' : ''">
                Confirm Dispatch
            </button>
            <a href="{{ route('dispatch.show', $order) }}" class="btn-secondary">Cancel</a>
            <p x-show="hasOverflow" class="text-sm text-red-500">
                Some quantities exceed the remaining maximum.
            </p>
        </div>
    </form>
    @else
    <div class="card p-8 text-center">
        <svg class="w-10 h-10 mx-auto mb-3 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-[#1D1D1F] font-medium">All pieces have been dispatched</p>
        <p class="text-[#6E6E73] text-sm mt-1">There is nothing left to send for this order.</p>
        <a href="{{ route('dispatch.index') }}" class="btn-primary mt-5 inline-flex">Back to Dispatch List</a>
    </div>
    @endif

</div>

@endsection
