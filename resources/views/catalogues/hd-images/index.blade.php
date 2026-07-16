@extends('layouts.app')

@section('title', 'HD Gallery — ' . $catalogue->name)

@section('content')

<div x-data="hdGalleryUploader({
        presignUrl: '{{ route('catalogues.hd-images.presign', $catalogue) }}',
        storeUrl: '{{ route('catalogues.hd-images.store', $catalogue) }}',
        csrfToken: '{{ csrf_token() }}',
     })">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-7">
        <div>
            <a href="{{ route('catalogues.show', $catalogue) }}" class="text-[#0066CC] text-sm hover:underline">← {{ $catalogue->name }}</a>
            <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">HD Gallery</h1>
            <p class="text-[#6E6E73] text-xs mt-1">{{ $catalogue->hdImages->count() }} image{{ $catalogue->hdImages->count() === 1 ? '' : 's' }}</p>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
        {{ session('success') }}
    </div>
    @endif

    {{-- Shareable Link --}}
    <div class="card p-5 mb-7" x-data="{ copied: false }">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-3">Shareable Gallery Link</p>
        <div class="flex items-center gap-3">
            <input type="text" value="{{ $shareUrl }}" readonly class="apple-input text-xs flex-1 text-[#6E6E73]">
            <button @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="btn-primary flex-shrink-0">
                <span x-show="!copied">Copy Link</span>
                <span x-show="copied">Copied ✓</span>
            </button>
        </div>
        <p class="text-[#86868B] text-xs mt-2">Share this link with customers who want the full-resolution photos.</p>
    </div>

    {{-- Drop Zone --}}
    <div class="card p-10 mb-7 text-center border-2 border-dashed transition-colors"
         :class="dragging ? 'border-[#0071E3]' : 'border-[#D2D2D7]'"
         :style="dragging ? 'background:#F0F7FF' : ''"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="dragging = false; onFiles($event.dataTransfer.files)"
         @click="$refs.fileInput.click()"
         style="cursor:pointer;">
        <input type="file" x-ref="fileInput" multiple accept="image/jpeg,image/png" class="hidden"
               @change="onFiles($event.target.files); $event.target.value = ''">
        <svg class="w-8 h-8 mx-auto text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
        </svg>
        <p class="text-[#1D1D1F] text-sm font-medium mt-3">Drop HD images here, or click to browse</p>
        <p class="text-[#86868B] text-xs mt-1">JPG or PNG, up to 1GB per file · select multiple at once</p>
    </div>

    {{-- Upload Queue --}}
    <div class="card p-5 mb-7" x-show="queue.length > 0" x-cloak>
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-3">
            Uploading <span x-text="queue.filter(i => i.status === 'done').length"></span> / <span x-text="queue.length"></span>
            <span x-show="allDone" class="text-[#15803D]">— refreshing…</span>
        </p>
        <div class="space-y-3">
            <template x-for="item in queue" :key="item.id">
                <div class="flex items-center gap-3">
                    <img :src="item.preview" class="w-10 h-10 rounded-lg object-cover flex-shrink-0 border border-[#F2F2F7]">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-[#1D1D1F] truncate" x-text="item.name"></p>
                        <div class="h-1.5 bg-[#F2F2F7] rounded-full mt-1.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-200"
                                 :class="item.status === 'error' ? 'bg-[#FF3B30]' : 'bg-[#0071E3]'"
                                 :style="`width:${item.status === 'done' ? 100 : item.progress}%`"></div>
                        </div>
                        <p x-show="item.status === 'error'" class="text-[10px] text-[#FF3B30] mt-1" x-text="item.error"></p>
                    </div>
                    <span class="text-xs flex-shrink-0 w-20 text-right"
                          :class="item.status === 'error' ? 'text-[#FF3B30]' : 'text-[#86868B]'"
                          x-text="item.status === 'error' ? 'Failed' : (item.status === 'done' ? 'Done ✓' : (item.status === 'saving' ? 'Finalizing…' : Math.round(item.progress) + '%'))"></span>
                    <button type="button" x-show="item.status === 'error'" @click="retry(item)"
                            class="text-xs text-[#0066CC] font-medium flex-shrink-0">Retry</button>
                </div>
            </template>
        </div>
    </div>

    {{-- Gallery Grid --}}
    @if($catalogue->hdImages->isEmpty())
    <div class="card p-12 text-center">
        <p class="text-[#6E6E73] text-sm">No HD images uploaded yet.</p>
    </div>
    @else
    <div class="columns-2 sm:columns-3 lg:columns-4 gap-4" style="column-gap:1rem;">
        @foreach($catalogue->hdImages as $image)
        <div class="relative group rounded-xl overflow-hidden border border-[#E8E8ED] mb-4" style="break-inside:avoid;">
            <img src="{{ Storage::url($image->thumbnail_path ?? $image->s3_path) }}" loading="lazy" class="w-full h-auto block">

            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition-colors flex items-end justify-between p-3 opacity-0 group-hover:opacity-100">
                <span class="text-white text-xs truncate pr-2">{{ $image->original_filename }}</span>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ Storage::url($image->s3_path) }}" target="_blank"
                       class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center hover:bg-white">
                        <svg class="w-3.5 h-3.5 text-[#1D1D1F]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </a>

                    <form id="delete-hd-{{ $image->id }}" method="POST" action="{{ route('catalogues.hd-images.destroy', [$catalogue, $image]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                    <button type="button"
                            @click="$store.confirm.show({
                                title: 'Delete Image',
                                message: 'This will permanently delete {{ addslashes($image->original_filename) }} from the gallery.',
                                formId: 'delete-hd-{{ $image->id }}',
                                confirmText: 'Delete',
                                danger: true
                            })"
                            class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center hover:bg-white">
                        <svg class="w-3.5 h-3.5 text-[#FF3B30]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
