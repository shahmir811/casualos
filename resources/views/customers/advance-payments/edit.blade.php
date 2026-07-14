@extends('layouts.app')
@section('title', 'Edit Advance Payment')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('customers.index') }}" class="text-[#0066CC] hover:underline text-sm">Customers</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('customers.show', $customer) }}" class="text-[#0066CC] hover:underline text-sm">{{ $customer->name }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Edit Advance Payment</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Edit Advance Payment</h1>
<p class="text-[#6E6E73] text-sm mb-6">{{ $customer->name }} · Current advance credit balance: PKR {{ number_format($customer->advance_credit_balance, 0) }}</p>

<div class="max-w-2xl">

@if($errors->any())
<div class="mb-4 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<div class="card p-6"
     x-data="{
        paymentType: '{{ old('payment_type', $advancePayment->payment_type) }}',
        amountDisplay: '{{ number_format((int) old('amount', $advancePayment->amount), 0) }}',
        amountRaw: '{{ old('amount', $advancePayment->amount) }}',
        currentBalance: {{ (float) $customer->advance_credit_balance }},
        originalAmount: {{ (float) $advancePayment->amount }},
        existingReceipts: {{ Illuminate\Support\Js::from(collect($advancePayment->receipt_image ?? [])->map(fn($path) => [
            'path' => $path,
            'url'  => \Illuminate\Support\Facades\Storage::url($path),
            'type' => strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image',
            'name' => basename($path),
        ])->values()) }},
        removedExisting: [],
        files: [],
        isDragging: false,
        lightboxSrc: '',
        lightboxOpen: false,
        get isBankTransfer() { return this.paymentType === 'bank_transfer'; },
        get remainingExisting() { return this.isBankTransfer ? this.existingReceipts.filter(r => !this.removedExisting.includes(r.path)) : []; },
        get willClearExisting() { return !this.isBankTransfer && this.existingReceipts.length > 0; },
        // The reversal step floors the balance at 0 rather than going negative — if the
        // original amount exceeds what's still available, some of it has already been
        // spent elsewhere (applied to an order) and can't be reversed.
        get shortfall() { return Math.max(0, this.originalAmount - this.currentBalance); },
        get showShortfallWarning() { return this.shortfall > 0; },
        removeExisting(path) { this.removedExisting.push(path); },
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
     }">

    <form method="POST" action="{{ route('advance-payments.update', [$customer, $advancePayment]) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        <template x-for="path in removedExisting" :key="path">
            <input type="hidden" name="remove_receipts[]" :value="path">
        </template>

        {{-- Shortfall warning — informational only, does not block submission --}}
        <p x-show="showShortfallWarning" x-cloak
           class="text-xs text-[#B45309] bg-[#FFFBEB] border border-[#FDE68A] rounded-lg px-3 py-2 leading-relaxed">
            PKR <span x-text="shortfall.toLocaleString('en-US')" class="font-semibold"></span> of this payment's original amount has already been spent (applied to an order since). Saving will floor the advance credit balance at PKR 0 before applying the new amount, rather than going negative.
        </p>

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
                    <option value="{{ $bank->id }}"
                        {{ old('bank_account_id', $advancePayment->bank_account_id) == $bank->id ? 'selected' : '' }}>
                        {{ $bank->title }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Payment Date <span class="text-[#FF3B30]">*</span></label>
                <input type="date" name="payment_date" value="{{ old('payment_date', $advancePayment->payment_date->format('Y-m-d')) }}" required class="apple-input">
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Notes <span class="font-normal normal-case">(optional)</span></label>
                <input type="text" name="notes" value="{{ old('notes', $advancePayment->notes) }}" class="apple-input" placeholder="e.g. advance toward next catalogue">
            </div>
        </div>

        {{-- Receipt Upload — required for Bank Transfer only --}}
        <div x-show="isBankTransfer" x-cloak>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                Payment Receipt <span class="text-[#FF3B30]">*</span>
                <span class="font-normal normal-case ml-1">· PDF, JPG, PNG or WebP · max 5 MB each</span>
            </label>

            <p x-show="willClearExisting" x-cloak class="text-xs text-[#86868B] mb-2">
                Switching away from Bank Transfer will remove the existing receipt(s).
            </p>

            <template x-if="remainingExisting.length > 0">
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 mb-3">
                    <template x-for="r in remainingExisting" :key="r.path">
                        <div class="relative group">
                            <template x-if="r.type === 'image'">
                                <img :src="r.url" class="w-full h-20 object-cover rounded-lg border border-[#E8E8ED] cursor-pointer hover:opacity-80 transition-opacity"
                                     @click="lightboxSrc = r.url; lightboxOpen = true" alt="Receipt">
                            </template>
                            <template x-if="r.type === 'pdf'">
                                <a :href="r.url" target="_blank" class="w-full h-20 rounded-lg border border-[#FFCDD0] bg-[#FFF0EF] flex flex-col items-center justify-center gap-1">
                                    <svg class="w-7 h-7 text-[#FF3B30]" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-[10px] font-bold text-[#FF3B30] tracking-wide">PDF</span>
                                </a>
                            </template>
                            <button type="button" @click.stop="removeExisting(r.path)"
                                    class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-[#FF3B30] text-white rounded-full flex items-center justify-center hover:bg-red-700 transition-colors shadow opacity-0 group-hover:opacity-100">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            </button>
                            <p class="text-[10px] text-[#6E6E73] mt-1 truncate" x-text="r.name"></p>
                        </div>
                    </template>
                </div>
            </template>

            <input type="file" name="receipt_images[]" accept=".pdf,.jpg,.jpeg,.png,.webp"
                   multiple class="hidden" x-ref="receiptInput"
                   :required="isBankTransfer && remainingExisting.length === 0 && files.length === 0"
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
                    <p class="text-xs text-[#86868B] mt-1">Add a new receipt · multiple files allowed</p>
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
                                    <img :src="f.preview" class="w-full h-20 object-cover rounded-lg border border-[#E8E8ED] cursor-pointer hover:opacity-80 transition-opacity"
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

        <div class="pt-1 flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Changes</button>
            <a href="{{ route('customers.show', $customer) }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>
</div>
@endsection
