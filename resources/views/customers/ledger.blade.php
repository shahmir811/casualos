@extends('layouts.app')

@section('title', $customer->name . ' — Ledger')

@section('content')

<div class="flex items-start justify-between mb-7">
    <div>
        <a href="{{ route('customers.show', $customer) }}" class="text-[#0066CC] text-sm hover:underline">← {{ $customer->name }}</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-3">Customer Ledger</h1>
    </div>
    <div class="stat-card text-right min-w-[180px]">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding Balance</p>
        <p class="text-2xl font-light {{ $balance > 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }}">
            PKR {{ lacs_format(abs($balance), 0) }}
            <span class="text-sm">{{ $balance > 0 ? 'DR' : ($balance < 0 ? 'CR' : '') }}</span>
        </p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Date</th>
                <th class="text-left">Type</th>
                <th class="text-left">Description</th>
                <th class="text-left">Reference</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
            @php
                $typeBadge = [
                    'order_placed'      => 'badge bg-blue-100 text-blue-700',
                    'payment'           => 'badge bg-green-100 text-green-700',
                    'credit_adjustment' => 'badge bg-purple-100 text-purple-700',
                    'order_reduction'   => 'badge bg-yellow-100 text-yellow-700',
                ];
            @endphp
            <tr>
                <td class="text-[#6E6E73] text-xs">{{ $entry->created_at->format('d M Y') }}</td>
                <td>
                    <span class="{{ $typeBadge[$entry->transaction_type] ?? 'badge bg-[#F5F5F7] text-[#6E6E73]' }}">
                        {{ str_replace('_', ' ', $entry->transaction_type) }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-sm">{{ $entry->notes }}</td>
                <td class="text-[#86868B] text-xs">{{ $entry->reference_id ? '#' . $entry->reference_id : '—' }}</td>
                <td class="text-right font-mono text-sm {{ $entry->amount > 0 ? 'text-[#1D1D1F]' : 'text-[#30D158]' }}">
                    {{ $entry->amount > 0 ? '+' : '' }}PKR {{ lacs_format(abs($entry->amount), 0) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-[#86868B] py-12">No ledger entries.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $entries->links() }}</div>

@endsection
