@extends('layouts.app')
@section('title', 'Move Pieces')
@section('content')

@php
    $sizes        = ['xs', 's', 'm', 'l', 'xl'];
    $totalPaid    = (float) $order->total_paid;
    $currentTotal = (float) $order->total_amount;

    $alpineVals   = [];
    $alpineMaxes  = [];
    $alpinePrices = [];
    $designNames  = [];
    foreach ($order->items as $item) {
        $did = (string) $item->design_id;
        $alpinePrices[$did] = (float) $item->unit_price;
        $designNames[$did]  = $item->design->name ?? "Design #{$item->design_id}";
        foreach ($sizes as $size) {
            $key = $did . '_' . $size;
            $alpineVals[$key]  = 0;
            $alpineMaxes[$key] = (int) ($item->{'qty_' . $size} ?? 0);
        }
    }

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
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Move Pieces</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Move Pieces</h1>
<p class="text-[#6E6E73] text-sm mb-6">
    Order #{{ $order->order_number }} · {{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }}
</p>

<div class="max-w-4xl"
     x-data="{
        vals:   {{ Js::from($alpineVals) }},
        maxes:  {{ Js::from($alpineMaxes) }},
        prices: {{ Js::from($alpinePrices) }},
        surplusAction: 'credit_to_advance',
        refundMethod: 'cash',
        totalPaid: {{ $totalPaid }},
        currentTotal: {{ $currentTotal }},
        targets: {{ Js::from($targetsJs) }},
        targetType: '{{ old('target_type', '') }}',
        targetId: '{{ old('target_id', '') }}',
        search: '',
        open: false,

        isOver(key) {
            return parseInt(this.vals[key] || 0) > (this.maxes[key] ?? 0);
        },
        get hasOverflow() {
            return Object.keys(this.vals).some(k => this.isOver(k));
        },
        get hasAnyQty() {
            return Object.values(this.vals).some(v => parseInt(v) > 0);
        },
        get filteredTargets() {
            const s = this.search.toLowerCase();
            return this.targets.filter(t => t.label.toLowerCase().includes(s));
        },
        selectTarget(t) {
            this.targetType = t.type; this.targetId = t.id; this.search = t.label; this.open = false;
        },
        get canSubmit() {
            return this.hasAnyQty && !this.hasOverflow && this.targetType && this.targetId;
        },
        designTotal(designId) {
            return ['xs','s','m','l','xl'].reduce((sum, s) => {
                return sum + (parseInt(this.vals[designId + '_' + s]) || 0);
            }, 0);
        },
        sizeTotal(size) {
            return Object.entries(this.vals)
                .filter(([k]) => k.endsWith('_' + size))
                .reduce((sum, [, v]) => sum + (parseInt(v) || 0), 0);
        },
        get grandTotal() {
            return Object.values(this.vals).reduce((sum, v) => sum + (parseInt(v) || 0), 0);
        },
        get moveAmount() {
            return Object.entries(this.vals).reduce((sum, [key, qty]) => {
                const designId = key.split('_')[0];
                return sum + (this.prices[designId] || 0) * (parseInt(qty) || 0);
            }, 0);
        },
        get newSourceTotal() { return Math.max(0, this.currentTotal - this.moveAmount); },
        get surplus()    { return Math.max(0, this.totalPaid - this.newSourceTotal); },
        get hasSurplus() { return this.surplus > 0; },
        formatPkr(n) {
            const neg = n < 0;
            const str = String(Math.round(Math.abs(n)));
            let formatted;
            if (str.length <= 3) {
                formatted = str;
            } else {
                const last3 = str.slice(-3);
                let rem = str.slice(0, -3);
                const groups = [];
                while (rem.length > 0) {
                    const take = Math.min(2, rem.length);
                    groups.unshift(rem.slice(-take));
                    rem = rem.slice(0, -take);
                }
                formatted = groups.join(',') + ',' + last3;
            }
            return (neg ? '-' : '') + 'PKR ' + formatted;
        }
     }"
     x-init="targets.forEach(t => { if (t.type === targetType && String(t.id) === String(targetId)) search = t.label; })">

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form id="form-move-pieces" method="POST" action="{{ route('orders.move-pieces.store', $order) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf
        <input type="hidden" name="target_type" :value="targetType">
        <input type="hidden" name="target_id" :value="targetId">

        {{-- Pieces to move — per-design table, capped at current order quantities --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces to Move</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Enter how many pieces of each design and size to move out of this order. Disabled cells have none left.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[580px]">
                    <thead>
                        <tr class="border-b border-[#F2F2F7] bg-[#FAFAFA]">
                            <th class="text-left text-xs font-semibold text-[#6E6E73] uppercase tracking-widest px-5 py-3">Design</th>
                            @foreach(['XS', 'S', 'M', 'L', 'XL'] as $sLabel)
                            <th class="text-center text-xs font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-3 w-[90px]">{{ $sLabel }}</th>
                            @endforeach
                            <th class="text-center text-xs font-semibold text-[#6E6E73] uppercase tracking-widest px-3 py-3 w-16">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F2F2F7]">
                        @php $n = 0; @endphp
                        @foreach($order->items as $item)
                        @php $did = $item->design_id; @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <p class="text-sm font-semibold text-[#1D1D1F]">{{ $item->design->name ?? '—' }}</p>
                            </td>
                            @foreach($sizes as $size)
                            @php
                                $key    = $did . '_' . $size;
                                $maxQty = $alpineMaxes[$key] ?? 0;
                            @endphp
                            <td class="px-2 py-4 text-center">
                                @if($maxQty === 0)
                                    <input type="number" value="0" disabled
                                           class="w-full apple-input text-center opacity-40 cursor-not-allowed bg-[#F5F5F7]">
                                    <p class="text-[11px] text-center text-[#C7C7CC] mt-1">Max: 0</p>
                                @else
                                    <input type="hidden" name="items[{{ $n }}][design_id]" value="{{ $did }}">
                                    <input type="hidden" name="items[{{ $n }}][size]" value="{{ $size }}">
                                    <input type="number"
                                           name="items[{{ $n }}][qty]"
                                           min="0"
                                           max="{{ $maxQty }}"
                                           value="0"
                                           autocomplete="off"
                                           :class="isOver('{{ $key }}')
                                               ? 'apple-input text-center w-full ring-2 ring-[#FF3B30] bg-[#FFF0EF] text-[#FF3B30]'
                                               : 'apple-input text-center w-full'"
                                           @input="vals['{{ $key }}'] = parseInt($event.target.value) || 0">
                                    <p class="text-[11px] text-center mt-1"
                                       :class="isOver('{{ $key }}') ? 'text-[#FF3B30] font-semibold' : 'text-[#86868B]'">
                                        Max: {{ $maxQty }}
                                    </p>
                                    @php $n++; @endphp
                                @endif
                            </td>
                            @endforeach
                            <td class="px-3 py-4 text-center">
                                <span class="text-sm font-semibold text-[#0071E3] tabular-nums"
                                      x-text="designTotal({{ $did }})"></span>
                                <p class="text-[11px] text-[#86868B] mt-1">pcs</p>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                            <td class="px-5 py-3 text-xs font-semibold text-[#1D1D1F]">Total Moving</td>
                            @foreach($sizes as $sz)
                            <td class="px-2 py-3 text-center">
                                <span class="text-sm font-semibold text-[#0071E3] tabular-nums"
                                      x-text="sizeTotal('{{ $sz }}') > 0 ? sizeTotal('{{ $sz }}') : '—'"></span>
                            </td>
                            @endforeach
                            <td class="px-3 py-3 text-center">
                                <span class="text-sm font-semibold text-[#0071E3] tabular-nums"
                                      x-text="grandTotal > 0 ? grandTotal : '—'"></span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div x-show="hasOverflow" x-cloak class="px-5 py-3 bg-[#FFF0EF] border-t border-[#FFCDD0]">
                <p class="text-sm text-[#FF3B30]">One or more quantities exceed what's available. Please correct the highlighted cells.</p>
            </div>
        </div>

        {{-- Target picker — existing order on this catalogue, or a new customer --}}
        <div class="card p-6 mb-5">
            <h2 class="text-[#1D1D1F] text-sm font-semibold mb-1">Move To</h2>
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

        {{-- Financial summary for the source order --}}
        <div class="card p-5 space-y-2 text-sm">
            <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Impact on This Order</h3>
            <div class="flex justify-between">
                <span class="text-[#6E6E73]">Current order total</span>
                <span class="font-medium text-[#1D1D1F]" x-text="formatPkr(currentTotal)"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#6E6E73]">Amount moving out</span>
                <span class="font-medium text-[#FF3B30]"
                      x-text="moveAmount > 0 ? '− ' + formatPkr(moveAmount) : '—'"></span>
            </div>
            <div class="flex justify-between border-t border-[#F2F2F7] pt-2 mt-1">
                <span class="font-semibold text-[#1D1D1F]">New total</span>
                <span class="font-semibold text-[#1D1D1F]" x-text="formatPkr(newSourceTotal)"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#6E6E73]">Amount already paid</span>
                <span class="font-medium text-[#30D158]" x-text="formatPkr(totalPaid)"></span>
            </div>
            <div x-show="hasSurplus" class="flex justify-between border-t border-[#F2F2F7] pt-2 mt-1">
                <span class="font-semibold text-[#FF9500]">Surplus to handle</span>
                <span class="font-semibold text-[#FF9500]" x-text="formatPkr(surplus)"></span>
            </div>
            <p class="text-xs text-[#86868B] pt-2">
                The target order's price and total will be computed on save, using current design pricing.
            </p>
        </div>

        {{-- Surplus action — only shown when the source has a real surplus --}}
        <div x-show="hasSurplus" x-cloak class="card p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-[#1D1D1F] mb-0.5">Handle Surplus</h3>
                <p class="text-xs text-[#6E6E73]">This customer has paid more than the order's new total after the move. Choose what to do with the surplus.</p>
            </div>

            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="surplus_action" value="credit_to_advance"
                           x-model="surplusAction" class="mt-0.5">
                    <div>
                        <p class="text-sm font-medium text-[#1D1D1F]">Credit to Advance Balance</p>
                        <p class="text-xs text-[#6E6E73]">Add the surplus to the customer's advance credit for future orders.</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="surplus_action" value="refund"
                           x-model="surplusAction" class="mt-0.5">
                    <div>
                        <p class="text-sm font-medium text-[#1D1D1F]">Refund to Customer</p>
                        <p class="text-xs text-[#6E6E73]">Return the surplus amount to the customer via cash or bank transfer.</p>
                    </div>
                </label>
            </div>

            {{-- Refund method — only when refund is chosen --}}
            <div x-show="surplusAction === 'refund'" x-cloak class="space-y-4 pt-2 border-t border-[#F2F2F7]"
                 x-data="{
                    fileName: '', fileType: '', filePreview: '', lightboxOpen: false, isDragging: false,
                    processFile(file) {
                        if (!file) return;
                        this.fileName = file.name;
                        const ext = file.name.split('.').pop().toLowerCase();
                        this.fileType = (ext === 'pdf') ? 'pdf' : 'image';
                        this.filePreview = this.fileType === 'image' ? URL.createObjectURL(file) : '';
                    },
                    handleDrop(e) {
                        this.isDragging = false;
                        const f = e.dataTransfer.files[0];
                        if (f) { this.$refs.refundDocInput.files = e.dataTransfer.files; this.processFile(f); }
                    },
                    handleChange(e) { this.processFile(e.target.files[0]); },
                    clearFile() { this.fileName = ''; this.fileType = ''; this.filePreview = ''; this.$refs.refundDocInput.value = ''; }
                 }">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Refund Method <span class="text-[#FF3B30]">*</span></label>
                    <select name="refund_method" x-model="refundMethod" class="apple-input" :required="surplusAction === 'refund'">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div x-show="refundMethod === 'bank_transfer'" x-cloak class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Bank / Reference</label>
                        <input type="text" name="refund_reference" class="apple-input"
                               placeholder="e.g. HBL — sent to customer account 0312-XXXXXXX"
                               value="{{ old('refund_reference') }}">
                        <p class="text-[11px] text-[#86868B] mt-1">Customer's bank name, account number, or transfer reference ID.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Transfer Proof <span class="font-normal normal-case text-[#86868B]">(image or PDF)</span></label>

                        <input type="file" name="refund_document" accept=".pdf,.jpg,.jpeg,.png"
                               class="hidden" x-ref="refundDocInput" @change="handleChange($event)">

                        <template x-if="!fileName">
                            <div class="border-2 border-dashed rounded-xl transition-colors cursor-pointer px-5 py-8 text-center"
                                 :class="isDragging ? 'border-[#0071E3] bg-[#F0F7FF]' : 'border-[#D1D1D6] bg-[#FAFAFA] hover:border-[#0071E3]'"
                                 @dragover.prevent="isDragging = true"
                                 @dragleave.prevent="isDragging = false"
                                 @drop.prevent="handleDrop($event)"
                                 @click="$refs.refundDocInput.click()">
                                <svg class="w-8 h-8 mx-auto text-[#86868B] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-[#1D1D1F] font-medium">Click to upload or drag &amp; drop</p>
                                <p class="text-xs text-[#86868B] mt-1">JPG, PNG, or PDF · max 10 MB</p>
                            </div>
                        </template>

                        <template x-if="fileName">
                            <div class="flex items-center gap-4 p-3 border border-[#E8E8ED] rounded-xl bg-[#FAFAFA]">
                                <div class="relative shrink-0 w-20 h-20">
                                    <template x-if="fileType === 'image'">
                                        <img :src="filePreview"
                                             class="w-20 h-20 object-cover rounded-lg border border-[#E8E8ED] cursor-pointer hover:opacity-80 transition-opacity"
                                             @click="lightboxOpen = true" alt="Preview">
                                    </template>
                                    <template x-if="fileType === 'pdf'">
                                        <div class="w-20 h-20 rounded-lg border border-[#FFCDD0] bg-[#FFF0EF] flex flex-col items-center justify-center gap-1">
                                            <svg class="w-8 h-8 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-[10px] font-bold text-[#FF3B30] tracking-wide">PDF</span>
                                        </div>
                                    </template>
                                    <button type="button" @click.stop="clearFile()"
                                            class="absolute -top-2 -right-2 w-5 h-5 bg-[#FF3B30] text-white rounded-full flex items-center justify-center hover:bg-red-700 transition-colors shadow">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    </button>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-[#1D1D1F] font-medium truncate" x-text="fileName"></p>
                                    <p x-show="fileType === 'image'" class="text-xs text-[#0066CC] mt-1 cursor-pointer hover:underline" @click="lightboxOpen = true">Click thumbnail to preview</p>
                                    <p x-show="fileType === 'pdf'" class="text-xs text-[#86868B] mt-1">No preview available</p>
                                    <button type="button" @click="$refs.refundDocInput.click()" class="text-xs text-[#0066CC] hover:underline mt-1 block">Change file</button>
                                </div>
                            </div>
                        </template>

                        <div x-show="lightboxOpen" x-cloak
                             class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
                             @click.self="lightboxOpen = false"
                             @keydown.escape.window="lightboxOpen = false">
                            <div class="relative max-w-3xl max-h-[90vh] mx-4">
                                <img :src="filePreview" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl" alt="Preview">
                                <button type="button" @click="lightboxOpen = false"
                                        class="absolute -top-3 -right-3 w-8 h-8 bg-white text-[#1D1D1F] rounded-full flex items-center justify-center shadow-lg hover:bg-[#F5F5F7] transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Hidden surplus_action — 'none' when no surplus, otherwise the radio value --}}
        <input type="hidden" name="surplus_action" :value="hasSurplus ? surplusAction : 'none'">

        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Notes <span class="font-normal normal-case">(optional)</span></label>
            <textarea name="notes" rows="2" class="apple-input" placeholder="Reason for the move...">{{ old('notes') }}</textarea>
        </div>

        <div class="p-4 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-sm text-[#FF3B30]">
            <strong>Warning:</strong> This will move pieces out of Order #{{ $order->order_number }} into the selected target, creating ledger entries on both sides.
            If the new total reaches PKR 0, this order will be marked as <strong>Cancelled</strong>. This cannot be undone.
        </div>

        <div class="flex gap-3 items-center">
            <button type="button" class="btn-primary"
                    :style="canSubmit ? '' : 'opacity:0.5;cursor:not-allowed;'"
                    :disabled="!canSubmit"
                    @click="canSubmit && $store.confirm.show({
                        title: 'Move Pieces',
                        message: 'This will move the selected pieces out of Order #{{ $order->order_number }} into the chosen target. This cannot be undone.',
                        formId: 'form-move-pieces',
                        confirmText: 'Move Pieces',
                        danger: true
                    })">Move Pieces</button>
            <a href="{{ route('orders.show', $order) }}" class="btn-secondary">Cancel</a>
            <p x-show="!hasAnyQty" class="text-sm text-[#86868B]">Enter at least one quantity to move.</p>
            <p x-show="hasAnyQty && !hasOverflow && !(targetType && targetId)" class="text-sm text-[#86868B]">Choose a target above.</p>
            <p x-show="hasOverflow" x-cloak class="text-sm text-[#FF3B30]">Fix highlighted cells first.</p>
        </div>
    </form>
</div>

@endsection
