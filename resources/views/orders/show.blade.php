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
        'cancelled'            => 'badge bg-red-100 text-red-700',
    ];
    $statusLabel = [
        'received'             => 'Received',
        'confirmed'            => 'Confirmed',
        'stitching'            => 'Stitching',
        'partially_dispatched' => 'Partially Dispatched',
        'dispatched'           => 'Dispatched',
        'cancelled'            => 'Cancelled',
    ];
@endphp

<div x-data="{ reductionModal: false, activeReductionId: null }">

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
        <form id="form-confirm-order" method="POST" action="{{ route('orders.confirm', $order) }}">@csrf</form>
        <button type="button" class="btn-primary"
                @click="$store.confirm.show({
                    title: 'Confirm Order',
                    message: 'Confirm order {{ $order->order_number }} for {{ $order->customer->name }}? Status will change to Confirmed.',
                    formId: 'form-confirm-order',
                    confirmText: 'Confirm Order'
                })">
            Confirm Order
        </button>
        @endif

        @if($order->status === 'confirmed' && in_array(Auth::user()->role, ['admin', 'production_manager']))
        <form method="POST" action="{{ route('orders.stitch', $order) }}">
            @csrf
            <button type="submit" class="btn-primary" style="background:#FF9500;">
                Send to Stitching
            </button>
        </form>
        @endif

        @if(in_array(Auth::user()->role, ['admin', 'accountant']) && !in_array($order->status, ['cancelled', 'dispatched']))
        <a href="{{ route('orders.reduce', $order) }}" class="btn-secondary">
            Log Reduction
        </a>
        @endif

        @if(Auth::user()->role === 'admin' && $order->reductions->count() > 0)
        <a href="{{ route('orders.reassign.create', $order) }}" class="btn-secondary">
            Reassign Pieces
        </a>
        @endif

        <a href="{{ route('orders.invoice', $order) }}" class="btn-secondary" target="_blank">
            Download Invoice
        </a>

        @if($order->status === 'received' && $order->total_paid == 0 && in_array(Auth::user()->role, ['admin', 'accountant']))
        <form id="form-delete-order" method="POST" action="{{ route('orders.destroy', $order) }}">
            @csrf
            @method('DELETE')
        </form>
        <button type="button"
                class="btn-secondary text-[#FF3B30] border-[#FF3B30] hover:bg-[#FFF0EF]"
                @click="$store.confirm.show({
                    title: 'Delete Order',
                    message: 'This will permanently remove Order #{{ $order->order_number }} and all related records from the system. This cannot be undone.',
                    formId: 'form-delete-order',
                    confirmText: 'Delete Order',
                    danger: true
                })">
            Delete Order
        </button>
        @endif
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
                'cancelled'            => 'text-red-600',
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
    $firstItem  = $order->items->first();
    $rawXs      = $firstItem?->qty_xs ?? 0;
    $rawS       = $firstItem?->qty_s  ?? 0;
    $rawM       = $firstItem?->qty_m  ?? 0;
    $rawL       = $firstItem?->qty_l  ?? 0;
    $rawXl      = $firstItem?->qty_xl ?? 0;
    $numDesigns = $order->catalogue?->number_of_designs ?? $order->items->count();

    // Sum reduction qty per size and total across all reductions
    $reducedBySize = ['xs' => 0, 's' => 0, 'm' => 0, 'l' => 0, 'xl' => 0];
    $totalReduced  = 0;
    foreach ($order->reductions as $red) {
        foreach ($red->items as $ri) {
            if (isset($reducedBySize[$ri->size])) {
                $reducedBySize[$ri->size] += $ri->qty_reduced;
            }
            $totalReduced += $ri->qty_reduced;
        }
    }

    // A size column only zeroes out when ALL pieces of that size across all designs are eliminated
    $qxs = ($reducedBySize['xs'] >= $rawXs * $numDesigns) ? 0 : $rawXs;
    $qs  = ($reducedBySize['s']  >= $rawS  * $numDesigns) ? 0 : $rawS;
    $qm  = ($reducedBySize['m']  >= $rawM  * $numDesigns) ? 0 : $rawM;
    $ql  = ($reducedBySize['l']  >= $rawL  * $numDesigns) ? 0 : $rawL;
    $qxl = ($reducedBySize['xl'] >= $rawXl * $numDesigns) ? 0 : $rawXl;

    $qtyPerDesign  = $qxs + $qs + $qm + $ql + $qxl;
    $originalTotal = ($rawXs + $rawS + $rawM + $rawL + $rawXl) * $numDesigns;
    $totalPieces   = max(0, $originalTotal - $totalReduced);
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
                    <th class="text-left">Order #</th>
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
                    <td class="text-[#6E6E73] text-xs tabular-nums font-mono">{{ $order->order_number }}</td>
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
                fileName: '',
                fileType: '',
                filePreview: '',
                isDragging: false,
                lightboxOpen: false,
                get isBankTransfer() { return this.paymentType === 'bank_transfer'; },
                processFile(file) {
                    if (!file) return;
                    this.fileName = file.name;
                    const ext = file.name.split('.').pop().toLowerCase();
                    this.fileType = (ext === 'pdf') ? 'pdf' : 'image';
                    this.filePreview = this.fileType === 'image' ? URL.createObjectURL(file) : '';
                },
                handleFile(e) {
                    const file = e.target.files[0];
                    if (!file) { this.fileName = ''; this.fileType = ''; this.filePreview = ''; return; }
                    this.processFile(file);
                },
                handleDrop(e) {
                    this.isDragging = false;
                    const file = e.dataTransfer.files[0];
                    if (!file) return;
                    this.$refs.receiptInput.files = e.dataTransfer.files;
                    this.processFile(file);
                },
                clearFile() {
                    this.fileName = '';
                    this.fileType = '';
                    this.filePreview = '';
                    this.$refs.receiptInput.value = '';
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
                    <span class="font-normal normal-case ml-1">· PDF, JPG, PNG or WebP · max 5 MB</span>
                </label>

                <input type="file" name="receipt_image" accept=".pdf,.jpg,.jpeg,.png,.webp"
                       class="hidden" x-ref="receiptInput" :required="isBankTransfer" @change="handleFile($event)">

                {{-- Empty state --}}
                <template x-if="!fileName">
                    <div class="border-2 border-dashed rounded-xl transition-colors cursor-pointer px-5 py-8 text-center"
                         :class="isDragging ? 'border-[#0071E3] bg-[#F0F7FF]' : 'border-[#D1D1D6] bg-[#FAFAFA] hover:border-[#0071E3]'"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleDrop($event)"
                         @click="$refs.receiptInput.click()">
                        <svg class="w-8 h-8 mx-auto text-[#86868B] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-sm text-[#1D1D1F] font-medium">Click to upload or drag &amp; drop</p>
                        <p class="text-xs text-[#86868B] mt-1">PDF, JPG, PNG or WebP · max 5 MB</p>
                    </div>
                </template>

                {{-- File selected --}}
                <template x-if="fileName">
                    <div class="flex items-center gap-4 p-3 border border-[#E8E8ED] rounded-xl bg-[#FAFAFA]">
                        <div class="relative shrink-0 w-20 h-20">
                            <template x-if="fileType === 'image'">
                                <img :src="filePreview"
                                     class="w-20 h-20 object-cover rounded-lg border border-[#E8E8ED] cursor-pointer hover:opacity-80 transition-opacity"
                                     @click="lightboxOpen = true" alt="Preview">
                            </template>
                            <template x-if="fileType === 'pdf'">
                                <div class="w-20 h-20 rounded-lg border border-[#FFCDD0] bg-[#FFF0EF] flex flex-col items-center justify-center gap-1">
                                    <svg class="w-8 h-8 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#FF3B30] tracking-wide">PDF</span>
                                </div>
                            </template>
                            <button type="button" @click.stop="clearFile()"
                                    class="absolute -top-2 -right-2 w-5 h-5 bg-[#FF3B30] text-white rounded-full flex items-center justify-center hover:bg-red-700 transition-colors shadow">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-[#1D1D1F] font-medium truncate" x-text="fileName"></p>
                            <p x-show="fileType === 'image'" class="text-xs text-[#0066CC] mt-1 cursor-pointer hover:underline" @click="lightboxOpen = true">Click thumbnail to preview</p>
                            <p x-show="fileType === 'pdf'" class="text-xs text-[#86868B] mt-1">No preview available</p>
                            <button type="button" @click="$refs.receiptInput.click()" class="text-xs text-[#0066CC] hover:underline mt-1 block">Change file</button>
                        </div>
                    </div>
                </template>

                {{-- Image lightbox --}}
                <div x-show="lightboxOpen" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
                     @click.self="lightboxOpen = false"
                     @keydown.escape.window="lightboxOpen = false">
                    <div class="relative max-w-3xl max-h-[90vh] mx-4">
                        <img :src="filePreview" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl" alt="Preview">
                        <button type="button" @click="lightboxOpen = false"
                                class="absolute -top-3 -right-3 w-8 h-8 bg-white text-[#1D1D1F] rounded-full flex items-center justify-center shadow-lg hover:bg-[#F5F5F7] transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
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
                    @php $receiptExt = strtolower(pathinfo($payment->receipt_image, PATHINFO_EXTENSION)); @endphp
                    @if($receiptExt === 'pdf')
                    <a href="{{ Storage::url($payment->receipt_image) }}" target="_blank"
                       class="inline-flex w-10 h-10 rounded-lg border border-[#FFCDD0] hover:border-[#FF3B30] transition-colors items-center justify-center bg-[#FFF0EF]"
                       title="View receipt (PDF)">
                        <svg class="w-5 h-5 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                    @else
                    <a href="{{ Storage::url($payment->receipt_image) }}" target="_blank"
                       class="inline-block w-10 h-10 rounded-lg overflow-hidden border border-[#E8E8ED] hover:border-[#0071E3] transition-colors"
                       title="View receipt">
                        <img src="{{ Storage::url($payment->receipt_image) }}" class="w-full h-full object-cover">
                    </a>
                    @endif
                    @else
                    <span class="text-[#C7C7CC] text-xs">—</span>
                    @endif
                </td>
                @if(in_array(Auth::user()->role, ['admin', 'accountant']))
                <td class="text-right">
                    <form id="form-delete-payment-{{ $payment->id }}" method="POST"
                          action="{{ route('orders.payments.destroy', [$order, $payment]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                    <button type="button"
                            class="text-[#FF3B30] text-xs hover:underline whitespace-nowrap"
                            @click="$store.confirm.show({
                                title: 'Delete Payment',
                                message: 'Permanently delete this payment of PKR {{ lacs_format($payment->amount, 0) }}? The order balance will be recalculated.',
                                formId: 'form-delete-payment-{{ $payment->id }}',
                                confirmText: 'Delete Payment',
                                danger: true
                            })">
                        Delete
                    </button>
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Refunds --}}
@if($order->refunds->count())
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Refunds ({{ $order->refunds->count() }})</h2>
    </div>
    <table class="w-full apple-table">
        <tbody>
            @foreach($order->refunds as $refund)
            <tr>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $refund->refund_date->format('d M Y') }}</td>
                <td>
                    <span class="badge bg-red-100 text-red-700">{{ $refund->refund_method === 'bank_transfer' ? 'Bank Transfer' : 'Cash' }}</span>
                    @if($refund->refund_method === 'bank_transfer' && $refund->refund_reference)
                    <span class="ml-1 text-xs text-[#6E6E73]">· {{ $refund->refund_reference }}</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-sm">{{ $refund->notes ?? '—' }}</td>
                <td class="text-right text-[#FF3B30] font-mono font-medium">− PKR {{ lacs_format($refund->amount, 0) }}</td>
                <td class="text-right">
                    @if($refund->refund_document)
                    @php $docExt = strtolower(pathinfo($refund->refund_document, PATHINFO_EXTENSION)); @endphp
                    @if(in_array($docExt, ['jpg', 'jpeg', 'png']))
                    <a href="{{ Storage::url($refund->refund_document) }}" target="_blank"
                       class="inline-block w-10 h-10 rounded-lg overflow-hidden border border-[#E8E8ED] hover:border-[#0071E3] transition-colors"
                       title="View transfer proof">
                        <img src="{{ Storage::url($refund->refund_document) }}" class="w-full h-full object-cover">
                    </a>
                    @else
                    <a href="{{ Storage::url($refund->refund_document) }}" target="_blank"
                       class="inline-flex w-10 h-10 rounded-lg border border-[#E8E8ED] hover:border-[#0071E3] transition-colors items-center justify-center bg-[#FFF0EF]"
                       title="View transfer proof (PDF)">
                        <svg class="w-5 h-5 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                    @endif
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

