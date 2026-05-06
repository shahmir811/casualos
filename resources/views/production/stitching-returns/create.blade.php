@extends('layouts.app')
@section('title', 'Log Stitching Return')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('stitching-returns.index') }}" class="text-[#0066CC] hover:underline text-sm">Stitching Returns</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Return</span>
</div>

<div class="max-w-2xl"
     x-data="{
        selectedCatalogueId: '{{ old('catalogue_id', '') }}',
        selectedDesignId: '{{ old('design_id', '') }}',
        selectedUnit: '{{ old('stitching_unit_id', '') }}',
        catalogues: {{ Js::from($catalogues) }},
        sizes: {
            xs: {{ old('items.0.qty', 0) }},
            s:  {{ old('items.1.qty', 0) }},
            m:  {{ old('items.2.qty', 0) }},
            l:  {{ old('items.3.qty', 0) }},
            xl: {{ old('items.4.qty', 0) }}
        },
        get designs() {
            const cat = this.catalogues.find(c => c.id == this.selectedCatalogueId);
            return cat ? cat.designs : [];
        },
        get selectedDesign() {
            return this.designs.find(d => d.id == this.selectedDesignId) || null;
        },
        get totalQty() {
            return Object.values(this.sizes).reduce((sum, v) => sum + (parseInt(v) || 0), 0);
        },
        get remainingQty() {
            return this.selectedDesign ? (this.selectedDesign.remaining_qty ?? 0) : null;
        },
        get assignedQty() {
            return this.selectedDesign ? (this.selectedDesign.assigned_qty ?? 0) : 0;
        },
        get returnedQty() {
            return this.selectedDesign ? (this.selectedDesign.returned_qty ?? 0) : 0;
        },
        remainingForSize(size) {
            if (!this.selectedDesign || !this.selectedDesign.remaining_sizes) return null;
            return this.selectedDesign.remaining_sizes[size] ?? 0;
        },
        sizeOverLimit(size) {
            const rem = this.remainingForSize(size);
            return rem !== null && (parseInt(this.sizes[size]) || 0) > rem;
        },
        get overLimit() {
            if (this.remainingQty !== null && this.totalQty > this.remainingQty) return true;
            // Also block if any individual size exceeds its per-size cap
            return ['xs','s','m','l','xl'].some(s => this.sizeOverLimit(s));
        },
        get canSubmit() {
            return this.selectedUnit !== '' && this.totalQty > 0 && !this.overLimit;
        },
        onDesignChange() {
            // Reset sizes on design change
            this.sizes = { xs: 0, s: 0, m: 0, l: 0, xl: 0 };
            // Auto-select stitching unit from the production assignment
            if (this.selectedDesign && this.selectedDesign.stitching_unit_id) {
                this.selectedUnit = String(this.selectedDesign.stitching_unit_id);
            } else {
                this.selectedUnit = '';
            }
        }
     }">

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Stitching Return</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('stitching-returns.store') }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">

            {{-- Catalogue --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                <select name="catalogue_id" x-model="selectedCatalogueId" class="apple-input" required>
                    <option value="">— Select catalogue —</option>
                    @foreach($catalogues as $cat)
                    <option value="{{ $cat->id }}" {{ old('catalogue_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Design --}}
            <div x-show="designs.length > 0" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Design</label>
                <select name="design_id" x-model="selectedDesignId" @change="onDesignChange()" class="apple-input" required>
                    <option value="">— Select design —</option>
                    <template x-for="design in designs" :key="design.id">
                        <option :value="design.id" x-text="design.name"></option>
                    </template>
                </select>
            </div>

            {{-- Stitching Unit --}}
            <div x-show="selectedDesignId !== ''" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Stitching Unit
                    <span class="text-[#FF3B30]">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-{{ min(count($stitchingUnits), 4) }}">
                    @foreach($stitchingUnits as $unit)
                    <label class="relative cursor-pointer">
                        <input type="radio" name="stitching_unit_id" value="{{ $unit->id }}"
                               x-model="selectedUnit"
                               class="sr-only peer">
                        <div class="flex flex-col items-center justify-center p-4 border-2 rounded-xl text-center transition-all
                                    border-[#E8E8ED] bg-white text-[#6E6E73]
                                    peer-checked:border-[#AF52DE] peer-checked:bg-[#F5EEFF] peer-checked:text-[#AF52DE]
                                    hover:border-[#AF52DE] hover:bg-[#F5EEFF]">
                            <svg class="w-5 h-5 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="text-xs font-semibold">Unit {{ $unit->number }}</span>
                            <span class="text-[10px] mt-0.5 text-current opacity-70 leading-tight">{{ $unit->name }}</span>
                        </div>
                    </label>
                    @endforeach
                </div>
                {{-- Auto-select note --}}
                <p x-show="selectedDesign && selectedDesign.stitching_unit_id" x-cloak
                   class="mt-2 text-[10px] text-[#86868B]">
                    Auto-selected from production assignment — change if incorrect
                </p>
                <p x-show="selectedDesign && !selectedDesign.stitching_unit_id" x-cloak
                   class="mt-2 text-[10px] text-[#FF9500]">
                    No stitching unit found on the assignment — please select manually
                </p>
            </div>

            {{-- Return Date --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                <input type="date" name="return_date" value="{{ old('return_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>
        </div>

        {{-- Remaining pieces callout (shown once a design is selected) --}}
        <div x-show="selectedDesignId !== ''" x-cloak>

            {{-- Design has stitching assignments --}}
            <template x-if="assignedQty > 0">
                <div class="rounded-xl border px-4 py-3 flex items-start gap-3"
                     :class="remainingQty > 0 ? 'bg-[#F0F6FF] border-[#C7DEFF]' : 'bg-[#FFF0EF] border-[#FFCDD0]'">
                    <div class="mt-0.5 flex-shrink-0 w-5 h-5 rounded-full flex items-center justify-center"
                         :class="remainingQty > 0 ? 'bg-[#0066CC]' : 'bg-[#FF3B30]'">
                        <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 110 20A10 10 0 0112 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold mb-1"
                           :class="remainingQty > 0 ? 'text-[#0066CC]' : 'text-[#FF3B30]'">
                            <span x-text="remainingQty > 0 ? remainingQty + ' pieces outstanding' : 'All pieces already returned'"></span>
                        </p>
                        <p class="text-xs text-[#6E6E73]">
                            <span x-text="assignedQty"></span> assigned
                            &nbsp;·&nbsp;
                            <span x-text="returnedQty"></span> already returned
                            &nbsp;·&nbsp;
                            <span x-text="remainingQty"></span> remaining
                        </p>
                    </div>
                </div>
            </template>

            {{-- Design has no stitching assignment at all --}}
            <template x-if="assignedQty === 0">
                <div class="rounded-xl border border-[#FFE5B0] bg-[#FFFBF0] px-4 py-3 text-xs text-[#B05C00]">
                    No stitching assignment found for this design in this catalogue.
                </div>
            </template>
        </div>

        {{-- Pieces by size --}}
        <div class="card overflow-hidden" x-show="selectedDesignId !== '' && remainingQty > 0" x-cloak>
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces Returned by Size</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Maximum returnable: <span class="font-semibold text-[#0066CC]" x-text="remainingQty"></span> pcs</p>
            </div>
            <div class="p-5 grid grid-cols-5 gap-3">
                @foreach(['xs','s','m','l','xl'] as $size)
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest mb-2 text-center transition-colors"
                           :class="sizeOverLimit('{{ $size }}') ? 'text-[#FF3B30]' : 'text-[#6E6E73]'">
                        {{ strtoupper($size) }}
                    </label>
                    <input type="hidden" name="items[{{ $loop->index }}][size]" value="{{ $size }}">
                    <input type="number" name="items[{{ $loop->index }}][qty]"
                           min="0"
                           x-model.number="sizes.{{ $size }}"
                           class="apple-input text-center transition-all"
                           :class="sizeOverLimit('{{ $size }}') ? 'border-[#FF3B30] bg-[#FFF0EF] text-[#FF3B30] focus:ring-red-200' : ''">
                    {{-- Per-size remaining hint --}}
                    <div class="mt-1.5 text-center" x-show="selectedDesignId !== ''" x-cloak>
                        <template x-if="!sizeOverLimit('{{ $size }}')">
                            <span class="text-[10px] font-medium"
                                  :class="remainingForSize('{{ $size }}') > 0 ? 'text-[#86868B]' : 'text-[#C7C7CC]'"
                                  x-text="remainingForSize('{{ $size }}') + ' left'"></span>
                        </template>
                        <template x-if="sizeOverLimit('{{ $size }}')">
                            <span class="text-[10px] font-semibold text-[#FF3B30]"
                                  x-text="'max ' + remainingForSize('{{ $size }}')"></span>
                        </template>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="px-5 pb-4 pt-1 border-t border-[#F2F2F7]">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-[#6E6E73]">Total pieces being returned:</span>
                    <span class="text-sm font-semibold"
                          :class="overLimit ? 'text-[#FF3B30]' : (totalQty > 0 ? 'text-[#34C759]' : 'text-[#1D1D1F]')"
                          x-text="totalQty"></span>
                </div>
                <p x-show="overLimit" x-cloak
                   class="mt-1.5 text-xs text-[#FF3B30] font-medium">
                    ⚠ Exceeds the <span x-text="remainingQty"></span> remaining pieces — please reduce quantities.
                </p>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                    class="btn-primary disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="!canSubmit">
                Save Return
            </button>
            <a href="{{ route('stitching-returns.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
