@extends('layouts.app')
@section('title', 'Order Reduction')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Reduction</span>
</div>

<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Order Reduction</h1>
    <p class="text-[#6E6E73] text-sm mb-6">Order #{{ $order->order_number }} · {{ $order->customer->name ?? '—' }} · PKR {{ number_format($order->total_amount, 0) }}</p>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('orders.reduce.store', $order) }}" class="space-y-5"
          x-data="{
            items: [],
            addItem() { this.items.push({ design_id: '', size: 'xs', qty: 1 }); },
            removeItem(i) { this.items.splice(i, 1); }
          }">
        @csrf

        <div class="card p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Adjustment Type</label>
                <select name="adjustment_type" class="apple-input" required>
                    <option value="">— Select type —</option>
                    <option value="damage"           {{ old('adjustment_type') === 'damage'           ? 'selected' : '' }}>Damage</option>
                    <option value="short_supply"     {{ old('adjustment_type') === 'short_supply'     ? 'selected' : '' }}>Short Supply</option>
                    <option value="price_correction" {{ old('adjustment_type') === 'price_correction' ? 'selected' : '' }}>Price Correction</option>
                    <option value="other"            {{ old('adjustment_type') === 'other'            ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Notes <span class="font-normal normal-case">(optional)</span></label>
                <textarea name="notes" rows="2" class="apple-input" placeholder="Reason for reduction...">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Items to reduce --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-[#1D1D1F]">Items to Reduce</h3>
                    <p class="text-xs text-[#6E6E73] mt-0.5">Select which design, size, and quantity to deduct</p>
                </div>
                <button type="button" @click="addItem()" class="btn-secondary text-xs">+ Add Item</button>
            </div>

            <div class="divide-y divide-[#F2F2F7]" x-show="items.length > 0">
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="px-5 py-3 grid grid-cols-12 gap-3 items-center">
                        <div class="col-span-5">
                            <label class="block text-xs text-[#86868B] mb-1">Design</label>
                            <select :name="`items[${idx}][design_id]`" x-model="item.design_id" class="apple-input" required>
                                <option value="">— Design —</option>
                                @foreach($order->items as $oi)
                                <option value="{{ $oi->design->id }}">{{ $oi->design->name ?? '—' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-xs text-[#86868B] mb-1">Size</label>
                            <select :name="`items[${idx}][size]`" x-model="item.size" class="apple-input" required>
                                <option value="xs">XS</option>
                                <option value="s">S</option>
                                <option value="m">M</option>
                                <option value="l">L</option>
                                <option value="xl">XL</option>
                            </select>
                        </div>
                        <div class="col-span-3">
                            <label class="block text-xs text-[#86868B] mb-1">Qty</label>
                            <input type="number" :name="`items[${idx}][qty]`" x-model="item.qty" min="1" class="apple-input text-center" required>
                        </div>
                        <div class="col-span-1 flex justify-end pt-4">
                            <button type="button" @click="removeItem(idx)" class="text-[#FF3B30] hover:text-red-700 transition-colors">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="items.length === 0" class="px-5 py-8 text-center text-[#86868B] text-sm">
                Click <strong>+ Add Item</strong> to specify which pieces are being reduced.
            </div>
        </div>

        <div class="p-4 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-sm text-[#FF3B30]">
            <strong>Warning:</strong> This will permanently reduce Order #{{ $order->order_number }}'s value and create a ledger credit for the customer. This cannot be undone.
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-danger" x-bind:disabled="items.length === 0">Apply Reduction</button>
            <a href="{{ route('orders.show', $order) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
