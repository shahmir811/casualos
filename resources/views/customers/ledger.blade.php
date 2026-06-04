@extends('layouts.app')
@section('title', $customer->name . ' — Ledger')

@section('content')

<div class="flex flex-col gap-4 mb-7 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <a href="{{ route('customers.show', $customer) }}" class="text-[#0066CC] text-sm hover:underline">← {{ $customer->name }}</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Customer Ledger</h1>
    </div>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
        <a href="{{ route('customers.ledger.pdf', $customer) }}" target="_blank" class="btn-secondary self-start">
            Download PDF
        </a>
        <div class="stat-card text-right w-full sm:min-w-[180px] sm:w-auto">
            <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding Balance</p>
            <p class="text-2xl font-light {{ $balance > 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }}">
                PKR {{ lacs_format(abs($balance), 0) }}
                <span class="text-sm">{{ $balance > 0 ? 'Debit' : ($balance < 0 ? 'Credit' : '') }}</span>
            </p>
        </div>
    </div>
</div>

<div x-data="{
    open: false,
    reduction: null,
    reductions: {{ Illuminate\Support\Js::from($reductionMap) }},
    show(id) {
        this.reduction = this.reductions[id] ?? null;
        if (this.reduction) this.open = true;
    }
}" @keydown.escape.window="open = false">

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full apple-table min-w-[640px]">
        <thead>
            <tr>
                <th class="text-left">Date</th>
                <th class="text-left">Type</th>
                <th class="text-left">Description</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Order</th>
                <th class="text-left">By</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
            @php
                $typeBadge = [
                    'advance_received'   => 'badge bg-blue-100 text-blue-700',
                    'order_charged'      => 'badge bg-red-100 text-red-700',
                    'payment_received'   => 'badge bg-green-100 text-green-700',
                    'credit_applied'     => 'badge bg-teal-100 text-teal-700',
                    'order_reduced'      => 'badge bg-yellow-100 text-yellow-700',
                    'refund_issued'      => 'badge bg-orange-100 text-orange-700',
                ];
                $amountColor = [
                    'advance_received'   => 'text-[#30D158]',
                    'payment_received'   => 'text-[#30D158]',
                    'credit_applied'     => 'text-[#30D158]',
                    'order_charged'      => 'text-[#1D1D1F]',
                    'order_reduced'      => 'text-[#FF3B30]',
                    'refund_issued'      => 'text-[#FF3B30]',
                ];
                $prefix   = $entry->amount > 0 ? '+' : ($entry->amount < 0 ? '−' : '');
                $refKey   = $entry->reference_type . ':' . $entry->reference_id;
                $orderRef = $orderMap[$refKey] ?? null;
            @endphp
            <tr>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $entry->created_at->format('d M Y') }}</td>
                <td>
                    <span class="{{ $typeBadge[$entry->transaction_type] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                        {{ str_replace('_', ' ', $entry->transaction_type) }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-sm max-w-xs">
                    @if($entry->notes)
                        @foreach(explode("\n", $entry->notes) as $line)
                            <p class="{{ $loop->first ? 'text-[#1D1D1F] font-medium' : 'text-[#6E6E73]' }} text-xs leading-relaxed">{{ $line }}</p>
                        @endforeach
                    @else
                        <span class="text-[#C7C7CC]">—</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $orderRef['catalogue'] ?? '—' }}</td>
                <td class="text-xs whitespace-nowrap">
                    @if($orderRef)
                        <a href="{{ route('orders.show', $orderRef['id']) }}"
                           class="text-[#0066CC] hover:underline font-mono">#{{ $orderRef['number'] }}</a>
                        @if($entry->transaction_type === 'order_reduced' && $entry->reference_id)
                            <button type="button"
                                    @click="show({{ $entry->reference_id }})"
                                    class="ml-1.5 text-[#6E6E73] hover:text-[#0066CC] text-[10px] underline underline-offset-2">View</button>
                        @endif
                    @else
                        <span class="text-[#C7C7CC]">—</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $entry->createdBy->name ?? '—' }}</td>
                <td class="text-right font-mono text-sm {{ $amountColor[$entry->transaction_type] ?? 'text-[#1D1D1F]' }}">
                    {{ $prefix }}PKR {{ lacs_format(abs($entry->amount), 0) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-[#86868B] py-12">No ledger entries.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-5">{{ $entries->links() }}</div>

{{-- Reduction detail modal --}}
<template x-teleport="body">
<div x-show="open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     style="display:none">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40" @click="open = false"></div>

    {{-- Panel --}}
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto"
         @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#F2F2F7]">
            <div>
                <h2 class="text-base font-semibold text-[#1D1D1F]">Order Reduction</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5" x-text="reduction ? reduction.catalogue : ''"></p>
            </div>
            <button @click="open = false"
                    class="text-[#86868B] hover:text-[#1D1D1F] transition-colors p-1 rounded-lg hover:bg-[#F5F5F7]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="p-6 space-y-5" x-show="reduction">

            {{-- Reduction details --}}
            <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Date</p>
                    <p class="text-[#1D1D1F] font-medium" x-text="reduction?.date"></p>
                </div>
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Logged By</p>
                    <p class="text-[#1D1D1F] font-medium" x-text="reduction?.logged_by"></p>
                </div>
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Adjustment Type</p>
                    <p class="text-[#1D1D1F] font-medium" x-text="reduction?.adjustment_type"></p>
                </div>
                <div>
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Catalogue</p>
                    <p class="text-[#1D1D1F] font-medium" x-text="reduction?.catalogue"></p>
                </div>
                <div class="col-span-2" x-show="reduction?.notes">
                    <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Notes</p>
                    <p class="text-[#1D1D1F] text-sm" x-text="reduction?.notes"></p>
                </div>
            </div>

            <div class="border-t border-[#F2F2F7]"></div>

            {{-- Items reduced --}}
            <div>
                <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Items Reduced</p>
                <table class="w-full apple-table text-sm">
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
                        <template x-for="item in (reduction?.items ?? [])" :key="item.design + item.size">
                            <tr>
                                <td class="font-medium text-[#1D1D1F]" x-text="item.design"></td>
                                <td class="text-center font-mono text-xs" x-text="item.size"></td>
                                <td class="text-center tabular-nums" x-text="item.qty"></td>
                                <td class="text-right tabular-nums text-[#6E6E73]" x-text="item.unit_price"></td>
                                <td class="text-right tabular-nums font-medium text-[#FF3B30]" x-text="item.amount"></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-[#E8E8ED]">
                            <td colspan="4" class="text-right font-semibold text-[#1D1D1F] py-3 pr-4">Total Reduction</td>
                            <td class="text-right tabular-nums font-semibold text-[#FF3B30] py-3" x-text="reduction?.adjustment_amount"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="border-t border-[#F2F2F7]"></div>

            {{-- Financial impact --}}
            <div class="space-y-2 text-sm">
                <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Financial Impact</p>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Original order total</span>
                    <span class="font-medium tabular-nums text-[#1D1D1F]" x-text="reduction?.original_total"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6E6E73]">Reduction amount</span>
                    <span class="font-medium tabular-nums text-[#FF3B30]" x-text="'− ' + (reduction?.adjustment_amount ?? '')"></span>
                </div>
                <div class="flex justify-between border-t border-[#F2F2F7] pt-2">
                    <span class="font-semibold text-[#1D1D1F]">New order total</span>
                    <span class="font-semibold tabular-nums text-[#1D1D1F]" x-text="reduction?.new_total"></span>
                </div>
            </div>

            {{-- Surplus handling --}}
            <template x-if="reduction?.surplus_action && reduction.surplus_action !== 'none'">
                <div>
                    <div class="border-t border-[#F2F2F7] mb-4"></div>
                    <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Surplus Handling</p>

                    <template x-if="reduction.surplus_action === 'credit_to_advance'">
                        <div class="flex items-center gap-3">
                            <span class="badge bg-purple-100 text-purple-700">Credit to Advance</span>
                            <span class="text-[#6E6E73] text-sm">Surplus was added to customer's advance credit balance.</span>
                        </div>
                    </template>

                    <template x-if="reduction.surplus_action === 'refund'">
                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="badge bg-orange-100 text-orange-700">Refund Issued</span>
                            </div>
                            <template x-if="reduction.refund">
                                <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm mt-2">
                                    <div>
                                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Date</p>
                                        <p class="text-[#1D1D1F] font-medium" x-text="reduction.refund.refund_date"></p>
                                    </div>
                                    <div>
                                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Method</p>
                                        <p class="text-[#1D1D1F] font-medium" x-text="reduction.refund.refund_method"></p>
                                    </div>
                                    <div>
                                        <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Amount</p>
                                        <p class="text-[#FF3B30] font-semibold tabular-nums" x-text="reduction.refund.amount"></p>
                                    </div>
                                    <template x-if="reduction.refund.refund_reference">
                                        <div class="col-span-2">
                                            <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Bank / Reference</p>
                                            <p class="text-[#1D1D1F] font-medium" x-text="reduction.refund.refund_reference"></p>
                                        </div>
                                    </template>
                                    <template x-if="reduction.refund.refund_document">
                                        <div class="col-span-2">
                                            <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-1">Transfer Proof</p>
                                            <template x-if="reduction.refund.doc_is_image">
                                                <a :href="reduction.refund.refund_document" target="_blank">
                                                    <img :src="reduction.refund.refund_document"
                                                         class="max-h-40 rounded-lg border border-[#E8E8ED] object-contain" alt="Transfer proof">
                                                </a>
                                            </template>
                                            <template x-if="!reduction.refund.doc_is_image">
                                                <a :href="reduction.refund.refund_document" target="_blank"
                                                   class="inline-flex items-center gap-2 text-[#0066CC] hover:underline text-sm">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                                                    </svg>
                                                    View Transfer Proof (PDF)
                                                </a>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

        </div>
    </div>
</div>
</template>

</div>{{-- end x-data --}}

@endsection
