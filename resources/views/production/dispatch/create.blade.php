@extends('layouts.app')
@section('title', 'Record Dispatch')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('dispatch.index') }}" class="text-[#0066CC] hover:underline text-sm">Dispatch</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Order #{{ $order->order_number }}</span>
</div>

<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-2">Record Dispatch</h1>
    <p class="text-[#6E6E73] text-sm mb-6">Order #{{ $order->order_number }} · {{ $order->customer->name }} · PKR {{ number_format($order->total_amount, 0) }}</p>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Order summary card --}}
    <div class="card overflow-hidden mb-5">
        <div class="px-5 py-4 border-b border-[#F2F2F7]">
            <h3 class="text-sm font-semibold text-[#1D1D1F]">Items Being Dispatched</h3>
        </div>
        <table class="w-full apple-table">
            <thead><tr><th class="text-left">Design</th><th class="text-right">XS</th><th class="text-right">S</th><th class="text-right">M</th><th class="text-right">L</th><th class="text-right">XL</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td class="font-medium text-sm">{{ $item->design->name ?? '—' }}</td>
                    <td class="text-right text-sm">{{ $item->qty_xs }}</td>
                    <td class="text-right text-sm">{{ $item->qty_s }}</td>
                    <td class="text-right text-sm">{{ $item->qty_m }}</td>
                    <td class="text-right text-sm">{{ $item->qty_l }}</td>
                    <td class="text-right text-sm">{{ $item->qty_xl }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('dispatch.store', $order) }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Dispatch Date</label>
                <input type="date" name="dispatch_date" value="{{ old('dispatch_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Shipping Address</label>
                <input type="text" name="shipping_address" value="{{ old('shipping_address', $order->customer->city ?? '') }}" class="apple-input" placeholder="City / full address">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Cargo Document / Tracking # <span class="font-normal normal-case">(optional)</span></label>
                <input type="text" name="cargo_document" value="{{ old('cargo_document') }}" class="apple-input" placeholder="e.g. TCS-12345678">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Batch Number <span class="font-normal normal-case">(optional — auto-generated if blank)</span></label>
                <input type="text" name="batch_number" value="{{ old('batch_number') }}" class="apple-input" placeholder="e.g. DISP-001">
            </div>
        </div>

        <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl text-sm text-orange-800">
            <strong>Note:</strong> Saving this dispatch will mark order #{{ $order->order_number }} as <strong>Dispatched</strong>. This cannot be undone.
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Confirm Dispatch</button>
            <a href="{{ route('dispatch.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
