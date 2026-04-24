@extends('layouts.app')
@section('title', 'Edit Order')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->id }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Edit</span>
</div>

<div class="max-w-lg">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Edit Order #{{ $order->id }}</h1>
    <p class="text-[#6E6E73] text-sm mb-6">{{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }}</p>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('orders.update', $order) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div class="card p-6 space-y-5">

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Internal Notes</label>
                <textarea name="notes" rows="3" class="apple-input"
                          placeholder="Add any internal notes about this order...">{{ old('notes', $order->notes) }}</textarea>
                <p class="text-[#86868B] text-xs mt-1">Only visible to admin and accountant.</p>
            </div>

            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative" x-data="{ checked: {{ $order->is_flagged ? 'true' : 'false' }} }">
                        <input type="hidden" name="is_flagged" value="0">
                        <input type="checkbox" name="is_flagged" value="1"
                               x-model="checked"
                               {{ $order->is_flagged ? 'checked' : '' }}
                               class="sr-only peer">
                        <div @click="checked = !checked"
                             :class="checked ? 'bg-[#FF3B30]' : 'bg-[#D2D2D7]'"
                             class="w-10 h-6 rounded-full cursor-pointer transition-colors duration-200">
                            <div :class="checked ? 'translate-x-4' : 'translate-x-0'"
                                 class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200"></div>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-[#1D1D1F]">Flag this order</p>
                        <p class="text-xs text-[#6E6E73]">Flagged orders appear in the red alert in the top bar</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save Changes</button>
            <a href="{{ route('orders.show', $order) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
