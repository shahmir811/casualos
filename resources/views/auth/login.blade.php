<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Casualite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .apple-input {
            width: 100%;
            background: #F5F5F7;
            border: 1px solid transparent;
            border-radius: 10px;
            color: #1D1D1F;
            font-size: 0.9rem;
            padding: 0.75rem 1rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .apple-input:focus {
            border-color: #0071E3;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.15);
        }
        .apple-input::placeholder { color: #86868B; }
        .btn-primary {
            background: #0071E3;
            color: #fff;
            border-radius: 980px;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.65rem 1.5rem;
            width: 100%;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary:hover { background: #0077ED; }
    </style>
</head>
<body class="min-h-screen bg-[#F5F5F7] flex items-center justify-center px-4">

    <div class="w-full max-w-sm">

        {{-- Logo / Brand --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center mb-5">
                <svg width="160" height="140" viewBox="0 0 160 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Dark background -->
                    <rect width="160" height="140" rx="16" fill="#0A0A0A"/>

                    <!-- C letter form: arc open to the right -->
                    <path
                        d="M 86 28
                           A 34 34 0 1 0 86 82"
                        stroke="white" stroke-width="2.2" fill="none"
                        stroke-linecap="round"
                    />

                    <!-- L letter form: vertical bar then horizontal base -->
                    <line x1="90" y1="28" x2="90" y2="90" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
                    <line x1="90" y1="90" x2="118" y2="90" stroke="white" stroke-width="2.2" stroke-linecap="round"/>

                    <!-- CASUALITE wordmark -->
                    <text
                        x="80"
                        y="122"
                        text-anchor="middle"
                        fill="white"
                        font-family="'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif"
                        font-size="10"
                        font-weight="300"
                        letter-spacing="5"
                    >CASUALITE</text>
                </svg>
            </div>
            <p class="text-[#6E6E73] text-sm mt-1">Operations System</p>
        </div>

        {{-- Card --}}
        <div class="bg-white border border-[#E8E8ED] rounded-2xl p-8 shadow-sm">

            <h2 class="text-[#1D1D1F] font-semibold text-lg mb-6">Sign In</h2>

            {{-- Error --}}
            @if(session('error'))
                <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}" class="space-y-4">
                @csrf

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="apple-input"
                        placeholder="your@email.com"
                    >
                    @error('email')
                        <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="apple-input"
                        placeholder="••••••••"
                    >
                    @error('password')
                        <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <div class="pt-2">
                    <button type="submit" class="btn-primary">
                        Sign In
                    </button>
                </div>
            </form>

        </div>

        <p class="text-center text-[#86868B] text-xs mt-6">
            Forgot your password? Contact the Admin.
        </p>

    </div>

</body>
</html>
