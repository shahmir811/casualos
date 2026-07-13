@extends('layouts.app')
@section('title', 'Dispatch')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Dispatch</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Orders in stitching status ready for dispatch</p>
</div>

{{-- Search & Filters --}}
<div class="card p-4 mb-6">
    <form method="GET" action="{{ route('dispatch.index') }}">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px] max-w-sm">
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search by customer name…"
                       class="apple-input w-full">
            </div>
            <div class="min-w-[160px]">
                <select name="payment_status" class="apple-input w-full">
                    <option value="">All Payment Statuses</option>
                    <option value="not_paid" @selected($paymentStatus === 'not_paid')>Not Paid</option>
                    <option value="partially_paid" @selected($paymentStatus === 'partially_paid')>Partially Paid</option>
                    <option value="fully_paid" @selected($paymentStatus === 'fully_paid')>Fully Paid</option>
                </select>
            </div>
            <div class="min-w-[160px]">
                <select name="dispatch_status" class="apple-input w-full">
                    <option value="">All Dispatch Statuses</option>
                    <option value="pending" @selected($dispatchStatus === 'pending')>Pending</option>
                    <option value="partial" @selected($dispatchStatus === 'partial')>Partial</option>
                    <option value="complete" @selected($dispatchStatus === 'complete')>Complete</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-[#86868B] mb-1">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="apple-input">
            </div>
            <div>
                <label class="block text-xs text-[#86868B] mb-1">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="apple-input">
            </div>
            <button type="submit" class="btn-primary">Filter</button>
            @if($search || $paymentStatus || $dispatchStatus || $dateFrom || $dateTo)
                <a href="{{ route('dispatch.index') }}" class="btn-secondary">Clear</a>
            @endif
        </div>
    </form>
</div>

{{-- Mobile cards --}}
<div class="card overflow-hidden sm:hidden">
    @forelse($orders as $order)
    @php
        $totalPaid       = (float) $order->total_paid;
        $outstanding     = (float) $order->outstanding_balance;
        $totalOrdered    = $order->items->sum('total_qty');
        $totalDispatched = $order->dispatchBatches->flatMap->items->sum('quantity');
        $hasBatches      = $order->dispatchBatches->count() > 0;
    @endphp
    <div class="px-5 py-4 border-b border-[#F2F2F7] last:border-b-0">
        <div class="flex items-start justify-between gap-3 mb-1.5">
            <span class="font-semibold text-[#1D1D1F]">#{{ $order->order_number }}</span>
            <div class="flex items-center gap-1.5 shrink-0">
                @if($totalPaid <= 0)
                    <span class="badge bg-[#F5F5F7] text-[#86868B]">Not Paid</span>
                @elseif($outstanding > 0)
                    <span class="badge bg-orange-100 text-orange-700">Par. Paid</span>
                @else
                    <span class="badge bg-green-100 text-green-700">Fully Paid</span>
                @endif
                @if(!$hasBatches)
                    <span class="badge bg-[#F5F5F7] text-[#86868B]">Pending</span>
                @elseif($totalDispatched >= $totalOrdered)
                    <span class="badge bg-green-100 text-green-700">Complete</span>
                @else
                    <span class="badge bg-orange-100 text-orange-700">Partial</span>
                @endif
            </div>
        </div>
        <p class="text-sm text-[#6E6E73] mb-1">{{ $order->customer->name ?? '—' }}</p>
        <div class="flex items-center justify-between mt-2">
            <span class="text-sm font-semibold text-[#1D1D1F]">PKR {{ number_format($order->total_amount, 0) }}</span>
            <a href="{{ route('dispatch.show', $order) }}" class="btn-primary text-xs">View →</a>
        </div>
    </div>
    @empty
    <div class="p-12 text-center text-[#86868B]">
        <svg class="w-8 h-8 mx-auto mb-3 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
        No orders ready for dispatch.
    </div>
    @endforelse
</div>

{{-- Desktop table --}}
<div class="card overflow-hidden hidden sm:block">
    <div class="overflow-x-auto">
    <table class="w-full apple-table min-w-[580px]">
        <thead>
            <tr>
                <th class="text-left">Order #</th>
                <th class="text-left">Customer</th>
                <th class="text-left">Amount</th>
                <th class="text-left">Payment</th>
                <th class="text-left">Dispatch</th>
                <th class="text-left">Confirmed Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
            @php
                $totalPaid       = (float) $order->total_paid;
                $outstanding     = (float) $order->outstanding_balance;
                $totalOrdered    = $order->items->sum('total_qty');
                $totalDispatched = $order->dispatchBatches->flatMap->items->sum('quantity');
                $hasBatches      = $order->dispatchBatches->count() > 0;
            @endphp
            <tr>
                <td class="font-medium">#{{ $order->order_number }}</td>
                <td>{{ $order->customer->name ?? '—' }}</td>
                <td>PKR {{ number_format($order->total_amount, 0) }}</td>
                <td>
                    @if($totalPaid <= 0)
                        <span class="badge bg-[#F5F5F7] text-[#86868B]">Not Paid</span>
                    @elseif($outstanding > 0)
                        <span class="badge bg-orange-100 text-orange-700">Partially Paid</span>
                    @else
                        <span class="badge bg-green-100 text-green-700">Fully Paid</span>
                    @endif
                </td>
                <td>
                    @if(!$hasBatches)
                        <span class="badge bg-[#F5F5F7] text-[#86868B]">Pending</span>
                    @elseif($totalDispatched >= $totalOrdered)
                        <span class="badge bg-green-100 text-green-700">Complete</span>
                    @else
                        <span class="badge bg-orange-100 text-orange-700">Partial</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $order->updated_at->format('d M Y') }}</td>
                <td>
                    <a href="{{ route('dispatch.show', $order) }}" class="btn-primary text-xs">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">
                    <svg class="w-8 h-8 mx-auto mb-3 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                    No orders ready for dispatch.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-5">{{ $orders->links() }}</div>

@endsection