function hdGalleryUploader({ presignUrl, storeUrl, csrfToken }) {
    return {
        queue: [],
        dragging: false,
        allDone: false,

        onFiles(fileList) {
            Array.from(fileList).forEach(file => {
                if (!['image/jpeg', 'image/png'].includes(file.type)) return;
                if (file.size > 1073741824) return;

                this.queue.push({
                    id: crypto.randomUUID(),
                    file,
                    name: file.name,
                    preview: URL.createObjectURL(file),
                    progress: 0,
                    status: 'queued',
                    error: '',
                });
            });

            this.pump();
        },

        activeCount() {
            return this.queue.filter(i => i.status === 'uploading' || i.status === 'saving').length;
        },

        pump() {
            while (this.activeCount() < 3) {
                const next = this.queue.find(i => i.status === 'queued');
                if (!next) break;
                this.processItem(next);
            }
        },

        async processItem(item) {
            item.status = 'uploading';
            try {
                const uuid = item.id;

                // Best-effort client-side thumbnail — original still uploads if this fails
                let thumbBlob = null;
                try {
                    const bitmap = await createImageBitmap(item.file);
                    const maxDim = 700;
                    const scale = Math.min(1, maxDim / Math.max(bitmap.width, bitmap.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
                    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
                    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                    thumbBlob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.78));
                } catch (e) {
                    thumbBlob = null;
                }

                const originalPresign = await this.presign(uuid, 'original', item.file.type);
                await this.putToS3(originalPresign, item.file, pct => {
                    item.progress = Math.round(pct * (thumbBlob ? 0.9 : 1));
                });

                if (thumbBlob) {
                    const thumbPresign = await this.presign(uuid, 'thumbnail', 'image/jpeg');
                    await this.putToS3(thumbPresign, thumbBlob, () => {});
                }

                item.progress = 100;
                item.status = 'saving';

                const res = await fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        uuid,
                        original_filename: item.file.name,
                        content_type: item.file.type,
                    }),
                });

                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    throw new Error(body.message || 'Could not save image.');
                }

                item.status = 'done';
            } catch (e) {
                item.status = 'error';
                item.error = e.message || 'Upload failed.';
            } finally {
                this.pump();
                this.checkAllDone();
            }
        },

        async presign(uuid, kind, contentType) {
            const res = await fetch(presignUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ uuid, kind, content_type: contentType }),
            });
            if (!res.ok) throw new Error('Could not get an upload URL.');
            return res.json();
        },

        putToS3({ url, headers }, blob, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', url);
                Object.entries(headers || {}).forEach(([k, v]) => xhr.setRequestHeader(k, v));
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) onProgress((e.loaded / e.total) * 100);
                };
                xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload to storage failed.'));
                xhr.onerror = () => reject(new Error('Upload to storage failed.'));
                xhr.send(blob);
            });
        },

        retry(item) {
            item.status = 'queued';
            item.progress = 0;
            item.error = '';
            this.pump();
        },

        checkAllDone() {
            if (this.queue.length > 0 && this.queue.every(i => i.status === 'done')) {
                this.allDone = true;
                setTimeout(() => window.location.reload(), 700);
            }
        },
    };
}
</script>
@endpush
