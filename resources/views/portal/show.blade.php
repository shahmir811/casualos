<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal — Casual Lite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #F5F5F7;
        }
        .form-input {
            width: 100%;
            background: #F5F5F7;
            border: 1.5px solid transparent;
            border-radius: 10px;
            color: #1D1D1F;
            font-size: 0.9375rem;
            padding: 0.75rem 1rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        }
        .form-input:focus {
            background: #fff;
            border-color: #0071E3;
            box-shadow: 0 0 0 3px rgba(0,113,227,0.12);
        }
        .form-input::placeholder { color: #AEAEB2; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-12">

    <div class="w-full max-w-sm">

        {{-- Logo / Brand --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-[#1D1D1F] rounded-2xl mb-4 shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Customer Portal</h1>
            <p class="text-[#6E6E73] text-sm mt-1">Verify your identity to view your orders</p>
        </div>

        {{-- Error --}}
        @if(session('error'))
        <div class="mb-4 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl px-4 py-3 text-[#FF3B30] text-sm">
            {{ session('error') }}
        </div>
        @endif

        {{-- Verification Card --}}
        <div class="bg-white rounded-2xl shadow-sm border border-[#E8E8ED] px-6 py-7">

            <p class="text-[#1D1D1F] text-sm font-medium mb-1">Welcome back</p>
            <p class="text-[#6E6E73] text-xs mb-5 leading-relaxed">
                Enter the email address registered with your account to continue.
            </p>

            <form method="POST" action="{{ route('portal.verify', $customer->portal_token) }}">
                @csrf
                <div class="mb-5">
                    <label class="block text-[#1D1D1F] text-xs font-semibold mb-2 uppercase tracking-wider">
                        Email Address
                    </label>
                    <input type="email"
                        name="email"
                        class="form-input"
                        placeholder="you@example.com"
                        autocomplete="email"
                        required>
                </div>

                <button type="submit"
                    class="w-full bg-[#1D1D1F] text-white font-semibold text-sm rounded-xl py-3 hover:bg-[#3A3A3C] transition-colors active:scale-[0.985] transform">
                    Verify &amp; View Orders
                </button>
            </form>
        </div>

        <p class="text-center text-[#C7C7CC] text-xs mt-6">
            © {{ date('Y') }} Casual Lite · Powered by CasualOS
        </p>

    </div>

</body>
</html>
