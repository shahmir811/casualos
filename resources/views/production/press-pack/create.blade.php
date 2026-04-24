@extends('layouts.app')
@section('title', 'Log Press & Pack')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('press-pack.index') }}" class="text-[#0066CC] hover:underline text-sm">Press & Pack</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Pack</span>
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

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Press & Pack</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('press-pack.store') }}" class="space-y-5">
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

            <div x-show="designs.length > 0">
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Design</label>
                <select name="design_id" class="apple-input" required>
                    <option value="">— Select design —</option>
                    <template x-for="design in designs" :key="design.id">
                        <option :value="design.id" x-text="design.name"></option>
                    </template>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Packed Date</label>
                <input type="date" name="packed_date" value="{{ old('packed_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces Packed by Size</h3>
                <p class="text-xs text-[#6E6E73] mt-0.5">Enter how many pieces were pressed and packed per size</p>
            </div>
            <div class="p-5 grid grid-cols-5 gap-3">
                @foreach(['xs','s','m','l','xl'] as $size)
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2 text-center">{{ strtoupper($size) }}</label>
                    <input type="hidden" name="items[{{ $loop->index }}][size]" value="{{ $size }}">
                    <input type="number" name="items[{{ $loop->index }}][qty]"
                           min="0" value="{{ old("items.{$loop->index}.qty", 0) }}"
                           class="apple-input text-center">
                </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save Record</button>
            <a href="{{ route('press-pack.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
