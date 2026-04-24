@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')

<div class="mb-7">
    <a href="{{ route('customers.show', $customer) }}" class="text-[#0066CC] text-sm hover:underline">← {{ $customer->name }}</a>
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Edit Customer</h1>
</div>

<div class="max-w-lg">
    <form method="POST" action="{{ route('customers.update', $customer) }}" class="card p-7 space-y-5">
        @csrf @method('PUT')

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Full Name <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="name" value="{{ old('name', $customer->name) }}" required
                class="apple-input">
            @error('name') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Email Address <span class="text-[#FF3B30]">*</span></label>
            <input type="email" name="email" value="{{ old('email', $customer->email) }}" required
                class="apple-input">
            @error('email') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Contact Number <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="contact_number" value="{{ old('contact_number', $customer->contact_number) }}" required
                class="apple-input">
            @error('contact_number') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">City <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="city" value="{{ old('city', $customer->city) }}" required
                class="apple-input">
            @error('city') <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="btn-primary">Save Changes</button>
            <a href="{{ route('customers.show', $customer) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
