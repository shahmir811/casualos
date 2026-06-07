@extends('layouts.app')
@section('title', 'Log Outsourced Batch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('outsourced-batches.index') }}" class="text-[#0066CC] hover:underline text-sm">Outsourced Batches</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Batch</span>
</div>

<div class="max-w-4xl"
     x-data="{
        selectedCatalogueId: '{{ $catalogue->id }}',
        catalogues: {{ Js::from([$catalogue]) }},
        qtys: {},
        get designs() {
            const cat = this.catalogues.find(c => c.id == this.selectedCatalogueId);
            return cat ? cat.designs.filter(d => d.manufacturing_type === 'outsourced') : [];
        },
        setQty(designId, size, val) {
            if (!this.qtys[designId]) this.qtys[designId] = {};
            this.qtys[designId][size] = parseInt(val) || 0;
        },
        rowTotal(designId) {
            const row = this.qtys[designId] || {};
            return ['xs','s','m','l','xl'].reduce((sum, s) => sum + (row[s] || 0), 0);
        }
     }">

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Outsourced Batch Arrival</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('outsourced-batches.store') }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                <input type="hidden" name="catalogue_id" value="{{ $catalogue->id }}">
                <div class="flex items-center gap-2.5 px-4 py-3 bg-[#F5F5F7] border border-[#E8E8ED] rounded-xl">
                    <span class="font-semibold text-[#1D1D1F]">{{ $catalogue->name }}</span>
                    <span class="text-xs text-[#86868B]">· selected from sidebar</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Received Date</label>
                <input type="date" name="received_date" value="{{ old('received_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Notes <span class="font-normal normal-case">(optional)</span></label>
                <textarea name="notes" rows="2" class="apple-input" placeholder="e.g. Supplier name, quality notes...">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Pieces per design per size --}}
        <template x-if="designs.length > 0">
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-[#F2F2F7]">
                    <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces Received — by Design & Size</h3>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Enter how many pieces arrived per size for each outsourced design</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full apple-table">
                        <thead>
                            <tr>
                                <th class="text-left">Design</th>
                                <th class="text-center">XS</th>
                                <th class="text-center">S</th>
                                <th class="text-center">M</th>
                                <th class="text-center">L</th>
                                <th class="text-center">XL</th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(design, idx) in designs" :key="design.id">
                                <tr>
                                    <td>
                                        <input type="hidden" :name="`items[${idx}][design_id]`" :value="design.id">
                                        <span class="text-sm font-medium text-[#1D1D1F]" x-text="design.name"></span>
                                    </td>
                                    <template x-for="size in ['xs','s','m','l','xl']" :key="size">
                                        <td class="text-center">
                                            <input type="number"
                                                   :name="`items[${idx}][${size}]`"
                                                   min="0"
                                                   value="0"
                                                   @input="setQty(design.id, size, $event.target.value)"
                                                   class="apple-input text-center w-20 mx-auto">
                                        </td>
                                    </template>
                                    <td class="text-center font-bold text-[#0071E3]" x-text="rowTotal(design.id)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <template x-if="selectedCatalogueId && designs.length === 0">
            <div class="card p-8 text-center text-[#86868B] text-sm">
                No outsourced designs found in this catalogue.
            </div>
        </template>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary" x-bind:disabled="designs.length === 0">Save Batch</button>
            <a href="{{ route('outsourced-batches.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
