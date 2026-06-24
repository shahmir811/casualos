@extends('layouts.app')
@section('title', 'Order Reduction')
@section('content')

@php
    $totalPaid    = (float) $order->total_paid;
    $currentTotal = (float) $order->total_amount;
    $rNumDesigns  = $order->catalogue?->number_of_designs ?? $order->items->count();

    // Tally all prior reductions, tracking per-design per-size
    $perDesignReduced    = [];
    $totalAlreadyReduced = 0;
    foreach ($order->reductions as $red) {
        foreach ($red->items as $ri) {
            $did = $ri->design_id;
            if (!isset($perDesignReduced[$did])) {
                $perDesignReduced[$did] = ['xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0];
            }
            if (isset($perDesignReduced[$did][$ri->size])) {
                $perDesignReduced[$did][$ri->size] += $ri->qty_reduced;
            }
            $totalAlreadyReduced += $ri->qty_reduced;
        }
    }

    $sizes = ['xs', 's', 'm', 'l', 'xl'];

    // Per-design per-size max reducible and Alpine data
    $alpineVals   = [];
    $alpineMaxes  = [];
    $alpinePrices = [];
    foreach ($order->items as $item) {
        $did = (string) $item->design_id;
        $alpinePrices[$did] = (float) $item->unit_price;
        foreach ($sizes as $size) {
            $key     = $did . '_' . $size;
            $ordered = (int) ($item->{'qty_' . $size} ?? 0);
            $reduced = (int) ($perDesignReduced[$item->design_id][$size] ?? 0);
            $alpineVals[$key]  = 0;
            $alpineMaxes[$key] = max(0, $ordered - $reduced);
        }
    }

    // Order summary display stats
    $firstItem = $order->items->first();
    $rUniformForSize = function(string $sz) use ($rNumDesigns, $perDesignReduced): int {
        if ($rNumDesigns === 0 || empty($perDesignReduced)) return 0;
        $amounts = array_column($perDesignReduced, $sz);
        if (count($perDesignReduced) < $rNumDesigns) $amounts[] = 0;
        $unique = array_unique($amounts);
        return count($unique) === 1 ? (int) $unique[0] : 0;
    };
    $rawXs = $firstItem?->qty_xs ?? 0;
    $rawS  = $firstItem?->qty_s  ?? 0;
    $rawM  = $firstItem?->qty_m  ?? 0;
    $rawL  = $firstItem?->qty_l  ?? 0;
    $rawXl = $firstItem?->qty_xl ?? 0;
    $rawTotalPieces = ($rawXs + $rawS + $rawM + $rawL + $rawXl) * $rNumDesigns;
    $rqxs = max(0, $rawXs - $rUniformForSize('xs'));
    $rqs  = max(0, $rawS  - $rUniformForSize('s'));
    $rqm  = max(0, $rawM  - $rUniformForSize('m'));
    $rql  = max(0, $rawL  - $rUniformForSize('l'));
    $rqxl = max(0, $rawXl - $rUniformForSize('xl'));
    $rQtyPerDesign = $rqxs + $rqs + $rqm + $rql + $rqxl;
    $rTotalPieces  = max(0, $rawTotalPieces - $totalAlreadyReduced);
@endphp

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Reduction</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Order Reduction</h1>
<p class="text-[#6E6E73] text-sm mb-6">
    Order #{{ $order->order_number }} · {{ $order->customer->name ?? '—' }} · PKR {{ number_format($order->total_amount, 0) }}
</p>

{{-- Order Summary — full width --}}
<div class="card mb-6 overflow-hidden">
    <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Order Summary</h2>
        <span class="text-xs text-[#6E6E73]">{{ $rNumDesigns }} designs · {{ number_format($rTotalPieces) }} pieces total</span>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Order #</th>
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
                    <p class="text-xs text-[#86868B] mt-0.5">{{ $rNumDesigns }} designs</p>
                </td>
                <td class="text-[#6E6E73] text-xs tabular-nums font-mono">{{ $order->order_number }}</td>
                <td class="text-center tabular-nums px-3 {{ $rqxs ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $rqxs ?: '—' }}</td>
                <td class="text-center tabular-nums px-3 {{ $rqs  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $rqs  ?: '—' }}</td>
                <td class="text-center tabular-nums px-3 {{ $rqm  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $rqm  ?: '—' }}</td>
                <td class="text-center tabular-nums px-3 {{ $rql  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $rql  ?: '—' }}</td>
                <td class="text-center tabular-nums px-3 {{ $rqxl ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $rqxl ?: '—' }}</td>
                <td class="text-center font-semibold text-[#1D1D1F] tabular-nums">{{ number_format($rQtyPerDesign) }}</td>
                <td class="text-center font-semibold text-[#0071E3] tabular-nums">{{ number_format($rTotalPieces) }}</td>
                <td class="text-right font-semibold text-[#1D1D1F] tabular-nums">PKR {{ number_format($order->total_amount, 0) }}</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="max-w-4xl"
     x-data="{
        vals:   {{ Js::from($alpineVals) }},
        maxes:  {{ Js::from($alpineMaxes) }},
        prices: {{ Js::from($alpinePrices) }},
        surplusAction: 'credit_to_advance',
        refundMethod: 'cash',
        totalPaid: {{ $totalPaid }},
        currentTotal: {{ $currentTotal }},

        isOver(key) {
            return parseInt(this.vals[key] || 0) > (this.maxes[key] ?? 0);
        },
        get hasOverflow() {
            return Object.keys(this.vals).some(k => this.isOver(k));
        },
        get hasAnyQty() {
            return Object.values(this.vals).some(v => parseInt(v) > 0);
        },
        get canSubmit() {
            return this.hasAnyQty && !this.hasOverflow;
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
        get reductionAmount() {
            return Object.entries(this.vals).reduce((sum, [key, qty]) => {
                const designId = key.split('_')[0];
                return sum + (this.prices[designId] || 0) * (parseInt(qty) || 0);
            }, 0);
        },
        get newTotal()   { return Math.max(0, this.currentTotal - this.reductionAmount); },
        get surplus()    { return Math.max(0, this.totalPaid - this.newTotal); },
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
     }">

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form id="form-apply-reduction" method="POST" action="{{ route('orders.reduce.store', $order) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        {{-- Adjustment type + notes --}}
        <div class="card p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Adjustment Type <span class="text-[#FF3B30]">*</span></label>
                <select name="adjustment_type" class="apple-input" required>
                    <option value="">— Select type —</option>
                    <option value="damage"           {{ old('adjustment_type') === 'damage'           ? 'selected' : '' }}>Damage</option>
                    <option value="short_supply"     {{ old('adjustment_type') === 'short_supply'     ? 'selected' : '' }}>Short Supply</option>
                    <option value="price_correction" {{ old('adjustment_type') === 'price_correction' ? 'selected' : '' }}>Price Correction</option>
                    <option value="other"            {{ old('adjustment_type') === 'other'            ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Notes <span class="font-normal normal-case">(optional)</span></label>
                <textarea name="notes" rows="2" class="apple-input" placeholder="Reason for reduction...">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Items to Reduce — per-design table --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Items to Reduce</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Enter quantities to deduct per design and size. Disabled cells have no remaining pieces.</p>
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
                                $key          = $did . '_' . $size;
                                $maxReducible = $alpineMaxes[$key] ?? 0;
                            @endphp
                            <td class="px-2 py-4 text-center">
                                @if($maxReducible === 0)
                                    <input type="number" value="0" disabled
                                           class="w-full apple-input text-center opacity-40 cursor-not-allowed bg-[#F5F5F7]">
                                    <p class="text-[11px] text-center text-[#C7C7CC] mt-1">Max: 0</p>
                                @else
                                    <input type="hidden" name="items[{{ $n }}][design_id]" value="{{ $did }}">
                                    <input type="hidden" name="items[{{ $n }}][size]" value="{{ $size }}">
                                    <input type="number"
                                           name="items[{{ $n }}][qty]"
                                           min="0"
                                           max="{{ $maxReducible }}"
                                           value="0"
                                           autocomplete="off"
                                           :class="isOver('{{ $key }}')
                                               ? 'apple-input text-center w-full ring-2 ring-[#FF3B30] bg-[#FFF0EF] text-[#FF3B30]'
                                               : 'apple-input text-center w-full'"
                                           @input="vals['{{ $key }}'] = parseInt($event.target.value) || 0">
                                    <p class="text-[11px] text-center mt-1"
                                       :class="isOver('{{ $key }}') ? 'text-[#FF3B30] font-semibold' : 'text-[#86868B]'">
                                        Max: {{ $maxReducible }}
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
                            <td class="px-5 py-3 text-xs font-semibold text-[#1D1D1F]">Total Reduced</td>
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

            {{-- Overflow warning --}}
            <div x-show="hasOverflow" x-cloak class="px-5 py-3 bg-[#FFF0EF] border-t border-[#FFCDD0]">
                <p class="text-sm text-[#FF3B30]">One or more quantities exceed the maximum. Please correct the highlighted cells.</p>
            </div>
        </div>

        {{-- Financial summary — always visible --}}
        <div class="card p-5 space-y-2 text-sm">
            <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Financial Summary</h3>
            <div class="flex justify-between">
                <span class="text-[#6E6E73]">Current order total</span>
                <span class="font-medium text-[#1D1D1F]" x-text="formatPkr(currentTotal)"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#6E6E73]">Reduction amount</span>
                <span class="font-medium text-[#FF3B30]"
                      x-text="reductionAmount > 0 ? '− ' + formatPkr(reductionAmount) : '—'"></span>
            </div>
            <div class="flex justify-between border-t border-[#F2F2F7] pt-2 mt-1">
                <span class="font-semibold text-[#1D1D1F]">New total</span>
                <span class="font-semibold text-[#1D1D1F]" x-text="formatPkr(newTotal)"></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#6E6E73]">Amount already paid</span>
                <span class="font-medium text-[#30D158]" x-text="formatPkr(totalPaid)"></span>
            </div>
            <div x-show="hasSurplus" class="flex justify-between border-t border-[#F2F2F7] pt-2 mt-1">
                <span class="font-semibold text-[#FF9500]">Surplus to handle</span>
                <span class="font-semibold text-[#FF9500]" x-text="formatPkr(surplus)"></span>
            </div>
        </div>

        {{-- Surplus action — only shown when there is a real surplus --}}
        <div x-show="hasSurplus" x-cloak class="card p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-[#1D1D1F] mb-0.5">Handle Surplus</h3>
                <p class="text-xs text-[#6E6E73]">The customer has paid more than the new order total. Choose what to do with the surplus.</p>
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

        {{-- Warning --}}
        <div class="p-4 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-sm text-[#FF3B30]">
            <strong>Warning:</strong> This will permanently reduce Order #{{ $order->order_number }}'s value and create a ledger entry.
            If the new total reaches PKR 0, the order will be marked as <strong>Cancelled</strong>. This cannot be undone.
        </div>

        <div class="flex gap-3 items-center">
            <button type="button" class="btn-primary"
                    :style="canSubmit ? 'background:#FF3B30;' : 'background:#FF9896;cursor:not-allowed;'"
                    :disabled="!canSubmit"
                    @click="canSubmit && $store.confirm.show({
                        title: 'Apply Reduction',
                        message: 'This will permanently reduce Order #{{ $order->order_number }}\'s value and create a ledger entry. This cannot be undone.',
                        formId: 'form-apply-reduction',
                        confirmText: 'Apply Reduction',
                        danger: true
                    })">Apply Reduction</button>
            <a href="{{ route('orders.show', $order) }}" class="btn-secondary">Cancel</a>
            <p x-show="!hasAnyQty" class="text-sm text-[#86868B]">Enter at least one quantity to reduce.</p>
            <p x-show="hasOverflow" x-cloak class="text-sm text-[#FF3B30]">Fix highlighted cells first.</p>
        </div>
    </form>
</div>

@endsection
