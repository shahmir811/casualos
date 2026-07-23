@extends('layouts.app')
@section('title', 'Assign Free Pieces')
@section('content')

@php
    $sizeLabels = ['xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'];

    $targetsJs = collect();
    foreach ($existingTargets as $t) {
        $targetsJs->push([
            'type'  => 'existing_order',
            'id'    => $t->id,
            'label' => ($t->customer->name ?? '—') . ' — Order #' . $t->order_number
                . ' (' . ucfirst(str_replace('_', ' ', $t->status)) . ')',
        ]);
    }
    foreach ($newCustomerTargets as $c) {
        $targetsJs->push([
            'type'  => 'new_customer',
            'id'    => $c->id,
            'label' => ($c->name ?? '—') . ' — New Order',
        ]);
    }
@endphp

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('free-pieces.index') }}" class="text-[#0066CC] hover:underline text-sm">Free Pieces</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Assign</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Assign Free Pieces</h1>
<p class="text-[#6E6E73] text-sm mb-6">{{ $catalogue->name }}</p>

@if($errors->any())
<div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

@if($sizeTotals->sum() === 0)
<div class="card p-8 text-center text-[#86868B]">No free pieces available to assign for this catalogue.</div>
@else

<div class="max-w-2xl"
     x-data="{
        xs: {{ (int) old('qty_xs', 0) }},
        s:  {{ (int) old('qty_s',  0) }},
        m:  {{ (int) old('qty_m',  0) }},
        l:  {{ (int) old('qty_l',  0) }},
        xl: {{ (int) old('qty_xl', 0) }},
        available: {{ Js::from($sizeTotals) }},
        targets: {{ Js::from($targetsJs) }},
        targetType: '{{ old('target_type', '') }}',
        targetId: '{{ old('target_id', '') }}',
        search: '',
        open: false,

        get piecesPerDesign() {
            return (parseInt(this.xs)||0) + (parseInt(this.s)||0) + (parseInt(this.m)||0) + (parseInt(this.l)||0) + (parseInt(this.xl)||0);
        },
        get filteredTargets() {
            const s = this.search.toLowerCase();
            return this.targets.filter(t => t.label.toLowerCase().includes(s));
        },
        selectTarget(t) {
            this.targetType = t.type; this.targetId = t.id; this.search = t.label; this.open = false;
        },
        isOverLimit(key) {
            return (parseInt(this[key])||0) > (this.available[key]||0);
        },
        get hasOverLimit() {
            return Object.keys(this.available).some(key => this.isOverLimit(key));
        },
        get canSubmit() {
            return this.piecesPerDesign > 0 && this.targetType && this.targetId && !this.hasOverLimit;
        }
     }"
     x-init="targets.forEach(t => { if (t.type === targetType && String(t.id) === String(targetId)) search = t.label; })">

    <form id="form-assign-free-pieces" method="POST" action="{{ route('free-pieces.store') }}">
        @csrf
        <input type="hidden" name="target_type" :value="targetType">
        <input type="hidden" name="target_id" :value="targetId">

        <div class="card p-6 mb-5">
            <h2 class="text-[#1D1D1F] text-sm font-semibold mb-1">Quantity Per Size</h2>
            <p class="text-[#6E6E73] text-xs mb-5">
                Each piece is drawn from every design with free stock in that size — e.g. 1 Small means 1 Small from each design, not 1 total.
            </p>

            <div class="grid grid-cols-5 gap-3">
                @foreach($sizeLabels as $key => $label)
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2 text-center">
                        {{ $label }}
                    </label>
                    <input type="number"
                           name="qty_{{ $key }}"
                           min="0"
                           :max="available.{{ $key }}"
                           x-model="{{ $key }}"
                           :disabled="available.{{ $key }} === 0"
                           :class="isOverLimit('{{ $key }}') ? 'ring-2 ring-[#FF3B30] text-[#FF3B30] bg-[#FFF0EF]' : (available.{{ $key }} === 0 ? 'opacity-50 cursor-not-allowed bg-[#F5F5F7]' : '')"
                           class="apple-input text-center text-lg font-semibold"
                           placeholder="0">
                    <p class="text-center text-[11px] mt-1" :class="isOverLimit('{{ $key }}') ? 'text-[#FF3B30] font-medium' : 'text-[#86868B]'">Available {{ $sizeTotals[$key] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        <div class="card p-6 mb-5">
            <h2 class="text-[#1D1D1F] text-sm font-semibold mb-1">Assign To</h2>
            <p class="text-[#6E6E73] text-xs mb-3">
                An existing order on this catalogue, or a customer who hasn't ordered on it yet (a brand-new order is created for them).
            </p>
            <div class="relative" @click.outside="open = false">
                <input type="text"
                       x-model="search"
                       @focus="open = true"
                       @input="open = true; targetType = ''; targetId = ''"
                       autocomplete="off"
                       placeholder="Type customer name or order #..."
                       class="apple-input w-full"
                       :class="!targetId ? 'ring-1 ring-[#FFCDD0]' : ''">
                <div x-show="open" x-cloak
                     class="absolute z-20 mt-1 w-full bg-white border border-[#E5E5E7] rounded-lg shadow-lg max-h-56 overflow-y-auto">
                    <template x-for="t in filteredTargets" :key="t.type + ':' + t.id">
                        <div @click="selectTarget(t)"
                             class="px-4 py-2 text-sm text-[#1D1D1F] cursor-pointer hover:bg-[#F5F5F7]"
                             x-text="t.label"></div>
                    </template>
                    <p x-show="filteredTargets.length === 0" class="px-4 py-2 text-sm text-[#6E6E73]">No matches</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary" :disabled="!canSubmit" :style="canSubmit ? '' : 'opacity:0.5;cursor:not-allowed;'">Assign Free Pieces</button>
            <a href="{{ route('free-pieces.index') }}" class="btn-secondary">Cancel</a>
            <p class="text-sm text-[#FF3B30]" x-show="hasOverLimit" x-cloak>Fix the highlighted size — quantity exceeds what's available.</p>
            <p class="text-sm text-[#86868B]" x-show="!hasOverLimit && piecesPerDesign > 0" x-text="piecesPerDesign + ' piece(s) per design selected'"></p>
        </div>
    </form>
</div>

@endif

@endsection
