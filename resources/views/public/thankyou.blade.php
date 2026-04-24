<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed — Casualite</title>
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

        {{-- Success Icon --}}
        <div class="w-20 h-20 bg-[#30D158] rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
            <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-2xl font-semibold text-[#1D1D1F] tracking-tight mb-3">Order Placed!</h1>
        <p class="text-[#6E6E73] text-base mb-2">
            Thank you for your order. The Casualite team will review it and confirm shortly.
        </p>
        <p class="text-[#86868B] text-sm mb-8">
            You'll be contacted on WhatsApp once your order is confirmed.
        </p>

        @if($order)
        <div class="bg-white border border-[#E8E8ED] rounded-2xl p-5 text-left mb-8">
            <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-3">Order Summary</p>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Order ID</span>
                    <span class="text-[#1D1D1F] font-medium">#{{ $order->id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Catalogue</span>
                    <span class="text-[#1D1D1F]">{{ $catalogue->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Total Amount</span>
                    <span class="text-[#1D1D1F] font-semibold">PKR {{ number_format($order->total_amount, 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Status</span>
                    <span class="text-[#FF9500] font-medium">Received — Pending Confirmation</span>
                </div>
            </div>
        </div>
        @endif

        <p class="text-[#86868B] text-sm">
            Questions? Reach us on WhatsApp or contact your sales representative.
        </p>

    </div>

</body>
</html>
