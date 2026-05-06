@extends('layouts.app')

@section('title', 'Edit Catalogue')

@section('content')

<div class="mb-7">
    <a href="{{ route('catalogues.show', $catalogue) }}" class="text-[#0066CC] text-sm hover:underline">← {{ $catalogue->name }}</a>
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Edit Catalogue</h1>
</div>

<div class="max-w-2xl">
    <form method="POST" action="{{ route('catalogues.update', $catalogue) }}" enctype="multipart/form-data" class="card p-7 space-y-5">
        @csrf
        @method('PUT')

        {{-- Name --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Catalogue Name <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="name" value="{{ old('name', $catalogue->name) }}" required
                class="apple-input">
            @error('name')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Cover Photo --}}
        <div x-data="{ preview: null }">
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Cover Photo</label>

            {{-- Current photo (shown when no new file selected) --}}
            @if($catalogue->cover_photo)
            <div x-show="!preview" class="mb-3">
                <img src="{{ Storage::url($catalogue->cover_photo) }}" alt="Current cover"
                    class="w-32 h-32 object-cover rounded-xl border border-[#E8E8ED]">
                <p class="text-[#86868B] text-xs mt-1">Current cover — upload a new image to replace it</p>
            </div>
            @endif

            {{-- New file preview --}}
            <div x-show="preview" class="mb-3">
                <img :src="preview" alt="New cover preview"
                    class="w-32 h-32 object-cover rounded-xl border border-[#0071E3] shadow-sm">
                <p class="text-[#0071E3] text-xs mt-1 font-medium">New image selected — will replace current on save</p>
            </div>

            <input type="file" name="cover_photo" accept="image/*"
                class="apple-input file:bg-[#0071E3] file:border-0 file:text-white file:text-xs file:px-3 file:py-1 file:rounded-full file:mr-3 file:cursor-pointer cursor-pointer"
                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
            @error('cover_photo')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Qty Per Design + Number of Designs --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">
                    Quantity Per Design <span class="text-[#FF3B30]">*</span>
                </label>
                <input type="number" name="qty_per_design" value="{{ old('qty_per_design', $catalogue->qty_per_design) }}" required min="1"
                    class="apple-input">
                <p class="mt-1 text-[#86868B] text-xs">Pieces manufactured from each design</p>
                @error('qty_per_design')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Number of Designs <span class="text-[#FF3B30]">*</span></label>
                <input type="number" name="number_of_designs" value="{{ old('number_of_designs', $catalogue->number_of_designs) }}" required min="1"
                    class="apple-input">
                <p class="mt-1 text-[#86868B] text-xs">Total pieces = qty/design × no. of designs</p>
                @error('number_of_designs')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Discount Benchmark --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Discount Benchmark (Qty)</label>
            <input type="number" name="quantity_benchmark" value="{{ old('quantity_benchmark', $catalogue->quantity_benchmark) }}" min="1"
                class="apple-input">
            <p class="mt-1 text-[#86868B] text-xs">Orders above this quantity get discount prices</p>
            @error('quantity_benchmark')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Notes</label>
            <textarea name="notes" rows="3" class="apple-input resize-none">{{ old('notes', $catalogue->notes) }}</textarea>
        </div>

        {{-- Submit + Delete --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">
                Save Changes
            </button>
            <a href="{{ route('catalogues.show', $catalogue) }}" class="btn-secondary">
                Cancel
            </a>

            @if(!$catalogue->orders()->exists())
            <form method="POST" action="{{ route('catalogues.destroy', $catalogue) }}" class="ml-auto">
                @csrf @method('DELETE')
                <button type="submit" onclick="return confirm('Permanently delete this catalogue and all its designs?')"
                    class="btn-danger">
                    Delete Catalogue
                </button>
            </form>
            @endif
        </div>
    </form>
</div>

@endsection
