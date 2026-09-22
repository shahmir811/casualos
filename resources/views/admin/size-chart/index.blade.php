@extends('layouts.app')
@section('title', 'Size Chart')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Size Chart</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Internal reference image — admin-only, not shown to customers</p>
    </div>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-sm" style="background:#FFF0EF; color:#FF3B30; border:1px solid #FFCDD0;">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card p-5" x-data="{ fileChosen: false, lightboxOpen: false }">
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-3">Current Size Chart</p>

    @if($sizeChart->image_path)
    <div class="flex items-start gap-5 flex-wrap mb-5">
        <img src="{{ Storage::url($sizeChart->image_path) }}" alt="Size Chart"
             class="w-full max-w-sm rounded-xl border border-[#E8E8ED] object-contain cursor-zoom-in"
             @click="lightboxOpen = true">
        <div class="min-w-0">
            <p class="text-[#1D1D1F] text-sm font-medium truncate">{{ $sizeChart->original_filename }}</p>
            <p class="text-[#86868B] text-xs mt-1">
                @if($sizeChart->file_size)
                    {{ number_format($sizeChart->file_size / 1024, 0) }} KB
                @endif
                @if($sizeChart->uploadedBy)
                    · uploaded by {{ $sizeChart->uploadedBy->name }}
                @endif
                @if($sizeChart->uploaded_at)
                    · {{ $sizeChart->uploaded_at->format('d M Y') }}
                @endif
            </p>

            <form id="form-delete-size-chart" method="POST" action="{{ route('size-chart.destroy') }}" class="hidden">
                @csrf @method('DELETE')
            </form>
            <button type="button" class="text-[#FF3B30] text-xs font-medium mt-3"
                    @click="$store.confirm.show({
                        title: 'Delete Size Chart',
                        message: 'This will permanently remove the current size chart image.',
                        formId: 'form-delete-size-chart',
                        confirmText: 'Delete',
                        danger: true
                    })">
                Delete
            </button>
        </div>
    </div>
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-3">Replace Size Chart</p>
    @else
    <p class="text-[#86868B] text-sm mb-5">No size chart uploaded yet.</p>
    @endif

    <form method="POST" action="{{ route('size-chart.store') }}" enctype="multipart/form-data" class="flex items-end gap-3 flex-wrap">
        @csrf
        <div class="flex-1 min-w-[240px]">
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                Image
            </label>
            <input type="file" name="image" accept="image/jpeg,image/jpg,image/png,image/webp" required
                   @change="fileChosen = !!$event.target.files.length"
                   class="apple-input">
            <p class="text-[#86868B] text-xs mt-2">JPG, PNG or WebP, up to 10MB.</p>
        </div>
        <div class="flex-shrink-0 pb-px">
            <button type="submit" class="btn-primary" :disabled="!fileChosen" :class="{ 'opacity-50 cursor-not-allowed': !fileChosen }">
                {{ $sizeChart->image_path ? 'Replace' : 'Upload' }}
            </button>
        </div>
    </form>

    @if($sizeChart->image_path)
    {{-- Image lightbox --}}
    <div x-show="lightboxOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
         @click.self="lightboxOpen = false"
         @keydown.escape.window="lightboxOpen = false">
        <div class="relative max-w-3xl max-h-[90vh] mx-4">
            <img src="{{ Storage::url($sizeChart->image_path) }}" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl" alt="Size Chart">
            <button type="button" @click="lightboxOpen = false"
                    class="absolute -top-3 -right-3 w-8 h-8 bg-white text-[#1D1D1F] rounded-full flex items-center justify-center shadow-lg hover:bg-[#F5F5F7] transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>
    @endif
</div>

@endsection
