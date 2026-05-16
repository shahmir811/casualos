@extends('layouts.app')
@section('title', 'Record Dispatch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('dispatch.index') }}" class="text-[#0066CC] hover:underline text-sm">Dispatch</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Order #{{ $order->order_number }}</span>
</div>

@php
    // Build Alpine vals and maxes keyed by "designId_size"
    // Effective max = min(remaining from order, available in packed inventory)
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
@endphp

<div class="max-w-3xl"
     x-data="{
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

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Record Dispatch</h1>
    <p class="text-[#6E6E73] text-sm mb-6">
        Order #{{ $order->order_number }} &middot; {{ $order->customer->name }}
        &middot; PKR {{ number_format($order->total_amount, 0) }}
    </p>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('dispatch.store', $order) }}"
          enctype="multipart/form-data"
          autocomplete="off"
          x-init="$nextTick(() => $el.querySelectorAll('input[type=number]').forEach(el => { el.value = 0; }))">
        @csrf

        {{-- Dispatch metadata --}}
        <div class="card p-6 space-y-5 mb-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Dispatch Date</label>
                <input type="date" name="dispatch_date" value="{{ old('dispatch_date', date('Y-m-d')) }}" class="apple-input max-w-xs" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Shipping Address</label>
                <input type="text" name="shipping_address" value="{{ old('shipping_address', $order->customer->city ?? '') }}" class="apple-input" placeholder="City / full address" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Cargo Document <span class="font-normal normal-case">(optional — PDF or image)</span>
                </label>
                <input type="file" name="cargo_document" accept=".pdf,.jpg,.jpeg,.png" class="apple-input">
            </div>
        </div>

        {{-- Per-design per-size dispatch quantities --}}
        <div class="card overflow-hidden mb-5">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces to Dispatch</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Enter quantities for this batch. Hint shows remaining pieces not yet dispatched.</p>
            </div>

            @foreach($order->items as $dIndex => $item)
            @php
                $design = $item->design;
            @endphp
            <div class="px-5 py-5 {{ !$loop->last ? 'border-b border-[#F2F2F7]' : '' }}">
                <p class="text-sm font-semibold text-[#1D1D1F] mb-4">{{ $design->name ?? '—' }}</p>
                <input type="hidden" name="designs[{{ $dIndex }}][design_id]" value="{{ $item->design_id }}">

                <div class="grid grid-cols-5 gap-3 mb-1">
                    @foreach($sizes as $sIndex => $size)
                    @php
                        $rem          = $remaining[$item->design_id][$size] ?? 0;
                        $stock        = $inStock[$item->design_id][$size]   ?? 0;
                        $effectiveMax = min($rem, $stock);
                        $alphaKey     = $item->design_id . '_' . $size;
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
                            <span class="block">Ordered: {{ $rem }}</span>
                            <span class="block {{ $stock === 0 ? 'text-red-400' : 'text-[#34C759]' }}">Stock: {{ $stock }}</span>
                        </p>
                    </div>
                    @endforeach
                </div>

                {{-- Row total --}}
                <div class="text-right mt-2">
                    <span class="text-xs text-[#6E6E73]">Batch total for this design:</span>
                    <span class="text-sm font-semibold text-[#0071E3] ml-1"
                          x-text="rowTotal({{ $item->design_id }})"></span>
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
            <a href="{{ route('dispatch.index') }}" class="btn-secondary">Cancel</a>
            <p x-show="hasOverflow" class="text-sm text-red-500">
                Some quantities exceed the remaining maximum.
            </p>
        </div>
    </form>
</div>

@endsection
