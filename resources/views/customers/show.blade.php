@extends('layouts.app')

@section('title', $customer->name)

@section('content')

<div class="flex items-start justify-between mb-7">
    <div>
        <a href="{{ route('customers.index') }}" class="text-[#0066CC] text-sm hover:underline">← Customers</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">{{ $customer->name }}</h1>
        <p class="text-[#6E6E73] text-sm mt-1">{{ $customer->city }}{{ $customer->country ? ', ' . $customer->country : '' }} · {{ $customer->contact_number }} · {{ $customer->email }}</p>
        @if($customer->address)
        <p class="text-[#86868B] text-xs mt-0.5">{{ $customer->address }}</p>
        @endif
    </div>
    @if(in_array(Auth::user()->role, ['admin', 'accountant']))
    <div class="flex items-center gap-2.5">
        @if(Auth::user()->role === 'admin')
        <a href="{{ route('customers.edit', $customer) }}" class="btn-secondary">Edit</a>
        @endif
        <a href="{{ route('customers.ledger', $customer) }}" class="btn-secondary">Ledger</a>
    </div>
    @endif
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-7">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Orders</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ $customer->orders->count() }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Advance Credit</p>
        <p class="text-2xl font-light {{ round((float) $customer->advance_credit_balance) > 0 ? 'text-[#30D158]' : 'text-[#1D1D1F]' }}">
            PKR {{ number_format($customer->advance_credit_balance, 0) }}
        </p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Customer Portal</p>
        <a href="{{ route('portal.show', $customer->portal_token) }}" target="_blank"
           class="text-[#0066CC] text-sm hover:underline">
            View Portal →
        </a>
    </div>
</div>

