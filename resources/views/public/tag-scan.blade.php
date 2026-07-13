<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casualite Tag</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
    </style>
</head>
<body class="min-h-screen bg-[#F5F5F7] flex items-center justify-center px-4">

    <div class="w-full max-w-md text-center">

        <h1 class="text-2xl font-semibold text-[#1D1D1F] tracking-tight mb-6">Casualite</h1>

        @if($tag)
        <div class="bg-white border border-[#E8E8ED] rounded-2xl p-5 text-left">
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Customer</span>
                    <span class="text-[#1D1D1F] font-medium">{{ $tag->order->customer->name ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Catalogue</span>
                    <span class="text-[#1D1D1F] font-medium">{{ $tag->order->catalogue->name ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Design</span>
                    <span class="text-[#1D1D1F] font-medium">{{ $tag->design->name ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Size</span>
                    <span class="text-[#1D1D1F] font-medium">{{ strtoupper($tag->size) }}</span>
                </div>
            </div>
        </div>
        @else
        <div class="bg-white border border-[#E8E8ED] rounded-2xl p-5 text-[#6E6E73] text-sm">
            Tag not found.
        </div>
        @endif

    </div>

</body>
</html>
