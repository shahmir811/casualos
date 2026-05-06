@extends('layouts.app')

@section('title', 'New Catalogue')

@section('content')

<div class="mb-7">
    <a href="{{ route('catalogues.index') }}" class="text-[#0066CC] text-sm hover:underline">← Catalogues</a>
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">New Catalogue</h1>
</div>

<div class="max-w-2xl">
    <form method="POST" action="{{ route('catalogues.store') }}" enctype="multipart/form-data" class="card p-7 space-y-5">
        @csrf

        {{-- Name --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Catalogue Name <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                class="apple-input"
                placeholder="e.g. Summer 2025">
            @error('name')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Cover Photo --}}
        <div x-data="{ preview: null }">
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Cover Photo</label>

            {{-- Preview --}}
            <div x-show="preview" class="mb-3">
                <img :src="preview" alt="Preview"
                    class="w-32 h-32 object-cover rounded-xl border border-[#E8E8ED] shadow-sm">
                <p class="text-[#86868B] text-xs mt-1">Preview — this is how it will appear.</p>
            </div>

            <input type="file" name="cover_photo" accept="image/*"
                class="apple-input file:bg-[#0071E3] file:border-0 file:text-white file:text-xs file:px-3 file:py-1 file:rounded-full file:mr-3 file:cursor-pointer cursor-pointer"
                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
            <p class="mt-1 text-[#86868B] text-xs">Recommended: square image, max 10MB</p>
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
                <input type="number" name="qty_per_design" value="{{ old('qty_per_design') }}" required min="1"
                    class="apple-input"
                    placeholder="e.g. 70">
                <p class="mt-1 text-[#86868B] text-xs">Pieces manufactured from each design</p>
                @error('qty_per_design')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Number of Designs <span class="text-[#FF3B30]">*</span></label>
                <input type="number" name="number_of_designs" value="{{ old('number_of_designs') }}" required min="1"
                    class="apple-input"
                    placeholder="e.g. 7">
                <p class="mt-1 text-[#86868B] text-xs">Total pieces = qty/design × no. of designs</p>
                @error('number_of_designs')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Wage Rate + Discount Benchmark --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Stitching Wage Rate (PKR per piece)</label>
                <input type="number" name="wage_rate" value="{{ old('wage_rate') }}" min="0" step="0.01"
                    class="apple-input"
                    placeholder="e.g. 150.00">
                @error('wage_rate')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Discount Benchmark (Qty)</label>
                <input type="number" name="quantity_benchmark" value="{{ old('quantity_benchmark') }}" min="1"
                    class="apple-input"
                    placeholder="e.g. 15">
                <p class="mt-1 text-[#86868B] text-xs">Orders above this quantity get discount prices</p>
                @error('quantity_benchmark')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Notes --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Notes</label>
            <textarea name="notes" rows="3" class="apple-input resize-none"
                placeholder="Internal notes about this catalogue...">{{ old('notes') }}</textarea>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">
                Create Catalogue
            </button>
            <a href="{{ route('catalogues.index') }}" class="btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection
