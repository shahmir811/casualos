@extends('layouts.app')

@section('title', 'New Customer')

@section('content')

<div class="mb-7">
    <a href="{{ route('customers.index') }}" class="text-[#0066CC] text-sm hover:underline">← Customers</a>
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">New Customer</h1>
</div>

<div class="max-w-lg">
    <form method="POST" action="{{ route('customers.store') }}" class="card p-7 space-y-5">
        @csrf

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Full Name <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" required
                class="apple-input" placeholder="Customer full name">
            @error('name') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Email Address <span class="text-[#FF3B30]">*</span></label>
            <input type="email" name="email" value="{{ old('email') }}" required
                class="apple-input" placeholder="customer@example.com">
            @error('email') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Contact Number (WhatsApp) <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="contact_number" value="{{ old('contact_number') }}" required
                class="apple-input" placeholder="+92 300 0000000">
            @error('contact_number') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">City <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="city" value="{{ old('city') }}" required
                class="apple-input" placeholder="e.g. Lahore, Karachi">
            @error('city') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">Add Customer</button>
            <a href="{{ route('customers.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
