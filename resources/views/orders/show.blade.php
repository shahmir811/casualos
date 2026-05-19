@extends('layouts.app')

@section('title', 'Order #' . $order->order_number)

@section('content')

@php
    $statusBadge = [
        'received'             => 'badge bg-blue-100 text-blue-700',
        'confirmed'            => 'badge bg-yellow-100 text-yellow-700',
        'stitching'            => 'badge bg-orange-100 text-orange-700',
        'partially_dispatched' => 'badge bg-purple-100 text-purple-700',
        'dispatched'           => 'badge bg-green-100 text-green-700',
    ];
    $statusLabel = [
        'received'             => 'Received',
        'confirmed'            => 'Confirmed',
        'stitching'            => 'Stitching',
        'partially_dispatched' => 'Partially Dispatched',
        'dispatched'           => 'Dispatched',
    ];
@endphp

<div class="flex items-start justify-between mb-7">
    <div>
        <a href="{{ route('orders.index') }}" class="text-[#0066CC] text-sm hover:underline">← Orders</a>
        <div class="flex flex-wrap items-center gap-2.5 mt-3">
            <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Order #{{ $order->order_number }}</h1>
            <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                {{ $statusLabel[$order->status] ?? ucfirst($order->status) }}
            </span>
            @if($order->is_flagged)
            <span class="badge bg-[#FFF0EF] text-[#FF3B30]">⚑ Flagged</span>
            @endif
        </div>
        <p class="text-[#6E6E73] text-sm mt-1">
            {{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }} · {{ $order->created_at->format('d M Y') }}
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        @if($order->status === 'received')
        <form method="POST" action="{{ route('orders.confirm', $order) }}">
            @csrf
            <button type="submit" onclick="return confirm('Confirm this order?')" class="btn-primary">
                Confirm Order
            </button>
        </form>
        @endif

        @if($order->status === 'confirmed' && in_array(Auth::user()->role, ['admin', 'production_manager']))
        <form method="POST" action="{{ route('orders.stitch', $order) }}">
            @csrf
            <button type="submit" class="btn-primary" style="background:#FF9500;">
                Send to Stitching
            </button>
        </form>
        @endif

        @if(Auth::user()->role === 'admin')
        <a href="{{ route('orders.reduce', $order) }}" class="btn-secondary">
            Log Reduction
        </a>
        @endif

        <a href="{{ route('orders.invoice', $order) }}" class="btn-secondary" target="_blank">
            Download Invoice
        </a>
    </div>
</div>

