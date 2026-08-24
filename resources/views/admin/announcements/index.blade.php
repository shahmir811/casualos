@extends('layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Announcements</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Post an update to the customer app's Timeline. Every customer gets a push notification.</p>
</div>

<div class="max-w-3xl mx-auto">

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

{{-- Compose — styled like a tweet composer --}}
<div class="card p-4 mb-5">
    <form id="announcement-form" method="POST" action="{{ route('announcements.store') }}"
          enctype="multipart/form-data"
          x-data="{
              previews: [],
              onFiles(e) { this.previews = Array.from(e.target.files).map(f => URL.createObjectURL(f)); },
              clearAll() { this.previews = []; $refs.imageInput.value = ''; }
          }"
          class="flex gap-3">
        @csrf

        <div class="w-11 h-11 rounded-full bg-[#0071E3] flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="28" cy="28" r="18" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="18" x2="36" y2="46" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="46" x2="50" y2="46" stroke="white" stroke-width="1.5"/>
            </svg>
        </div>

        <div class="flex-1 min-w-0">
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                class="w-full border-0 p-0 text-[17px] font-semibold text-[#1D1D1F] placeholder-[#AEAEB2] focus:ring-0 focus:outline-none"
                placeholder="Headline — e.g. New Catalogue: ISHQIA">
            @error('title')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            <textarea name="body" required rows="2"
                class="w-full border-0 p-0 mt-1.5 text-[15px] text-[#1D1D1F] placeholder-[#AEAEB2] resize-none focus:ring-0 focus:outline-none"
                placeholder="What's happening at Casual Lite?"
                oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            {{-- Image previews --}}
            <div x-show="previews.length" x-cloak class="relative mt-3">
                <div class="grid gap-2" :class="previews.length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
                    <template x-for="(src, i) in previews" :key="i">
                        <img :src="src" alt="Preview"
                            class="w-full object-cover rounded-2xl border border-[#E8E8ED]"
                            :class="previews.length === 1 ? 'max-h-64' : 'h-40'">
                    </template>
                </div>
                <button type="button" @click="clearAll()"
                    class="absolute top-2 right-2 w-7 h-7 rounded-full bg-[#1D1D1F]/70 text-white flex items-center justify-center hover:bg-[#1D1D1F]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            @error('images')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
            @error('images.*')
                <p class="mt-1 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror

            <div class="flex items-center justify-between mt-3 pt-3 border-t border-[#F2F2F7]">
                <label class="w-9 h-9 rounded-full flex items-center justify-center text-[#0071E3] hover:bg-[#0071E3]/10 cursor-pointer transition-colors" title="Add images (optional, max 10MB each)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                        <circle cx="8.5" cy="8.5" r="1.5" />
                    </svg>
                    <input type="file" name="images[]" accept="image/*" multiple x-ref="imageInput" class="hidden"
                        @change="onFiles($event)">
                </label>

                {{--
                    The Send button is type="button" and submits through the global
                    confirm modal rather than a plain type="submit" — this form (with
                    its title/body/image fields) is what actually gets submitted, so
                    formId points at it directly. Every other use of $store.confirm in
                    this codebase submits a separate hidden no-field form instead,
                    since proceed() just calls formId.submit() with no knowledge of
                    field contents — but this send genuinely needs the real form's
                    data, so pointing at it directly is correct here.
                --}}
                <button type="button" class="btn-primary rounded-full px-5 py-2 text-sm"
                        @click="$store.confirm.show({
                            title: 'Send Announcement',
                            message: 'Send this to every customer? This cannot be undone.',
                            formId: 'announcement-form',
                            confirmText: 'Send to All Customers'
                        })">
                    Post to Timeline
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Timeline --}}
<div class="card divide-y divide-[#F2F2F7] overflow-hidden">
    @forelse($announcements as $announcement)
    <div class="p-4 flex gap-3 hover:bg-[#FAFAFA] transition-colors">
        <div class="w-11 h-11 rounded-full bg-[#0071E3] flex items-center justify-center flex-shrink-0">
            <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="28" cy="28" r="18" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="18" x2="36" y2="46" stroke="white" stroke-width="1.5"/>
                <line x1="36" y1="46" x2="50" y2="46" stroke="white" stroke-width="1.5"/>
            </svg>
        </div>

        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-1.5 text-[15px] flex-wrap">
                <span class="font-semibold text-[#1D1D1F]">Casual Lite</span>
                <span class="text-[#86868B]">·</span>
                <span class="text-[#86868B]" title="{{ $announcement->sent_at->format('M j, Y g:i A') }}">{{ $announcement->sent_at->diffForHumans() }}</span>
                <span class="text-[#86868B]">·</span>
                <span class="text-[#86868B] text-sm">by {{ $announcement->sentBy?->name ?? '—' }}</span>
            </div>

            <p class="font-semibold text-[#1D1D1F] text-[15px] leading-snug mt-0.5">{{ $announcement->title }}</p>
            <p class="text-[#1D1D1F] text-[15px] leading-relaxed mt-0.5 whitespace-pre-wrap">{{ $announcement->body }}</p>

            @if(!empty($announcement->image_paths))
            <div class="mt-3 grid gap-1 rounded-2xl overflow-hidden border border-[#E8E8ED] {{ count($announcement->image_paths) === 1 ? 'grid-cols-1' : 'grid-cols-2' }}">
                @foreach($announcement->image_paths as $path)
                <img src="{{ Storage::url($path) }}" alt=""
                     class="w-full object-cover {{ count($announcement->image_paths) === 1 ? 'max-h-96' : 'h-44' }}">
                @endforeach
            </div>
            @endif

            <div class="flex items-center gap-1.5 mt-3 text-[#86868B] text-xs">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8zm6 3.13a4 4 0 010 7.75M6 20.13a4 4 0 010-7.75" />
                </svg>
                <span>{{ number_format($announcement->recipient_count) }} customers notified</span>
            </div>
        </div>
    </div>
    @empty
    <div class="text-center text-[#86868B] py-16 text-sm">No announcements sent yet.</div>
    @endforelse
</div>

<div class="mt-5">{{ $announcements->links() }}</div>

</div>

@endsection
