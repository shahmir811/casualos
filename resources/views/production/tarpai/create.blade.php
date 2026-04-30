@extends('layouts.app')
@section('title', 'Log Tarpai Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('tarpai-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Tarpai</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Send</span>
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
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Send Date</label>
                <input type="date" name="sent_date" value="{{ old('sent_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Per Piece Rate (Rs.)</label>
                <input type="number" name="per_piece_price" value="{{ old('per_piece_price') }}" step="0.01" min="0" class="apple-input" placeholder="e.g. 30" required>
            </div>

            {{-- Pieces by size --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1">Pieces Sent (by Size)</label>
                <p class="text-[#86868B] text-xs mb-3">Enter the quantity for each size being sent for tarpai finishing.</p>
                <div class="grid grid-cols-5 gap-3">
                    @foreach(['xs','s','m','l','xl'] as $i => $size)
                    <div>
                        <label class="block text-xs font-semibold text-[#86868B] uppercase tracking-widest mb-1.5 text-center">{{ strtoupper($size) }}</label>
                        <input type="hidden" name="items[{{ $i }}][size]" value="{{ $size }}">
                        <input type="number" name="items[{{ $i }}][qty]" value="{{ old("items.{$i}.qty", 0) }}" min="0" class="apple-input text-center px-1">
                    </div>
                    @endforeach
                </div>
                <div class="mt-3 text-right text-sm text-[#6E6E73]"
                     x-data
                     x-init="
                        const inputs = $el.closest('.card').querySelectorAll('input[name*=\'[qty]\']');
                        const span   = $el.querySelector('span');
                        const update = () => { let t=0; inputs.forEach(i => t += parseInt(i.value)||0); span.textContent = t; };
                        inputs.forEach(i => i.addEventListener('input', update));
                        update();
                     ">
                    Total pieces: <span class="font-semibold text-[#1D1D1F]">0</span>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save Send</button>
            <a href="{{ route('tarpai-sends.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
