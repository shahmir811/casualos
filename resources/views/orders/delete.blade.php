@extends('layouts.app')
@section('title', 'Delete Order')
@section('content')

@php
    $totalPieces = $order->items->sum('total_qty');
@endphp

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Delete Order</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Delete Order</h1>
<p class="text-[#6E6E73] text-sm mb-6">
    Order #{{ $order->order_number }} · {{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }} ·
    PKR {{ number_format($order->total_amount, 0) }} · {{ number_format($totalPieces) }} pieces
</p>

<div class="max-w-2xl"
     x-data="{
        refundChoiceRequired: {{ $refundableAmount > 0 ? 'true' : 'false' }},
        refundChoice: 'credit_to_advance',
        refundMethod: 'cash',
        refundableAmount: {{ (float) $refundableAmount }},

        get canSubmit() {
            return !this.refundChoiceRequired || !!this.refundChoice;
        },
        formatPkr(n) {
            return 'PKR ' + Math.round(n).toLocaleString('en-PK');
        }
     }">

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form id="form-delete-order" method="POST" action="{{ route('orders.delete.store', $order) }}" enctype="multipart/form-data" class="space-y-5">
        @csrf

        {{-- Refund / Credit section --}}
        <div class="card p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-[#1D1D1F] mb-0.5">Amount Paid</h3>
                <p class="text-xs text-[#6E6E73]">
                    PKR {{ number_format($refundableAmount, 0) }} was paid on this order (excluding any amount drawn from existing advance credit, which is automatically restored).
                </p>
            </div>

            @if($refundableAmount > 0)
            <div class="flex flex-col gap-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="refund_choice" value="credit_to_advance" x-model="refundChoice" class="mt-0.5">
                    <div>
                        <p class="text-sm font-medium text-[#1D1D1F]">Credit to Advance Balance</p>
                        <p class="text-xs text-[#6E6E73]">Add PKR {{ number_format($refundableAmount, 0) }} to the customer's advance credit for future orders.</p>
                    </div>
                </label>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="refund_choice" value="refund" x-model="refundChoice" class="mt-0.5">
                    <div>
                        <p class="text-sm font-medium text-[#1D1D1F]">Refund to Customer</p>
                        <p class="text-xs text-[#6E6E73]">Return PKR {{ number_format($refundableAmount, 0) }} to the customer via cash or bank transfer.</p>
                    </div>
                </label>
            </div>

            {{-- Refund method — only when refund is chosen --}}
            <div x-show="refundChoice === 'refund'" x-cloak class="space-y-4 pt-2 border-t border-[#F2F2F7]"
                 x-data="{
                    fileName: '', fileType: '', filePreview: '', lightboxOpen: false, isDragging: false,
                    processFile(file) {
                        if (!file) return;
                        this.fileName = file.name;
                        const ext = file.name.split('.').pop().toLowerCase();
                        this.fileType = (ext === 'pdf') ? 'pdf' : 'image';
                        this.filePreview = this.fileType === 'image' ? URL.createObjectURL(file) : '';
                    },
                    handleDrop(e) {
                        this.isDragging = false;
                        const f = e.dataTransfer.files[0];
                        if (f) { this.$refs.refundDocInput.files = e.dataTransfer.files; this.processFile(f); }
                    },
                    handleChange(e) { this.processFile(e.target.files[0]); },
                    clearFile() { this.fileName = ''; this.fileType = ''; this.filePreview = ''; this.$refs.refundDocInput.value = ''; }
                 }">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Refund Method <span class="text-[#FF3B30]">*</span></label>
                    <select name="refund_method" x-model="refundMethod" class="apple-input" :required="refundChoice === 'refund'">
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>

                <div x-show="refundMethod === 'bank_transfer'" x-cloak class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Bank / Reference</label>
                        <input type="text" name="refund_reference" class="apple-input"
                               placeholder="e.g. HBL — sent to customer account 0312-XXXXXXX"
                               value="{{ old('refund_reference') }}">
                        <p class="text-[11px] text-[#86868B] mt-1">Customer's bank name, account number, or transfer reference ID.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Transfer Proof <span class="font-normal normal-case text-[#86868B]">(image or PDF)</span></label>

                        <input type="file" name="refund_document" accept=".pdf,.jpg,.jpeg,.png"
                               class="hidden" x-ref="refundDocInput" @change="handleChange($event)">

                        <template x-if="!fileName">
                            <div class="border-2 border-dashed rounded-xl transition-colors cursor-pointer px-5 py-8 text-center"
                                 :class="isDragging ? 'border-[#0071E3] bg-[#F0F7FF]' : 'border-[#D1D1D6] bg-[#FAFAFA] hover:border-[#0071E3]'"
                                 @dragover.prevent="isDragging = true"
                                 @dragleave.prevent="isDragging = false"
                                 @drop.prevent="handleDrop($event)"
                                 @click="$refs.refundDocInput.click()">
                                <svg class="w-8 h-8 mx-auto text-[#86868B] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-[#1D1D1F] font-medium">Click to upload or drag &amp; drop</p>
                                <p class="text-xs text-[#86868B] mt-1">JPG, PNG, or PDF · max 10 MB</p>
                            </div>
                        </template>

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
                                    <button type="button" @click="$refs.refundDocInput.click()" class="text-xs text-[#0066CC] hover:underline mt-1 block">Change file</button>
                                </div>
                            </div>
                        </template>

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
                </div>
            </div>
            @else
            <p class="text-sm text-[#86868B]">Nothing was paid on this order — no refund or credit is needed.</p>
            @endif
        </div>

        {{-- Notice --}}
        <div class="p-4 bg-[#F0F7FF] border border-[#CDE3FF] rounded-xl text-sm text-[#0066CC]">
            This order's {{ number_format($totalPieces) }} pieces will become available in the
            <a href="{{ route('free-pieces.index') }}" class="underline font-medium">Free Pieces</a> pool
            once deleted, ready to assign to another customer at any time.
        </div>

        {{-- Warning --}}
        <div class="p-4 bg-[#FFF0EF] border border-[#FFCDD0] rounded-xl text-sm text-[#FF3B30]">
            <strong>Warning:</strong> This will permanently delete Order #{{ $order->order_number }}, including all of its payments and any prior reduction/refund history. This cannot be undone.
        </div>

        <div class="flex gap-3 items-center">
            <button type="button" class="btn-primary"
                    :style="canSubmit ? 'background:#FF3B30;' : 'background:#FF9896;cursor:not-allowed;'"
                    :disabled="!canSubmit"
                    @click="canSubmit && $store.confirm.show({
                        title: 'Delete Order',
                        message: 'This will permanently delete Order #{{ $order->order_number }} and all related records. This cannot be undone.',
                        formId: 'form-delete-order',
                        confirmText: 'Delete Order',
                        danger: true
                    })">Delete Order</button>
            <a href="{{ route('orders.show', $order) }}" class="btn-secondary">Cancel</a>
            <p x-show="!canSubmit" x-cloak class="text-sm text-[#FF3B30]">Choose whether to refund or credit the amount paid.</p>
        </div>
    </form>
</div>

@endsection
