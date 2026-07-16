<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $catalogue->name }} — HD Gallery</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #F5F5F7;
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen">

    <header class="bg-white border-b border-[#E8E8ED] sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-5 py-4 flex items-center justify-between">
            <div>
                <p class="text-[10px] font-semibold text-[#86868B] tracking-widest uppercase">Casualite</p>
                <h1 class="text-lg font-semibold text-[#1D1D1F] tracking-tight">{{ $catalogue->name }} — HD Gallery</h1>
            </div>
            <span class="text-xs text-[#86868B]">{{ $catalogue->hdImages->count() }} image{{ $catalogue->hdImages->count() === 1 ? '' : 's' }}</span>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-5 py-8">

        @if($catalogue->hdImages->isEmpty())
        <div class="bg-white border border-[#E8E8ED] rounded-2xl p-12 text-center">
            <p class="text-[#6E6E73] text-sm">No HD images have been uploaded for this catalogue yet.</p>
        </div>
        @else
        <div class="columns-1 sm:columns-2 lg:columns-3 gap-4" style="column-gap:1rem;">
            @foreach($catalogue->hdImages as $image)
            <div class="relative rounded-2xl overflow-hidden bg-white border border-[#E8E8ED] mb-4"
                 style="break-inside:avoid;"
                 x-data="galleryDownloader()">

                <img src="{{ Storage::url($image->thumbnail_path ?? $image->s3_path) }}" loading="lazy" class="w-full h-auto block">

                <div class="p-3 flex items-center justify-between gap-3">
                    <span class="text-xs text-[#6E6E73] truncate">{{ $image->original_filename }}</span>
                    <button type="button"
                            :disabled="downloading"
                            @click="download('{{ route('gallery.download', [$catalogue->hd_gallery_token, $image]) }}', '{{ addslashes($image->original_filename) }}')"
                            class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium text-white"
                            :class="downloading ? 'opacity-70' : ''"
                            style="background:#0071E3;">
                        <svg x-show="!downloading" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        <span x-show="!downloading">Download</span>
                        <span x-show="downloading" x-text="progress + '%'"></span>
                    </button>
                </div>

                <div x-show="downloading" x-cloak class="h-1 bg-[#F2F2F7]">
                    <div class="h-full bg-[#0071E3] transition-all duration-150" :style="`width:${progress}%`"></div>
                </div>

                <p x-show="error" x-cloak class="px-3 pb-2 text-[10px] text-[#FF3B30]" x-text="error"></p>
            </div>
            @endforeach
        </div>
        @endif

    </main>

    <script>
        function galleryDownloader() {
            return {
                downloading: false,
                progress: 0,
                error: '',
                async download(url, filename) {
                    this.downloading = true;
                    this.progress = 0;
                    this.error = '';
                    try {
                        const res = await fetch(url);
                        if (!res.ok) throw new Error('Download failed.');

                        const total = parseInt(res.headers.get('Content-Length') || '0', 10);
                        const reader = res.body.getReader();
                        const chunks = [];
                        let received = 0;

                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) break;
                            chunks.push(value);
                            received += value.length;
                            if (total) this.progress = Math.round((received / total) * 100);
                        }

                        const blob = new Blob(chunks);
                        const blobUrl = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = blobUrl;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        URL.revokeObjectURL(blobUrl);
                        this.progress = 100;
                    } catch (e) {
                        this.error = e.message || 'Download failed. Please try again.';
                    } finally {
                        setTimeout(() => { this.downloading = false; }, 500);
                    }
                },
            };
        }
    </script>

</body>
</html>