{{-- Record Advance Payment --}}
@if(in_array(Auth::user()->role, ['admin', 'accountant']))
<div class="card mb-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
    <button type="button" @click="open = !open" class="w-full px-6 py-4 text-left flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Record Advance Payment</h2>
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

        <p class="text-xs text-[#86868B] mb-4">Money received from this customer that isn't tied to a specific order — e.g. a pre-payment toward whatever they book next. Adds directly to their advance credit balance.</p>

        <form method="POST"
              action="{{ route('advance-payments.store', $customer) }}"
              enctype="multipart/form-data"
              x-data="{
                paymentType: '{{ old('payment_type', 'cash') }}',
                amountDisplay: '{{ old('amount') ? number_format((int) old('amount'), 0) : '' }}',
                amountRaw: '{{ old('amount') ?? '' }}',
                files: [],
                isDragging: false,
                lightboxSrc: '',
                lightboxOpen: false,
                get isBankTransfer() { return this.paymentType === 'bank_transfer'; },
                formatAmount(e) {
                    let raw = e.target.value.replace(/[^0-9]/g, '');
                    this.amountRaw = raw;
                    this.amountDisplay = raw ? Number(raw).toLocaleString('en-US') : '';
                },
                addFiles(fileList) {
                    Array.from(fileList).forEach(f => {
                        const ext = f.name.split('.').pop().toLowerCase();
                        this.files.push({ file: f, name: f.name, type: ext === 'pdf' ? 'pdf' : 'image', preview: ext !== 'pdf' ? URL.createObjectURL(f) : '' });
                    });
                    this.syncInput();
                },
                removeFile(idx) {
                    const f = this.files[idx];
                    if (f && f.preview) URL.revokeObjectURL(f.preview);
                    this.files.splice(idx, 1);
                    this.syncInput();
                },
                syncInput() {
                    const dt = new DataTransfer();
                    this.files.forEach(({ file }) => dt.items.add(file));
                    this.$refs.receiptInput.files = dt.files;
                },
                handleChange(e) { this.addFiles(e.target.files); },
                handleDrop(e) { this.isDragging = false; this.addFiles(e.dataTransfer.files); }
              }"
              class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Amount (PKR) <span class="text-[#FF3B30]">*</span></label>
                    <input type="text" inputmode="numeric" required
                        :value="amountDisplay"
                        @input="formatAmount($event)"
                        class="apple-input" placeholder="e.g. 50,000">
                    <input type="hidden" name="amount" :value="amountRaw">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Payment Method <span class="text-[#FF3B30]">*</span></label>
                    <select name="payment_type" required class="apple-input" x-model="paymentType">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                        Bank Account <span class="text-[#FF3B30]">*</span>
                    </label>
                    <select name="bank_account_id" required class="apple-input">
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
                    <input type="text" name="notes" value="{{ old('notes') }}" class="apple-input" placeholder="e.g. advance toward next catalogue">
                </div>
            </div>

            {{-- Receipt Upload — required for Bank Transfer only --}}
            <div x-show="isBankTransfer" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Payment Receipt <span class="text-[#FF3B30]">*</span>
                    <span class="font-normal normal-case ml-1">· PDF, JPG, PNG or WebP · max 5 MB each</span>
                </label>

                <input type="file" name="receipt_images[]" accept=".pdf,.jpg,.jpeg,.png,.webp"
                       multiple class="hidden" x-ref="receiptInput"
                       :required="isBankTransfer && files.length === 0"
                       @change="handleChange($event)">

                <template x-if="files.length === 0">
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
                        <p class="text-xs text-[#86868B] mt-1">PDF, JPG, PNG or WebP · max 5 MB each · multiple files allowed</p>
                    </div>
                </template>

                <template x-if="files.length > 0">
                    <div class="border border-[#E8E8ED] rounded-xl bg-[#FAFAFA] p-3"
                         @dragover.prevent="isDragging = true"
                         @dragleave.prevent="isDragging = false"
                         @drop.prevent="handleDrop($event)"
                         :class="isDragging ? 'border-[#0071E3] bg-[#F0F7FF]' : ''">
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 mb-2">
                            <template x-for="(f, idx) in files" :key="idx">
                                <div class="relative group">
                                    <template x-if="f.type === 'image'">
                                        <img :src="f.preview"
                                             class="w-full h-20 object-cover rounded-lg border border-[#E8E8ED] cursor-pointer hover:opacity-80 transition-opacity"
                                             @click="lightboxSrc = f.preview; lightboxOpen = true" alt="Receipt">
                                    </template>
                                    <template x-if="f.type === 'pdf'">
                                        <div class="w-full h-20 rounded-lg border border-[#FFCDD0] bg-[#FFF0EF] flex flex-col items-center justify-center gap-1">
                                            <svg class="w-7 h-7 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-[10px] font-bold text-[#FF3B30] tracking-wide">PDF</span>
                                        </div>
                                    </template>
                                    <button type="button" @click.stop="removeFile(idx)"
                                            class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-[#FF3B30] text-white rounded-full flex items-center justify-center hover:bg-red-700 transition-colors shadow opacity-0 group-hover:opacity-100">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    </button>
                                    <p class="text-[10px] text-[#6E6E73] mt-1 truncate" x-text="f.name"></p>
                                </div>
                            </template>
                            <button type="button" @click="$refs.receiptInput.click()"
                                    class="w-full h-20 rounded-lg border-2 border-dashed border-[#D1D1D6] hover:border-[#0071E3] hover:text-[#0071E3] transition-colors flex flex-col items-center justify-center gap-1 text-[#86868B]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span class="text-[10px] font-medium">Add more</span>
                            </button>
                        </div>
                        <p class="text-xs text-[#86868B]" x-text="files.length + ' file' + (files.length !== 1 ? 's' : '') + ' selected'"></p>
                    </div>
                </template>

                <div x-show="lightboxOpen" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
                     @click.self="lightboxOpen = false"
                     @keydown.escape.window="lightboxOpen = false">
                    <div class="relative max-w-3xl max-h-[90vh] mx-4">
                        <img :src="lightboxSrc" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl" alt="Preview">
                        <button type="button" @click="lightboxOpen = false"
                                class="absolute -top-3 -right-3 w-8 h-8 bg-white text-[#1D1D1F] rounded-full flex items-center justify-center shadow-lg hover:bg-[#F5F5F7] transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="pt-1">
                <button type="submit" class="btn-primary">Record Advance Payment</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Advance Payments History --}}