{{-- Reductions --}}
@if($order->reductions->count())
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Reductions ({{ $order->reductions->count() }})</h2>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Date</th>
                <th class="text-left">Type</th>
                <th class="text-left">Items</th>
                <th class="text-left">Surplus Action</th>
                <th class="text-right">Amount Reduced</th>
                <th class="text-right"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->reductions as $reduction)
            <tr>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $reduction->reduction_date->format('d M Y') }}</td>
                <td>
                    <span class="badge bg-yellow-100 text-yellow-700">{{ ucwords(str_replace('_', ' ', $reduction->adjustment_type)) }}</span>
                </td>
                <td class="text-[#6E6E73] text-xs">
                    @if($reduction->items->count())
                        <div class="space-y-0.5">
                            @foreach($reduction->items as $ri)
                            <div>{{ ($ri->design->name ?? '—') }} <span class="font-mono">{{ strtoupper($ri->size) }}×{{ $ri->qty_reduced }}</span></div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-[#C7C7CC]">—</span>
                    @endif
                </td>
                <td>
                    @if($reduction->surplus_action === 'credit_to_advance')
                        <span class="badge bg-purple-100 text-purple-700">Credit to Advance</span>
                    @elseif($reduction->surplus_action === 'refund')
                        <span class="badge bg-orange-100 text-orange-700">Refund Issued</span>
                    @else
                        <span class="text-[#C7C7CC] text-xs">—</span>
                    @endif
                </td>
                <td class="text-right font-mono font-medium text-[#FF3B30] tabular-nums">− PKR {{ lacs_format($reduction->adjustment_amount, 0) }}</td>
                <td class="text-right">
                    <button type="button"
                            @click="activeReductionId = {{ $reduction->id }}; reductionModal = true"
                            class="text-[#0066CC] text-xs hover:underline whitespace-nowrap">View Details</button>
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

{{-- ===== REDUCTION DETAIL MODAL ===== --}}
<div x-show="reductionModal"
     x-cloak
     @click.self="reductionModal = false; activeReductionId = null"
     @keydown.escape.window="reductionModal = false; activeReductionId = null"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     style="display:none;">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#F2F2F7] sticky top-0 bg-white rounded-t-2xl">
            <h2 class="text-base font-semibold text-[#1D1D1F]">Order Reduction</h2>
            <button type="button"
                    @click="reductionModal = false; activeReductionId = null"
                    class="w-8 h-8 rounded-full flex items-center justify-center text-[#6E6E73] hover:bg-[#F5F5F7] transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Per-reduction panels --}}
        @foreach($order->reductions as $reduction)
        <div x-show="activeReductionId === {{ $reduction->id }}" x-cloak class="p-6 space-y-5">

            {{-- Reduction Details --}}
            <div class="card p-5 space-y-4">
                <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Reduction Details</h3>
                <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Date</p>
                        <p class="text-[#1D1D1F] font-medium">{{ $reduction->reduction_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Logged By</p>
                        <p class="text-[#1D1D1F] font-medium">{{ $reduction->reducedBy->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Adjustment Type</p>
                        <p class="text-[#1D1D1F] font-medium">{{ ucwords(str_replace('_', ' ', $reduction->adjustment_type)) }}</p>
                    </div>
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Catalogue</p>
                        <p class="text-[#1D1D1F] font-medium">{{ $order->catalogue->name ?? '—' }}</p>
                    </div>
                    @if($reduction->notes)
                    <div class="col-span-2">
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Notes</p>
                        <p class="text-[#1D1D1F]">{{ $reduction->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Items Reduced --}}
            <div class="card overflow-hidden">
                <div class="px-5 py-3 border-b border-[#F2F2F7]">
                    <h3 class="text-sm font-semibold text-[#1D1D1F]">Items Reduced</h3>
                </div>
                <table class="w-full apple-table">
                    <thead>
                        <tr>
                            <th class="text-left">Design</th>
                            <th class="text-center">Size</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reduction->items as $item)
                        <tr>
                            <td class="font-medium text-[#1D1D1F]">{{ $item->design->name ?? '—' }}</td>
                            <td class="text-center font-mono text-xs">{{ strtoupper($item->size) }}</td>
                            <td class="text-center tabular-nums">{{ $item->qty_reduced }}</td>
                            <td class="text-right tabular-nums text-[#6E6E73]">PKR {{ lacs_format($item->unit_price, 0) }}</td>
                            <td class="text-right tabular-nums font-medium text-[#FF3B30]">PKR {{ lacs_format($item->amount_reduced, 0) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-[#E8E8ED]">
                            <td colspan="4" class="text-right font-semibold text-[#1D1D1F] py-3 pr-4">Total Reduction</td>
                            <td class="text-right tabular-nums font-semibold text-[#FF3B30] py-3">PKR {{ lacs_format($reduction->adjustment_amount, 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Financial Impact --}}
            <div class="card p-5 space-y-3 text-sm">
                <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Financial Impact</h3>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Original order total</span>
                    <span class="font-medium tabular-nums text-[#1D1D1F]">PKR {{ lacs_format($reduction->original_total, 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Reduction amount</span>
                    <span class="font-medium tabular-nums text-[#FF3B30]">− PKR {{ lacs_format($reduction->adjustment_amount, 0) }}</span>
                </div>
                <div class="flex justify-between border-t border-[#F2F2F7] pt-2">
                    <span class="font-semibold text-[#1D1D1F]">New order total</span>
                    <span class="font-semibold tabular-nums text-[#1D1D1F]">PKR {{ lacs_format($reduction->new_total, 0) }}</span>
                </div>
            </div>

            {{-- Surplus Handling --}}
            @if($reduction->surplus_action && $reduction->surplus_action !== 'none')
            <div class="card p-5 space-y-3 text-sm">
                <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Surplus Handling</h3>
                @if($reduction->surplus_action === 'credit_to_advance')
                <div class="flex items-center gap-3">
                    <span class="badge bg-purple-100 text-purple-700">Credit to Advance</span>
                    <span class="text-[#6E6E73]">Surplus was added to customer's advance credit balance.</span>
                </div>
                @elseif($reduction->surplus_action === 'refund')
                <div class="flex items-center gap-3 mb-3">
                    <span class="badge bg-orange-100 text-orange-700">Refund Issued</span>
                </div>
                @if($reduction->refund)
                @php $refund = $reduction->refund; @endphp
                <div class="grid grid-cols-2 gap-x-6 gap-y-3">
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Date</p>
                        <p class="text-[#1D1D1F] font-medium">{{ $refund->refund_date->format('d M Y') }}</p>
                    </div>
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Method</p>
                        <p class="text-[#1D1D1F] font-medium">{{ $refund->refund_method === 'bank_transfer' ? 'Bank Transfer' : 'Cash' }}</p>
                    </div>
                    @if($refund->refund_method === 'bank_transfer' && $refund->refund_reference)
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Bank / Reference</p>
                        <p class="text-[#1D1D1F] font-medium">{{ $refund->refund_reference }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Amount</p>
                        <p class="text-[#FF3B30] font-semibold tabular-nums">PKR {{ lacs_format($refund->amount, 0) }}</p>
                    </div>
                </div>
                @endif
                @endif
            </div>
            @endif

        </div>
        @endforeach

    </div>
</div>

</div>{{-- end x-data wrapper --}}

@endsection
