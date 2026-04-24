@extends('layouts.app')

@section('title', 'Edit Design')

@section('content')

<div class="mb-7">
    <a href="{{ route('catalogues.show', $design->catalogue) }}" class="text-[#0066CC] text-sm hover:underline">← {{ $design->catalogue->name }}</a>
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Edit Design</h1>
    <p class="text-[#6E6E73] text-sm mt-1">{{ $design->name }}</p>
</div>

<div class="max-w-2xl">
    <form method="POST" action="{{ route('designs.update', $design) }}" enctype="multipart/form-data" class="card p-7 space-y-5">
        @csrf
        @method('PUT')

        {{-- Name --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Design Name <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="name" value="{{ old('name', $design->name) }}" required
                class="apple-input">
            @error('name')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Photo --}}
        <div x-data="{ preview: null }">
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Design Photo</label>

            {{-- Current photo --}}
            @if($design->photo)
            <div x-show="!preview" class="mb-3">
                <img src="{{ Storage::url($design->photo) }}" alt="{{ $design->name }}"
                    class="w-36 h-36 object-cover rounded-xl border border-[#E8E8ED]">
                <p class="text-[#86868B] text-xs mt-1">Current photo — upload a new one to replace it</p>
            </div>
            @endif

            {{-- New file preview --}}
            <div x-show="preview" class="mb-3">
                <img :src="preview" alt="New photo preview"
                    class="w-36 h-36 object-cover rounded-xl border border-[#0071E3] shadow-sm">
                <p class="text-[#0071E3] text-xs mt-1 font-medium">New photo selected — will replace current on save</p>
            </div>

            <input type="file" name="photo" accept="image/*"
                class="apple-input file:bg-[#0071E3] file:border-0 file:text-white file:text-xs file:px-3 file:py-1 file:rounded-full file:mr-3 file:cursor-pointer cursor-pointer"
                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
            @error('photo')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Selling Price + Manufacturing Type --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Selling Price (PKR) <span class="text-[#FF3B30]">*</span></label>
                <input type="number" name="selling_price" value="{{ old('selling_price', $design->selling_price) }}" required min="0" step="0.01"
                    class="apple-input">
                @error('selling_price')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Manufacturing Type <span class="text-[#FF3B30]">*</span></label>
                <select name="manufacturing_type" required class="apple-input">
                    <option value="in_house" {{ old('manufacturing_type', $design->manufacturing_type) === 'in_house' ? 'selected' : '' }}>In-House</option>
                    <option value="outsourced" {{ old('manufacturing_type', $design->manufacturing_type) === 'outsourced' ? 'selected' : '' }}>Outsourced</option>
                </select>
                @error('manufacturing_type')
                    <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Sort Order --}}
        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Sort Order</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $design->sort_order) }}" min="0"
                class="apple-input">
            <p class="mt-1 text-[#86868B] text-xs">Lower numbers appear first.</p>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">
                Save Changes
            </button>
            <a href="{{ route('catalogues.show', $design->catalogue) }}" class="btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

@endsection
