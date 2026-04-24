@extends('layouts.app')

@section('title', 'New Team Account')

@section('content')

<div class="mb-7">
    <a href="{{ route('users.index') }}" class="text-[#0066CC] text-sm hover:underline">← Team Accounts</a>
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">New Team Account</h1>
</div>

<div class="max-w-lg">
    <form method="POST" action="{{ route('users.store') }}" class="card p-7 space-y-5">
        @csrf

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Full Name <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                class="apple-input" placeholder="Team member name">
            @error('name')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Email Address <span class="text-[#FF3B30]">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required
                class="apple-input" placeholder="email@casualite.com">
            @error('email')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Role <span class="text-[#FF3B30]">*</span></label>
            <select name="role" required class="apple-input">
                <option value="">Select a role</option>
                <option value="accountant" {{ old('role') === 'accountant' ? 'selected' : '' }}>Accountant</option>
                <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                <option value="designer" {{ old('role') === 'designer' ? 'selected' : '' }}>Designer</option>
            </select>
            @error('role')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Password <span class="text-[#FF3B30]">*</span></label>
            <input type="password" name="password" required minlength="8"
                class="apple-input" placeholder="Minimum 8 characters">
            @error('password')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Confirm Password <span class="text-[#FF3B30]">*</span></label>
            <input type="password" name="password_confirmation" required
                class="apple-input" placeholder="Re-enter password">
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">Create Account</button>
            <a href="{{ route('users.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