{{-- Financials --}}
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-7">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Order Status</p>
        @php
            $statusColors = [
                'received'             => 'text-blue-600',
                'confirmed'            => 'text-yellow-600',
                'stitching'            => 'text-orange-500',
                'partially_dispatched' => 'text-purple-600',
                'dispatched'           => 'text-[#30D158]',
            ];
        @endphp
        <p class="{{ $statusColors[$order->status] ?? 'text-[#1D1D1F]' }} text-2xl font-light">
            {{ $statusLabel[$order->status] ?? ucfirst($order->status) }}
        </p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Amount</p>
        <p class="text-[#1D1D1F] text-2xl font-light">PKR {{ lacs_format($order->total_amount, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Amount Paid</p>
        <p class="text-[#30D158] text-2xl font-light">PKR {{ lacs_format($order->total_paid, 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p>
        <p class="{{ $order->outstanding_balance > 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }} text-2xl font-light">
            PKR {{ lacs_format($order->outstanding_balance, 0) }}
        </p>
    </div>
</div>

{{-- Order Summary --}}
@php
    $firstItem      = $order->items->first();
    $qxs            = $firstItem?->qty_xs  ?? 0;
    $qs             = $firstItem?->qty_s   ?? 0;
    $qm             = $firstItem?->qty_m   ?? 0;
    $ql             = $firstItem?->qty_l   ?? 0;
    $qxl            = $firstItem?->qty_xl  ?? 0;
    $qtyPerDesign   = $qxs + $qs + $qm + $ql + $qxl;
    $numDesigns     = $order->catalogue?->number_of_designs ?? $order->items->count();
    $totalPieces    = $qtyPerDesign * $numDesigns;
@endphp
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Order Summary</h2>
        <span class="text-xs text-[#6E6E73]">{{ $numDesigns }} designs · {{ lacs_format($totalPieces) }} pieces total</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full apple-table whitespace-nowrap">
            <thead>
                <tr>
                    <th class="text-left">Catalogue</th>
                    <th class="text-center px-3">XS</th>
                    <th class="text-center px-3">S</th>
                    <th class="text-center px-3">M</th>
                    <th class="text-center px-3">L</th>
                    <th class="text-center px-3">XL</th>
                    <th class="text-center">Qty / Design</th>
                    <th class="text-center">Total Pieces</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <p class="font-medium text-[#1D1D1F] text-sm">{{ $order->catalogue->name ?? '—' }}</p>
                        <p class="text-xs text-[#86868B] mt-0.5">{{ $numDesigns }} designs</p>
                    </td>
                    <td class="text-center tabular-nums px-3 {{ $qxs ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qxs ?: '—' }}</td>
                    <td class="text-center tabular-nums px-3 {{ $qs  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qs  ?: '—' }}</td>
                    <td class="text-center tabular-nums px-3 {{ $qm  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qm  ?: '—' }}</td>
                    <td class="text-center tabular-nums px-3 {{ $ql  ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $ql  ?: '—' }}</td>
                    <td class="text-center tabular-nums px-3 {{ $qxl ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">{{ $qxl ?: '—' }}</td>
                    <td class="text-center font-semibold text-[#1D1D1F] tabular-nums">{{ lacs_format($qtyPerDesign) }}</td>
                    <td class="text-center font-semibold text-[#0071E3] tabular-nums">{{ lacs_format($totalPieces) }}</td>
                    <td class="text-right font-semibold text-[#1D1D1F] tabular-nums">PKR {{ lacs_format($order->total_amount, 0) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Record Payment --}}
@if(in_array(Auth::user()->role, ['admin', 'accountant']) && $order->outstanding_balance > 0)
<div class="card mb-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" class="w-full px-6 py-4 text-left flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Record Payment</h2>
        <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-[#6E6E73] transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-cloak class="px-6 pb-6 border-t border-[#F2F2F7] pt-5">

        @if($errors->any())
        <div class="mb-4 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
        @endif

        <form method="POST"
              action="{{ route('orders.payments.store', $order) }}"
              enctype="multipart/form-data"
              x-data="{
                paymentType: '{{ old('payment_type', 'cash') }}',
                preview: null,
                get isBankTransfer() { return this.paymentType === 'bank_transfer'; },
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) { this.preview = null; return; }
                    const reader = new FileReader();
                    reader.onload = ev => this.preview = ev.target.result;
                    reader.readAsDataURL(file);
                }
              }"
              class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Amount (PKR) <span class="text-[#FF3B30]">*</span></label>
                    <input type="number" name="amount" required min="1" step="0.01"
                        value="{{ old('amount') }}"
                        class="apple-input" placeholder="e.g. 50000">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Payment Method <span class="text-[#FF3B30]">*</span></label>
                    <select name="payment_type" required class="apple-input" x-model="paymentType">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="advance">From Advance Credit</option>
                    </select>
                </div>

                {{-- Bank account — only when Bank Transfer selected --}}
                <div x-show="isBankTransfer" x-cloak class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                        Bank Account <span class="text-[#FF3B30]">*</span>
                    </label>
                    <select name="bank_account_id" class="apple-input" :required="isBankTransfer">
                        <option value="">— Select bank account —</option>
                        @foreach($bankAccounts as $bank)
                        <option value="{{ $bank->id }}" {{ old('bank_account_id') == $bank->id ? 'selected' : '' }}>
                            {{ $bank->title }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Payment Date <span class="text-[#FF3B30]">*</span></label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', date('Y-m-d')) }}" required class="apple-input">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Notes <span class="font-normal normal-case">(optional)</span></label>
                    <input type="text" name="notes" value="{{ old('notes') }}" class="apple-input" placeholder="e.g. first instalment">
                </div>
            </div>

            {{-- Receipt Upload — only when Bank Transfer selected --}}
            <div x-show="isBankTransfer" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Payment Receipt <span class="text-[#FF3B30]">*</span>
                    <span class="font-normal normal-case ml-1">· JPG, PNG or WebP · max 5 MB</span>
                </label>

                <div class="flex gap-4 items-start">

                    {{-- Drop zone --}}
                    <label class="flex-1 flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-xl py-7 px-4 cursor-pointer transition-colors bg-[#F5F5F7]"
                           :class="preview ? 'border-[#30D158] bg-[#F0FFF4]' : 'border-[#D2D2D7] hover:border-[#0071E3]'">
                        <input type="file" name="receipt_image" accept="image/jpeg,image/jpg,image/png,image/webp"
                               class="sr-only" :required="isBankTransfer" @change="handleFile($event)">

                        <template x-if="!preview">
                            <div class="text-center pointer-events-none">
                                <svg class="w-9 h-9 text-[#C7C7CC] mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-sm font-medium text-[#1D1D1F]">Click to upload receipt</p>
                                <p class="text-xs text-[#86868B] mt-0.5">Screenshot, bank slip, or photo</p>
                            </div>
                        </template>

                        <template x-if="preview">
                            <p class="text-sm font-medium text-[#30D158] pointer-events-none">✓ Receipt selected — click to change</p>
                        </template>
                    </label>

                    {{-- Live preview --}}
                    <div x-show="preview" x-cloak
                         class="w-36 h-36 flex-shrink-0 rounded-xl overflow-hidden border border-[#E8E8ED] shadow-sm bg-[#F5F5F7]">
                        <img :src="preview" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>

            <div class="pt-1">
                <button type="submit" class="btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Payments History --}}
@if($order->payments->count())
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Payments ({{ $order->payments->count() }})</h2>
    </div>
    <table class="w-full apple-table">
        <tbody>
            @foreach($order->payments as $payment)
            <tr>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $payment->payment_date->format('d M Y') }}</td>
                <td>
                    <span class="badge bg-green-100 text-green-700">{{ ucwords(str_replace('_', ' ', $payment->payment_type)) }}</span>
                    @if($payment->payment_type === 'bank_transfer' && $payment->bankAccount)
                    <span class="ml-1 text-xs text-[#6E6E73]">· {{ $payment->bankAccount->title }}</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-sm">{{ $payment->notes ?? '—' }}</td>
                <td class="text-right text-[#30D158] font-mono font-medium">PKR {{ lacs_format($payment->amount, 0) }}</td>
                <td class="text-right">
                    @if($payment->receipt_image)
                    <a href="{{ Storage::url($payment->receipt_image) }}" target="_blank"
                       class="inline-block w-10 h-10 rounded-lg overflow-hidden border border-[#E8E8ED] hover:border-[#0071E3] transition-colors"
                       title="View receipt">
                        <img src="{{ Storage::url($payment->receipt_image) }}" class="w-full h-full object-cover">
                    </a>
                    @else
                    <span class="text-[#C7C7CC] text-xs">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Notes --}}
@if($order->notes)
<div class="card p-5">
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-2">Notes</p>
    <p class="text-[#1D1D1F] text-sm">{{ $order->notes }}</p>
</div>
@endif

@endsection
