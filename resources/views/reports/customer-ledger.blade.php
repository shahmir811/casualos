@extends('layouts.app')
@section('title', 'Customer Ledger')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Customer Ledger</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Customer Ledger</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Full payment and credit history per customer</p>
</div>

<form method="GET"
      class="flex items-center gap-3 mb-6"
      x-data="{
        customers: {{ Js::from($customers->map(fn($c) => ['id' => $c->id, 'label' => $c->name])) }},
        customerId: '{{ request('customer_id', '') }}',
        search: @js(optional($selectedCustomer)->name ?? ''),
        open: false,
        get filtered() {
            const s = this.search.toLowerCase();
            return this.customers.filter(c => c.label.toLowerCase().includes(s));
        },
        selectCustomer(c) {
            this.customerId = c.id; this.search = c.label; this.open = false;
        }
      }">
    <div class="relative" style="width:300px;" @click.outside="open = false">
        <input type="text"
               x-model="search"
               @focus="open = true"
               @input="open = true; customerId = ''"
               autocomplete="off"
               placeholder="Type customer name..."
               class="apple-input w-full">
        <input type="hidden" name="customer_id" :value="customerId">
        <div x-show="open" x-cloak
             class="absolute z-20 mt-1 w-full bg-white border border-[#E5E5E7] rounded-lg shadow-lg max-h-56 overflow-y-auto">
            <template x-for="c in filtered" :key="c.id">
                <div @click="selectCustomer(c)"
                     class="px-4 py-2 text-sm text-[#1D1D1F] cursor-pointer hover:bg-[#F5F5F7]"
                     x-text="c.label"></div>
            </template>
            <p x-show="filtered.length === 0" class="px-4 py-2 text-sm text-[#6E6E73]">No matches</p>
        </div>
    </div>
    <button type="submit" class="btn-primary" :disabled="!customerId" :style="customerId ? '' : 'opacity:0.5;cursor:not-allowed;'">Load Ledger</button>
    @if(request('customer_id'))
    <a href="{{ route('reports.customer-ledger') }}" class="btn-secondary">Clear</a>
    @endif
</form>

@if($selectedCustomer)

{{-- Balance Summary --}}
@php
    $credits = $entries->whereIn('transaction_type', ['advance_received', 'payment_received', 'credit_applied'])->sum(fn($e) => abs($e->amount));
    $debits  = $entries->whereIn('transaction_type', ['order_charged', 'order_reduced'])->sum(fn($e) => abs($e->amount));
    $netBalance = $balance; // signed balance from controller
@endphp
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Charged</p><p class="text-2xl font-light text-[#1D1D1F]">PKR {{ number_format($debits, 0) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Paid</p><p class="text-2xl font-light text-green-600">PKR {{ number_format($credits, 0) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Net Balance</p>
        <p class="text-2xl font-light {{ $netBalance < 0 ? 'text-[#FF3B30]' : 'text-green-600' }}">
            PKR {{ number_format(abs($netBalance), 0) }}
            <span class="text-sm font-normal">{{ $netBalance < 0 ? '(owed)' : '(credit)' }}</span>
        </p>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-sm font-semibold text-[#1D1D1F]">{{ $selectedCustomer->name }} — Ledger</h2>
        <a href="{{ route('customers.ledger', $selectedCustomer) }}" class="text-[#0066CC] text-xs hover:underline">Full Ledger →</a>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Date</th>
                <th class="text-left">Type</th>
                <th class="text-left">Payment #</th>
                <th class="text-left">Notes</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
            @php
                $typeColors = [
                    'advance_received'  => 'bg-green-100 text-green-700',
                    'order_charged'     => 'bg-red-100 text-red-700',
                    'payment_received'  => 'bg-green-100 text-green-700',
                    'credit_applied'    => 'bg-blue-100 text-blue-700',
                    'order_reduced'     => 'bg-yellow-100 text-yellow-700',
                    'refund_issued'     => 'bg-purple-100 text-purple-700',
                ];
                $typeLabels = [
                    'advance_received'  => 'Advance Received',
                    'order_charged'     => 'Order Charged',
                    'payment_received'  => 'Payment Received',
                    'credit_applied'    => 'Credit Applied',
                    'order_reduced'     => 'Order Reduced',
                    'refund_issued'     => 'Refund Issued',
                ];
            @endphp
            <tr>
                <td class="text-[#6E6E73] text-xs">{{ $entry->created_at->format('d M Y') }}</td>
                <td><span class="badge {{ $typeColors[$entry->transaction_type] ?? 'bg-[#F5F5F7] text-[#6E6E73]' }}">{{ $typeLabels[$entry->transaction_type] ?? $entry->transaction_type }}</span></td>
                <td class="text-[#6E6E73] text-xs font-mono">
                    @if($entry->transaction_type === 'payment_received' && !empty($paymentMap[$entry->reference_id]))
                        #{{ $paymentMap[$entry->reference_id] }}
                    @else
                        —
                    @endif
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $entry->notes ?? '—' }}</td>
                <td class="text-right font-medium {{ $entry->amount < 0 ? 'text-[#FF3B30]' : 'text-green-600' }}">
                    {{ $entry->amount < 0 ? '-' : '+' }}PKR {{ number_format(abs($entry->amount), 0) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-[#86868B] py-8">No ledger entries found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@else
<div class="card p-12 text-center">
    <svg class="w-10 h-10 mx-auto mb-3 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
    <p class="text-[#86868B]">Select a customer above to view their ledger.</p>
</div>
@endif

@endsection
