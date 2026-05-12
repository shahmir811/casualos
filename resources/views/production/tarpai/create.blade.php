@extends('layouts.app')
@section('title', 'Log Tarpai Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('tarpai-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Tarpai</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Send</span>
</div>

<div x-data="{
        selectedCatalogueId: '{{ old('catalogue_id', '') }}',
        catalogues: {{ Js::from($catalogues) }},
        availableQty: {{ Js::from($availableQty) }},
        quantities: {{ Js::from($oldQuantities) }},
        sizes: ['xs','s','m','l','xl'],
        tarpaiHouse: '{{ old('tarpai_house', '') }}',
        perPieceRate: {{ old('tarpai_house') === 'in_house' ? 0 : (int) old('per_piece_price', 30) }},

        get selectedCatalogue() {
            return this.catalogues.find(c => c.id == this.selectedCatalogueId) || null;
        },
        get designs() {
            return this.selectedCatalogue ? this.selectedCatalogue.designs : [];
        },
        get grandTotal() {
            return this.designs.reduce((sum, d) => sum + this.designTotal(d.id), 0);
        },

        onCatalogueChange() {
            this.quantities = {};
        },
        onHouseChange() {
            this.perPieceRate = this.tarpaiHouse === 'in_house' ? 0 : 30;
        },
        getAvail(designId, size) {
            return this.availableQty?.[this.selectedCatalogueId]?.[designId]?.[size] ?? 0;
        },
        getQty(designId, size) {
            return this.quantities?.[designId]?.[size] ?? 0;
        },
        setQty(designId, size, val) {
            if (!this.quantities[designId]) this.quantities[designId] = {};
            this.quantities[designId][size] = parseInt(val) || 0;
        },
        designTotal(designId) {
            return this.sizes.reduce((s, sz) => s + this.getQty(designId, sz), 0);
        },
        isOverLimit(designId, size) {
            const avail = this.getAvail(designId, size);
            return avail > 0 && this.getQty(designId, size) > avail;
        },
        get isFormValid() {
            return this.designs.every(d =>
                this.sizes.every(s => !this.isOverLimit(d.id, s))
            );
        }
     }">

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Tarpai Send</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('tarpai-sends.store') }}" class="space-y-5">
        @csrf

        {{-- Meta fields --}}
        <div class="card p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                    <select name="catalogue_id"
                            x-model="selectedCatalogueId"
                            @change="onCatalogueChange()"
                            class="apple-input" required>
                        <option value="">— Select catalogue —</option>
                        @foreach($catalogues as $cat)
                        <option value="{{ $cat->id }}" {{ old('catalogue_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Tarpai House</label>
                    <select name="tarpai_house" x-model="tarpaiHouse" @change="onHouseChange()" class="apple-input" required>
                        <option value="">— Select house —</option>
                        <option value="rashid_bhai">Rashid Bhai</option>
                        <option value="yousaf_bhai">Yousaf Bhai</option>
                        <option value="in_house">In-House</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Send Date</label>
                    <input type="date" name="sent_date" value="{{ old('sent_date', date('Y-m-d')) }}" class="apple-input" required>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Per Piece Rate (Rs.)</label>
                    <input type="number" name="per_piece_price"
                           x-model="perPieceRate"
                           :disabled="tarpaiHouse === 'in_house'"
                           step="0.01" min="0"
                           :class="tarpaiHouse === 'in_house'
                               ? 'apple-input opacity-50 cursor-not-allowed bg-[#F5F5F7]'
                               : 'apple-input'"
                           required>
                </div>
            </div>
        </div>

        {{-- Design table — shown once a catalogue is selected --}}
        <div x-show="designs.length > 0" x-cloak>
            <div class="card overflow-x-auto">
                <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-[#1D1D1F]">Kameez Pieces Sent (by Design &amp; Size)</h2>
                        <p class="text-xs text-[#86868B] mt-0.5">Only Kameez pieces go for Tarpai. Available figures are based on stitching returns.</p>
                    </div>
                    <span class="text-sm font-semibold text-[#1D1D1F]">
                        Total: <span x-text="grandTotal" class="text-[#0071E3]">0</span> pcs
                    </span>
                </div>

                {{-- No available pieces warning --}}
                <div x-show="designs.length > 0 && designs.every(d => sizes.every(s => getAvail(d.id, s) === 0))"
                     class="px-5 py-4 bg-amber-50 border-b border-amber-200 text-amber-700 text-sm">
                    No Kameez pieces are available for Tarpai in this catalogue. Record stitching returns first.
                </div>

                <table class="w-full text-sm">
                    <thead class="bg-[#F5F5F7]">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Design</th>
                            <template x-for="size in sizes" :key="size">
                                <th class="text-center px-3 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest" x-text="size.toUpperCase()"></th>
                            </template>
                            <th class="text-right px-5 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Row Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F2F2F7]">
                        <template x-for="(design, dIdx) in designs" :key="design.id">
                            <tr>
                                <td class="px-5 py-3 font-medium text-[#1D1D1F]">
                                    <span x-text="design.name"></span>
                                    <input type="hidden" :name="`designs[${dIdx}][design_id]`" :value="design.id">
                                </td>
                                <template x-for="(size, sIdx) in sizes" :key="size">
                                    <td class="px-3 py-3 text-center">
                                        <input type="hidden" :name="`designs[${dIdx}][items][${sIdx}][size]`" :value="size">
                                        <input type="number"
                                               :name="`designs[${dIdx}][items][${sIdx}][qty]`"
                                               :value="getQty(design.id, size)"
                                               @input="setQty(design.id, size, $event.target.value)"
                                               min="0"
                                               :readonly="getAvail(design.id, size) === 0"
                                               :class="getAvail(design.id, size) === 0
                                                   ? 'w-16 text-center px-1 py-1.5 text-sm bg-[#F5F5F7] text-[#C7C7CC] rounded-lg border border-[#E8E8ED] cursor-not-allowed outline-none'
                                                   : isOverLimit(design.id, size)
                                                       ? 'w-16 text-center px-1 py-1.5 text-sm border-2 border-red-500 bg-red-50 rounded-lg outline-none'
                                                       : 'w-16 apple-input text-center px-1 py-1.5 text-sm'">
                                        <div class="text-xs mt-1 tabular-nums"
                                             :class="getAvail(design.id, size) === 0 ? 'text-red-400' : 'text-[#86868B]'"
                                             x-text="'Avail: ' + getAvail(design.id, size)"></div>
                                    </td>
                                </template>
                                <td class="px-5 py-3 text-right font-semibold text-[#1D1D1F] tabular-nums"
                                    x-text="designTotal(design.id) + ' pcs'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit"
                    :disabled="!isFormValid"
                    :class="!isFormValid
                        ? 'btn-primary opacity-40 cursor-not-allowed'
                        : 'btn-primary'">
                Save Send
            </button>
            <a href="{{ route('tarpai-sends.index') }}" class="btn-secondary">Cancel</a>
            <span x-show="!isFormValid && designs.length > 0"
                  class="text-sm text-red-500 font-medium">
                Some quantities exceed available pieces.
            </span>
        </div>
    </form>
</div>

@endsection
