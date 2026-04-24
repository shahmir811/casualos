@extends('layouts.app')
@section('title', 'Log Outsourced Batch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('outsourced-batches.index') }}" class="text-[#0066CC] hover:underline text-sm">Outsourced Batches</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Batch</span>
</div>

<div class="max-w-2xl"
     x-data="{
        selectedCatalogueId: '',
        catalogues: {{ Js::from($catalogues) }},
        get designs() {
            const cat = this.catalogues.find(c => c.id == this.selectedCatalogueId);
            return cat ? cat.designs : [];
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
                <select name="catalogue_id" x-model="selectedCatalogueId" class="apple-input" required>
                    <option value="">— Select catalogue —</option>
                    @foreach($catalogues as $cat)
                    <option value="{{ $cat->id }}" {{ old('catalogue_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
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

        {{-- Pieces per design --}}
        <template x-if="designs.length > 0">
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-[#F2F2F7]">
                    <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces per Design</h3>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Enter total pieces received per design</p>
                </div>
                <div class="divide-y divide-[#F2F2F7]">
                    <template x-for="(design, idx) in designs" :key="design.id">
                        <div class="flex items-center gap-4 px-5 py-3">
                            <input type="hidden" :name="`items[${idx}][design_id]`" :value="design.id">
                            <span class="flex-1 text-sm text-[#1D1D1F]" x-text="design.name"></span>
                            <div class="w-32">
                                <input type="number" :name="`items[${idx}][total_pieces]`" min="0" value="0" class="apple-input text-center">
                            </div>
                            <span class="text-xs text-[#86868B] w-8">pcs</span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary" x-bind:disabled="designs.length === 0">Save Batch</button>
            <a href="{{ route('outsourced-batches.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
