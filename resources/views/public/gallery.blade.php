<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $catalogue->name }} — HD Gallery</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #F5F5F7;
        }
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
                 style="break-inside:avoid;">

                <img src="{{ Storage::url($image->thumbnail_path ?? $image->s3_path) }}" loading="lazy" class="w-full h-auto block">

                <div class="p-3 flex items-center justify-between gap-3">
                    <span class="text-xs text-[#6E6E73] truncate">{{ $image->original_filename }}</span>
                    <a href="{{ route('gallery.download', [$catalogue->hd_gallery_token, $image]) }}"
                       download="{{ $image->original_filename }}"
                       class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium text-white"
                       style="background:#0071E3;">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                        </svg>
                        <span>Download</span>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @endif

    </main>

</body>
</html>
