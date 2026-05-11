@extends('layouts.app')
@section('title', 'Log Press Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('press-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Press</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Press Send</span>
</div>

<div class="max-w-3xl"
     x-data="{
        selectedCatalogueId: '{{ old('catalogue_id', '') }}',
        catalogues: {{ Js::from($catalogues) }},
        availableQty: {{ Js::from($availableQty) }},
        oldQuantities: {{ Js::from($oldQuantities) }},
        inputValues: {},
        get designs() {
            const cat = this.catalogues.find(c => c.id == this.selectedCatalogueId);
            return cat ? cat.designs : [];
        },
        availableFor(designId, size) {
            return this.availableQty[this.selectedCatalogueId]?.[designId]?.[size] ?? 0;
        },
        oldQty(designId, size) {
            return this.oldQuantities[designId]?.[size] ?? 0;
        },
        updateQty(designId, size, value) {
            const key = designId + '_' + size;
            this.inputValues[key] = parseInt(value) || 0;
        },
        currentQty(designId, size) {
            const key = designId + '_' + size;
            return this.inputValues[key] ?? this.oldQty(designId, size);
        },
        isOverLimit(designId, size) {
            const avail = this.availableFor(designId, size);
            if (avail === 0) return false;
            return this.currentQty(designId, size) > avail;
        },
        get hasAnyError() {
            return this.designs.some(d =>
                ['xs','s','m','l','xl'].some(s => this.isOverLimit(d.id, s))
            );
        }
     }">

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Press Send</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('press-sends.store') }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                <select name="catalogue_id" x-model="selectedCatalogueId" class="apple-input" required>
                    <option value="">— Select catalogue —</option>
                    @foreach($catalogues as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Sent Date</label>
                <input type="date" name="sent_date" value="{{ old('sent_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>
        </div>

        <div x-show="designs.length > 0" x-cloak>
            <p class="text-xs text-[#6E6E73] mb-3">Available quantities are based on Tarpai returns minus pieces already sent to press.</p>

            <template x-for="(design, dIndex) in designs" :key="design.id">
                <div class="card overflow-hidden mb-4">
                    <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-[#1D1D1F]" x-text="design.name"></h3>
                        <input type="hidden" :name="'designs[' + dIndex + '][design_id]'" :value="design.id">
                    </div>
                    <div class="p-5">
                        <div class="grid grid-cols-5 gap-3">
                            <template x-for="size in ['xs','s','m','l','xl']" :key="size">
                                <div>
                                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1 text-center" x-text="size.toUpperCase()"></label>
                                    <p class="text-xs text-[#86868B] text-center mb-2">
                                        Avail: <span class="font-medium text-[#1D1D1F]" x-text="availableFor(design.id, size)"></span>
                                    </p>
                                    <input type="hidden" :name="'designs[' + dIndex + '][items][' + ['xs','s','m','l','xl'].indexOf(size) + '][size]'" :value="size">
                                    <input type="number"
                                           :name="'designs[' + dIndex + '][items][' + ['xs','s','m','l','xl'].indexOf(size) + '][qty]'"
                                           min="0"
                                           :max="availableFor(design.id, size)"
                                           :value="oldQty(design.id, size)"
                                           :disabled="availableFor(design.id, size) === 0"
                                           @input="updateQty(design.id, size, $event.target.value)"
                                           class="apple-input text-center"
                                           :class="isOverLimit(design.id, size) ? 'ring-2 ring-red-400 bg-red-50 text-red-600' : ''">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="selectedCatalogueId && designs.length === 0" x-cloak>
            <div class="card p-6 text-center text-[#86868B] text-sm">No in-house designs found for this catalogue.</div>
        </div>

        <div class="flex gap-3" x-show="designs.length > 0" x-cloak>
            <button type="submit" class="btn-primary"
                    :disabled="hasAnyError"
                    :class="hasAnyError ? 'opacity-50 cursor-not-allowed' : ''">
                Record Press Send
            </button>
            <a href="{{ route('press-sends.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
