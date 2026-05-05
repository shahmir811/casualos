@extends('layouts.app')
@section('title', 'New Assignment')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('production-assignments.index') }}" class="text-[#0066CC] hover:underline text-sm">Assignments</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">New Assignment</span>
</div>

<div class="max-w-2xl"
     x-data="{
        selectedCatalogueId: '{{ old('catalogue_id', '') }}',
        selectedDestination: '{{ old('destination', '') }}',
        selectedUnit: '{{ old('stitching_unit', '') }}',
        catalogues: {{ Js::from($catalogues) }},

        /* NP: quantities and prices keyed by design.id */
        npQty:   {},
        npPrice: {},

        /* Stitching: single design + per-size counts */
        selectedDesignId: '{{ old('design_id', '') }}',
        sizes: {
            xs: {{ old('items.0.qty', 0) }},
            s:  {{ old('items.1.qty', 0) }},
            m:  {{ old('items.2.qty', 0) }},
            l:  {{ old('items.3.qty', 0) }},
            xl: {{ old('items.4.qty', 0) }}
        },

        /* ── Derived ─────────────────────────────────────────── */
        get designs() {
            const cat = this.catalogues.find(c => c.id == this.selectedCatalogueId);
            return cat ? cat.designs : [];
        },

        get npDesigns() {
            return this.designs.filter(d => d.needs_naeem_pakki);
        },

        get selectedDesign() {
            return this.designs.find(d => d.id == this.selectedDesignId) || null;
        },

        get availableQty() {
            return this.selectedDesign ? (this.selectedDesign.available_qty ?? 0) : null;
        },

        get isNaeemPakki()    { return this.selectedDestination === 'naeem_pakki'; },
        get isStitchingUnit() { return this.selectedDestination === 'stitching_unit'; },

        /* Stitching totals */
        get totalQty() {
            return Object.values(this.sizes).reduce((s, v) => s + (parseInt(v) || 0), 0);
        },
        get nothingAvailable() {
            return this.selectedDesignId !== '' && this.availableQty !== null && this.availableQty === 0;
        },
        get isOverLimit() {
            return this.availableQty !== null && this.totalQty > this.availableQty;
        },

        /* NP helpers */
        npQtyFor(id)  { return parseInt(this.npQty[id] || 0); },
        npOverFor(id) {
            const d = this.npDesigns.find(x => x.id == id);
            if (!d) return false;
            const q = this.npQtyFor(id);
            return q > 0 && q > (d.available_qty || 0);
        },
        get npHasAnyQty()    { return this.npDesigns.some(d => this.npQtyFor(d.id) > 0); },
        get npAnyOverLimit() { return this.npDesigns.some(d => this.npOverFor(d.id)); },
        get npTotalAmount() {
            return this.npDesigns.reduce((sum, d) => {
                const qty   = this.npQtyFor(d.id);
                const price = parseFloat(this.npPrice[d.id] || 0);
                return sum + (qty * price);
            }, 0);
        },

        /* Submit guard */
        get canProceed() {
            if (!this.selectedDestination || !this.selectedCatalogueId) return false;
            if (this.isNaeemPakki)    return this.npDesigns.length > 0 && this.npHasAnyQty && !this.npAnyOverLimit;
            if (this.isStitchingUnit) return this.selectedDesignId !== '' && !this.nothingAvailable && !this.isOverLimit && this.totalQty > 0;
            return false;
        }
     }">

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">New Production Assignment</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('production-assignments.store') }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">

            {{-- ① Catalogue ──────────────────────────────────── --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                <select name="catalogue_id" x-model="selectedCatalogueId" class="apple-input" required>
                    <option value="">— Select catalogue —</option>
                    @foreach($catalogues as $cat)
                    <option value="{{ $cat->id }}" {{ old('catalogue_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- ② Destination (shown once catalogue is picked) ─ --}}
            <div x-show="designs.length > 0" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Destination</label>
                <div class="grid grid-cols-2 gap-3">

                    {{-- Naeem Pakki card --}}
                    <label class="relative cursor-pointer">
                        <input type="radio" name="destination" value="naeem_pakki"
                               x-model="selectedDestination" class="sr-only peer">
                        <div class="flex flex-col items-center justify-center p-4 border-2 rounded-xl text-center transition-all
                                    border-[#E8E8ED] bg-white text-[#6E6E73]
                                    peer-checked:border-[#FF9500] peer-checked:bg-[#FFF8EE] peer-checked:text-[#C67500]
                                    hover:border-[#FF9500] hover:bg-[#FFF8EE]">
                            <svg class="w-5 h-5 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                            </svg>
                            <span class="text-xs font-semibold">Naeem Pakki</span>
                            <span class="text-[10px] mt-0.5 opacity-70">Embroidery</span>
                        </div>
                    </label>

                    {{-- Stitching Unit card --}}
                    <label class="relative cursor-pointer">
                        <input type="radio" name="destination" value="stitching_unit"
                               x-model="selectedDestination" class="sr-only peer">
                        <div class="flex flex-col items-center justify-center p-4 border-2 rounded-xl text-center transition-all
                                    border-[#E8E8ED] bg-white text-[#6E6E73]
                                    peer-checked:border-[#AF52DE] peer-checked:bg-[#F5EEFF] peer-checked:text-[#8B37C0]
                                    hover:border-[#AF52DE] hover:bg-[#F5EEFF]">
                            <svg class="w-5 h-5 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs font-semibold">Stitching Unit</span>
                            <span class="text-[10px] mt-0.5 opacity-70">Direct stitching</span>
                        </div>
                    </label>

                </div>
            </div>

            {{-- ══════════════════════════════════════════════════
                 NAEEM PAKKI SECTION
            ══════════════════════════════════════════════════ --}}
            <div x-show="isNaeemPakki" x-cloak class="space-y-3">

                {{-- No NP designs in this catalogue --}}
                <div x-show="npDesigns.length === 0" x-cloak
                     class="flex items-start gap-2 px-3 py-2.5 bg-[#FFF8EE] border border-[#FFD699] rounded-xl text-xs text-[#C67500] font-medium">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span>No designs in this catalogue are flagged for Naeem Pakki embroidery.</span>
                </div>

                {{-- NP design table --}}
                <div x-show="npDesigns.length > 0" x-cloak class="border border-[#E8E8ED] rounded-xl overflow-hidden">
                    <div class="px-4 py-3 bg-[#FFF8EE] border-b border-[#FFD699]">
                        <p class="text-xs font-semibold text-[#C67500] uppercase tracking-widest">Select Designs &amp; Quantities</p>
                        <p class="text-xs text-[#6E6E73] mt-0.5">Leave quantity as 0 to skip a design. Rate is required when quantity &gt; 0.</p>
                    </div>
                    <table class="w-full apple-table">
                        <thead>
                            <tr>
                                <th class="text-left">Design</th>
                                <th class="text-center" style="color:#0071E3">Available</th>
                                <th class="text-center">Qty to Send</th>
                                <th class="text-right">Rate (Rs./pc)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="design in npDesigns" :key="design.id">
                                <tr>
                                    <td>
                                        {{-- Hidden fields submitted for every NP design --}}
                                        <input type="hidden" :name="'np_items[' + design.id + '][design_id]'" :value="design.id">
                                        <span class="font-medium text-[#1D1D1F]" x-text="design.name"></span>
                                        <template x-if="npOverFor(design.id)">
                                            <span class="ml-1.5 inline-flex items-center text-[10px] font-semibold text-[#FF3B30] bg-[#FFF0EF] border border-[#FFCDD0] px-1.5 py-0.5 rounded-full">
                                                exceeds limit
                                            </span>
                                        </template>
                                    </td>
                                    <td class="text-center">
                                        <span class="text-sm font-semibold"
                                              :class="(design.available_qty || 0) > 0 ? 'text-[#0071E3]' : 'text-[#FF3B30]'"
                                              x-text="(design.available_qty || 0).toLocaleString()"></span>
                                        <span class="text-[10px] text-[#86868B]"> pcs</span>
                                    </td>
                                    <td class="text-center">
                                        <input type="number"
                                               :name="'np_items[' + design.id + '][quantity]'"
                                               x-model.number="npQty[design.id]"
                                               min="0" :max="design.available_qty || 0"
                                               class="apple-input text-center"
                                               style="width:5rem"
                                               placeholder="0"
                                               :class="npOverFor(design.id) ? 'border-[#FF3B30] bg-[#FFF0EF]' : ''">
                                    </td>
                                    <td class="text-right">
                                        <input type="number"
                                               :name="'np_items[' + design.id + '][per_piece_price]'"
                                               x-model="npPrice[design.id]"
                                               min="0" step="0.01"
                                               class="apple-input text-right"
                                               style="width:6.5rem"
                                               placeholder="0.00"
                                               :required="npQtyFor(design.id) > 0">
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot x-show="npHasAnyQty" x-cloak>
                            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                                <td colspan="2" class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wide">
                                    Total Payable to Naeem Pakki
                                </td>
                                <td class="text-center tabular-nums font-semibold text-[#1D1D1F]"
                                    x-text="npDesigns.reduce((s,d) => s + npQtyFor(d.id), 0).toLocaleString() + ' pcs'"></td>
                                <td class="text-right tabular-nums font-bold text-[#FF9500] text-base pr-4"
                                    x-text="'Rs. ' + npTotalAmount.toLocaleString('en-PK', {minimumFractionDigits: 0, maximumFractionDigits: 0})"></td>
                            </tr>
                        </tfoot>
                    </table>

                    {{-- Over-limit footer warning --}}
                    <div x-show="npAnyOverLimit" x-cloak
                         class="px-4 py-2.5 border-t border-[#FFCDD0] bg-[#FFF0EF] flex items-start gap-2 text-xs text-[#FF3B30] font-medium">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        One or more quantities exceed what's available in factory. Reduce before saving.
                    </div>

                    {{-- All-good footer --}}
                    <div x-show="npHasAnyQty && !npAnyOverLimit" x-cloak
                         class="px-4 py-2.5 border-t border-[#A7F3D0] bg-[#ECFDF5] flex items-center gap-2 text-xs text-[#059669] font-medium">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Quantities look good — all within available stock.
                    </div>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════
                 STITCHING SECTION
            ══════════════════════════════════════════════════ --}}
            <div x-show="isStitchingUnit" x-cloak class="space-y-5">

                {{-- Design selector --}}
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Design</label>
                    <select name="design_id" x-model="selectedDesignId" class="apple-input">
                        <option value="">— Select design —</option>
                        <template x-for="design in designs" :key="design.id">
                            <option :value="design.id" x-text="design.name"></option>
                        </template>
                    </select>

                    {{-- Available qty callout --}}
                    <div x-show="selectedDesignId !== ''" x-cloak class="mt-2">
                        <div x-show="nothingAvailable" x-cloak
                             class="flex items-start gap-2 px-3 py-2.5 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-xs text-[#FF3B30] font-medium">
                            <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            <span>No pieces available in factory for this design. Receive fabric first before creating an assignment.</span>
                        </div>
                        <div x-show="!nothingAvailable" x-cloak
                             class="flex items-center gap-2 px-3 py-2 bg-[#F0F7FF] border border-[#C7E0FF] rounded-xl text-xs font-medium text-[#0071E3]">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                            </svg>
                            <span><strong x-text="availableQty"></strong> pieces available in factory for this design</span>
                        </div>
                    </div>
                </div>

                {{-- Stitching Unit selector --}}
                <div x-show="selectedDesignId !== '' && !nothingAvailable" x-cloak>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                        Stitching Unit <span class="text-[#FF3B30]">*</span>
                    </label>
                    <div class="grid grid-cols-4 gap-3">
                        @foreach([1, 2, 3, 4] as $unit)
                        <label class="relative cursor-pointer">
                            <input type="radio" name="stitching_unit" value="{{ $unit }}"
                                   x-model="selectedUnit"
                                   class="sr-only peer"
                                   {{ old('stitching_unit') == $unit ? 'checked' : '' }}>
                            <div class="flex flex-col items-center justify-center p-4 border-2 rounded-xl text-center transition-all
                                        border-[#E8E8ED] bg-white text-[#6E6E73]
                                        peer-checked:border-[#AF52DE] peer-checked:bg-[#F5EEFF] peer-checked:text-[#AF52DE]
                                        hover:border-[#AF52DE] hover:bg-[#F5EEFF]">
                                <svg class="w-5 h-5 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs font-semibold">Unit {{ $unit }}</span>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <p class="mt-2 text-[#86868B] text-xs">Select which stitching unit will process this batch</p>
                </div>
            </div>

            {{-- ③ Assignment Date (once a destination is chosen) --}}
            <div x-show="selectedDestination !== ''" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Assignment Date</label>
                <input type="date" name="assignment_date"
                       value="{{ old('assignment_date', date('Y-m-d')) }}"
                       class="apple-input" required>
            </div>

        </div>{{-- /card --}}

        {{-- Size Quantities (Stitching only) ─────────────────── --}}
        <div class="card overflow-hidden"
             x-show="isStitchingUnit && selectedDesignId !== '' && !nothingAvailable" x-cloak>
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces per Size</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Enter the quantity being assigned for each size</p>
            </div>
            <div class="p-5 grid grid-cols-5 gap-3">
                @foreach(['xs','s','m','l','xl'] as $size)
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2 text-center">{{ strtoupper($size) }}</label>
                    <input type="hidden" name="items[{{ $loop->index }}][size]" value="{{ $size }}">
                    <input type="number" name="items[{{ $loop->index }}][qty]"
                           min="0"
                           x-model.number="sizes.{{ $size }}"
                           class="apple-input text-center">
                </div>
                @endforeach
            </div>

            <div class="px-5 pb-4 pt-1 border-t border-[#F2F2F7]">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-[#6E6E73]">Total pieces being assigned:</span>
                    <span class="text-sm font-semibold"
                          :class="isOverLimit ? 'text-[#FF3B30]' : 'text-[#1D1D1F]'"
                          x-text="totalQty"></span>
                </div>
                <div x-show="selectedDesignId !== '' && isOverLimit" x-cloak
                     class="mt-2 flex items-start gap-2 px-3 py-2 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-xs text-[#FF3B30] font-medium">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    <span>
                        Total (<strong x-text="totalQty"></strong>) exceeds available
                        (<strong x-text="availableQty"></strong>) pieces in factory. Please reduce before saving.
                    </span>
                </div>
                <div x-show="selectedDesignId !== '' && availableQty !== null && totalQty > 0 && !isOverLimit" x-cloak
                     class="mt-2 flex items-center gap-2 px-3 py-2 bg-[#ECFDF5] border border-[#A7F3D0] rounded-xl text-xs text-[#059669] font-medium">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Within available limit — <strong x-text="availableQty - totalQty"></strong> pieces will remain in factory after assignment</span>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary"
                    :disabled="!canProceed"
                    :class="!canProceed ? 'opacity-50 cursor-not-allowed' : ''">
                Save Assignment
            </button>
            <a href="{{ route('production-assignments.index') }}" class="btn-secondary">Cancel</a>
        </div>

    </form>
</div>

@endsection
