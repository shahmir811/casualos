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
            <div class="inline-flex items-center justify-center mb-3">
                <img src="/images/casualite-logo.png" alt="Casualite" style="height:110px; width:auto;">
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
