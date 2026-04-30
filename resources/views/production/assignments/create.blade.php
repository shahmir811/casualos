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
        selectedCatalogueId: '',
        selectedDesignId: '',
        selectedDestination: '{{ old('destination', '') }}',
        selectedUnit: '{{ old('stitching_unit', '') }}',
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
        get availableQty() {
            return this.selectedDesign ? (this.selectedDesign.available_qty ?? 0) : null;
        },
        get isNaeemPakki() {
            return this.selectedDestination === 'naeem_pakki';
        },
        get isStitchingUnit() {
            return this.selectedDestination === 'stitching_unit';
        },
        get totalQty() {
            return Object.values(this.sizes).reduce((sum, v) => sum + (parseInt(v) || 0), 0);
        },
        get isOverLimit() {
            return this.availableQty !== null && this.totalQty > this.availableQty;
        },
        get nothingAvailable() {
            return this.selectedDesignId !== '' && this.availableQty !== null && this.availableQty === 0;
        },
        get canProceed() {
            return !this.nothingAvailable && !this.isOverLimit;
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

            {{-- Design (shown once catalogue picked) --}}
            <div x-show="designs.length > 0" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Design</label>
                <select name="design_id" x-model="selectedDesignId" class="apple-input" required>
                    <option value="">— Select design —</option>
                    <template x-for="design in designs" :key="design.id">
                        <option :value="design.id" x-text="design.name"></option>
                    </template>
                </select>

                {{-- Available qty callout --}}
                <div x-show="selectedDesignId !== ''" x-cloak class="mt-2">
                    {{-- 0 available: hard block --}}
                    <div x-show="nothingAvailable"
                         class="flex items-start gap-2 px-3 py-2.5 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-xs text-[#FF3B30] font-medium">
                        <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        <span>No pieces available in factory for this design. Receive fabric first before creating an assignment.</span>
                    </div>
                    {{-- >0 available: info --}}
                    <div x-show="!nothingAvailable"
                         class="flex items-center gap-2 px-3 py-2 bg-[#F0F7FF] border border-[#C7E0FF] rounded-xl text-xs font-medium text-[#0071E3]">
                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
                        </svg>
                        <span><strong x-text="availableQty"></strong> pieces available in factory for this design</span>
                    </div>
                </div>
            </div>

            {{-- ── All fields below are locked when nothing is available ── --}}
            <div x-show="!nothingAvailable || selectedDesignId === ''" x-cloak class="space-y-5">

            {{-- Destination --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Destination</label>
                <select name="destination" x-model="selectedDestination" class="apple-input" required>
                    <option value="">— Select destination —</option>
                    <option value="naeem_pakki">Naeem Pakki (Embroidery)</option>
                    <option value="stitching_unit">Stitching Unit</option>
                </select>
            </div>

            {{-- Stitching Unit selector — shown when destination = stitching_unit --}}
            <div x-show="isStitchingUnit" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Stitching Unit
                    <span class="text-[#FF3B30]">*</span>
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

            {{-- Naeem Pakki rate — only shown when destination is Naeem Pakki --}}
            <div x-show="isNaeemPakki" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Naeem Pakki Rate (Rs. per piece)
                    <span class="text-[#FF3B30]">*</span>
                </label>
                <input type="number" name="naeem_pakki_rate"
                       value="{{ old('naeem_pakki_rate') }}"
                       step="0.01" min="0"
                       class="apple-input"
                       placeholder="e.g. 150"
                       :required="isNaeemPakki">
                <p class="mt-1 text-[#86868B] text-xs">Agreed embroidery rate for this design</p>
            </div>

            {{-- Assignment Date --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Assignment Date</label>
                <input type="date" name="assignment_date" value="{{ old('assignment_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>

            </div>{{-- end: locked-when-nothing-available --}}
        </div>

        {{-- Size Quantities — hidden when nothing available --}}
        <div class="card overflow-hidden" x-show="!nothingAvailable || selectedDesignId === ''" x-cloak>
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

            {{-- Live total counter --}}
            <div class="px-5 pb-4 pt-1 border-t border-[#F2F2F7]">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-[#6E6E73]">Total pieces being assigned:</span>
                    <span class="text-sm font-semibold" :class="isOverLimit ? 'text-[#FF3B30]' : 'text-[#1D1D1F]'"
                          x-text="totalQty"></span>
                </div>

                {{-- Warning: over limit --}}
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

                {{-- Confirmation: within limit --}}
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
