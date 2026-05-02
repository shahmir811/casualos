@extends('layouts.app')
@section('title', 'Log Naeem Pakki Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('naeem-pakki-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Naeem Pakki</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Send</span>
</div>

<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Naeem Pakki Send</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($catalogues->isEmpty())
    <div class="card p-8 text-center">
        <p class="text-[#86868B] mb-2">No designs marked for Naeem Pakki work in any open catalogue.</p>
        <p class="text-xs text-[#86868B]">Enable "Requires Naeem Pakki Work" on a design to log sends for it.</p>
    </div>
    @else

    <form method="POST" action="{{ route('naeem-pakki-sends.store') }}" class="space-y-5"
          x-data="{
              selectedCatalogueId: '{{ old('catalogue_id', '') }}',
              selectedDesignId: '{{ old('design_id', '') }}',
              catalogues: {{ Js::from($catalogues) }},
              get designs() {
                  const cat = this.catalogues.find(c => c.id == this.selectedCatalogueId);
                  return cat ? cat.designs : [];
              },
              onCatalogueChange() { this.selectedDesignId = ''; }
          }">
        @csrf

        <div class="card p-6 space-y-5">

            {{-- Catalogue --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                <select name="catalogue_id" x-model="selectedCatalogueId" @change="onCatalogueChange()" class="apple-input" required>
                    <option value="">— Select catalogue —</option>
                    @foreach($catalogues as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Design --}}
            <div x-show="designs.length > 0" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Design</label>
                <select name="design_id" x-model="selectedDesignId" class="apple-input" required>
                    <option value="">— Select design —</option>
                    <template x-for="d in designs" :key="d.id">
                        <option :value="d.id" x-text="d.name"></option>
                    </template>
                </select>
                <p class="mt-1.5 text-xs text-[#86868B]">Only designs marked "Requires Naeem Pakki Work" are shown.</p>
            </div>

            {{-- Send Date --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Send Date</label>
                <input type="date" name="sent_date" value="{{ old('sent_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>

            {{-- Quantity --}}
            <div x-show="selectedDesignId !== ''" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Pieces Sent <span class="text-[#FF3B30]">*</span>
                </label>
                <input type="number" name="quantity" value="{{ old('quantity', 0) }}"
                       min="1" class="apple-input" placeholder="e.g. 50" required>
                <p class="mt-1.5 text-xs text-[#86868B]">Total pieces being sent for embroidery — no size breakdown required at this stage.</p>
            </div>

            {{-- Per Piece Rate --}}
            <div x-show="selectedDesignId !== ''" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Per Piece Rate (Rs.) <span class="text-[#FF3B30]">*</span>
                </label>
                <input type="number" name="per_piece_price" value="{{ old('per_piece_price') }}"
                       step="0.01" min="0" class="apple-input" placeholder="e.g. 150" required>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary" :disabled="selectedDesignId === ''">Save Send</button>
            <a href="{{ route('naeem-pakki-sends.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
    @endif
</div>

@endsection