@if($customer->advancePayments->count())
<div class="card mb-5">
    <div class="px-6 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Advance Payments ({{ $customer->advancePayments->count() }})</h2>
    </div>
    {{-- Mobile card list --}}
    <div class="divide-y divide-[#F2F2F7] sm:hidden">
        @foreach($customer->advancePayments as $advancePayment)
        @php
            $receipts = $advancePayment->receipt_image ?? [];
            $shortfallOnDelete = max(0, (float) $advancePayment->amount - (float) $customer->advance_credit_balance);
        @endphp
        @if(in_array(Auth::user()->role, ['admin', 'accountant']))
        <form id="form-delete-advance-{{ $advancePayment->id }}" method="POST"
              action="{{ route('advance-payments.destroy', [$customer, $advancePayment]) }}">
            @csrf @method('DELETE')
        </form>
        @endif
        <div class="px-5 py-4 space-y-2">
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge bg-green-100 text-green-700">{{ ucwords(str_replace('_', ' ', $advancePayment->payment_type)) }}</span>
                    @if($advancePayment->bankAccount)
                    <span class="text-xs text-[#6E6E73]">· {{ $advancePayment->bankAccount->title }}</span>
                    @endif
                </div>
                <span class="text-[#30D158] font-mono font-semibold text-sm whitespace-nowrap">PKR {{ number_format($advancePayment->amount, 0) }}</span>
            </div>
            <div class="flex items-center justify-between gap-3">
                <span class="text-[#6E6E73] text-xs">{{ $advancePayment->payment_date->format('d M Y') }}</span>
                <div class="flex items-center gap-2">
                    @foreach($receipts as $receipt)
                    @php $ext = strtolower(pathinfo($receipt, PATHINFO_EXTENSION)); @endphp
                    @if($ext === 'pdf')
                    <a href="{{ Storage::url($receipt) }}" target="_blank"
                       class="inline-flex w-8 h-8 rounded-lg border border-[#FFCDD0] items-center justify-center bg-[#FFF0EF]" title="View receipt (PDF)">
                        <svg class="w-4 h-4 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                    @else
                    <a href="{{ Storage::url($receipt) }}" target="_blank"
                       class="inline-block w-8 h-8 rounded-lg overflow-hidden border border-[#E8E8ED]" title="View receipt">
                        <img src="{{ Storage::url($receipt) }}" class="w-full h-full object-cover">
                    </a>
                    @endif
                    @endforeach
                    @if(in_array(Auth::user()->role, ['admin', 'accountant']))
                    <a href="{{ route('advance-payments.edit', [$customer, $advancePayment]) }}" class="text-[#0066CC] text-xs hover:underline">Edit</a>
                    <button type="button" class="text-[#FF3B30] text-xs hover:underline"
                            @click="$store.confirm.show({
                                title: 'Delete Advance Payment',
                                message: 'Permanently delete this advance payment of PKR {{ number_format($advancePayment->amount, 0) }}?{{ $shortfallOnDelete > 0 ? ' PKR ' . number_format($shortfallOnDelete, 0) . ' of it has already been spent, so the advance credit balance will be floored at PKR 0 rather than going negative.' : '' }}',
                                formId: 'form-delete-advance-{{ $advancePayment->id }}',
                                confirmText: 'Delete Payment',
                                danger: true
                            })">Delete</button>
                    @endif
                </div>
            </div>
            @if($advancePayment->notes)
            <p class="text-[#6E6E73] text-xs">{{ $advancePayment->notes }}</p>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Desktop table --}}
    <div class="hidden sm:block overflow-x-auto">
    <table class="w-full apple-table">
        <tbody>
            @foreach($customer->advancePayments as $advancePayment)
            @php
                $receipts = $advancePayment->receipt_image ?? [];
                $shortfallOnDelete = max(0, (float) $advancePayment->amount - (float) $customer->advance_credit_balance);
            @endphp
            <tr>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $advancePayment->payment_date->format('d M Y') }}</td>
                <td>
                    <span class="badge bg-green-100 text-green-700">{{ ucwords(str_replace('_', ' ', $advancePayment->payment_type)) }}</span>
                    @if($advancePayment->bankAccount)
                    <span class="ml-1 text-xs text-[#6E6E73]">· {{ $advancePayment->bankAccount->title }}</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-sm">{{ $advancePayment->notes ?? '—' }}</td>
                <td class="text-right text-[#30D158] font-mono font-medium">PKR {{ number_format($advancePayment->amount, 0) }}</td>
                <td class="text-right">
                    @if(count($receipts) > 0)
                    <div class="flex items-center justify-end gap-1 flex-wrap">
                        @foreach($receipts as $receipt)
                        @php $ext = strtolower(pathinfo($receipt, PATHINFO_EXTENSION)); @endphp
                        @if($ext === 'pdf')
                        <a href="{{ Storage::url($receipt) }}" target="_blank"
                           class="inline-flex w-9 h-9 rounded-lg border border-[#FFCDD0] hover:border-[#FF3B30] transition-colors items-center justify-center bg-[#FFF0EF]"
                           title="View receipt (PDF)">
                            <svg class="w-4 h-4 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                            </svg>
                        </a>
                        @else
                        <a href="{{ Storage::url($receipt) }}" target="_blank"
                           class="inline-block w-9 h-9 rounded-lg overflow-hidden border border-[#E8E8ED] hover:border-[#0071E3] transition-colors"
                           title="View receipt">
                            <img src="{{ Storage::url($receipt) }}" class="w-full h-full object-cover">
                        </a>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <span class="text-[#C7C7CC] text-xs">—</span>
                    @endif
                </td>
                @if(in_array(Auth::user()->role, ['admin', 'accountant']))
                <td class="text-right whitespace-nowrap">
                    <form id="form-delete-advance-{{ $advancePayment->id }}-desktop" method="POST"
                          action="{{ route('advance-payments.destroy', [$customer, $advancePayment]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                    <a href="{{ route('advance-payments.edit', [$customer, $advancePayment]) }}" class="text-[#0066CC] text-xs hover:underline mr-3">Edit</a>
                    <button type="button"
                            class="text-[#FF3B30] text-xs hover:underline whitespace-nowrap"
                            @click="$store.confirm.show({
                                title: 'Delete Advance Payment',
                                message: 'Permanently delete this advance payment of PKR {{ number_format($advancePayment->amount, 0) }}?{{ $shortfallOnDelete > 0 ? ' PKR ' . number_format($shortfallOnDelete, 0) . ' of it has already been spent, so the advance credit balance will be floored at PKR 0 rather than going negative.' : '' }}',
                                formId: 'form-delete-advance-{{ $advancePayment->id }}-desktop',
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
</div>
@endif

{{-- Recent Orders --}}
<div class="card">
    <div class="px-6 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-[#1D1D1F] text-sm font-semibold">Orders</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Order #</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-left">Amount</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($customer->orders as $order)
                @php
                    $statusBadge = [
                        'received'   => 'badge bg-blue-100 text-blue-700',
                        'confirmed'  => 'badge bg-yellow-100 text-yellow-700',
                        'stitching'  => 'badge bg-orange-100 text-orange-700',
                        'dispatched' => 'badge bg-green-100 text-green-700',
                    ];
                @endphp
                <tr>
                    <td class="font-medium">#{{ $order->order_number }}</td>
                    <td class="text-[#6E6E73]">{{ $order->catalogue->name ?? '—' }}</td>
                    <td>PKR {{ number_format($order->total_amount, 0) }}</td>
                    <td>
                        <span class="{{ $statusBadge[$order->status] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">{{ $order->status }}</span>
                    </td>
                    <td class="text-[#86868B] text-xs">{{ $order->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] text-xs hover:underline">View →</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-[#86868B] py-10">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
