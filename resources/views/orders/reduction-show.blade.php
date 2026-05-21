@extends('layouts.app')
@section('title', 'Reduction — Order #' . $order->order_number)

@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('orders.index') }}" class="text-[#0066CC] hover:underline text-sm">Orders</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('orders.show', $order) }}" class="text-[#0066CC] hover:underline text-sm">#{{ $order->order_number }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Reduction #{{ $reduction->id }}</span>
</div>

<div class="max-w-2xl space-y-5">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Order Reduction</h1>
        <p class="text-[#6E6E73] text-sm mt-1">
            Order #{{ $order->order_number }} · {{ $order->customer->name ?? '—' }} · {{ $order->catalogue->name ?? '—' }}
        </p>
    </div>

    {{-- Summary card --}}
    <div class="card p-6 space-y-4">
        <h2 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Reduction Details</h2>

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

    {{-- Items reduced --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#F2F2F7]">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Items Reduced</h2>
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
                    <td class="text-right tabular-nums text-[#6E6E73]">PKR {{ number_format($item->unit_price, 0) }}</td>
                    <td class="text-right tabular-nums font-medium text-[#FF3B30]">PKR {{ number_format($item->amount_reduced, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-[#E8E8ED]">
                    <td colspan="4" class="text-right font-semibold text-[#1D1D1F] py-3 pr-4">Total Reduction</td>
                    <td class="text-right tabular-nums font-semibold text-[#FF3B30] py-3">PKR {{ number_format($reduction->adjustment_amount, 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Financial impact --}}
    <div class="card p-6 space-y-3 text-sm">
        <h2 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Financial Impact</h2>
        <div class="flex justify-between">
            <span class="text-[#6E6E73]">Original order total</span>
            <span class="font-medium tabular-nums text-[#1D1D1F]">PKR {{ number_format($reduction->original_total, 0) }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-[#6E6E73]">Reduction amount</span>
            <span class="font-medium tabular-nums text-[#FF3B30]">− PKR {{ number_format($reduction->adjustment_amount, 0) }}</span>
        </div>
        <div class="flex justify-between border-t border-[#F2F2F7] pt-2">
            <span class="font-semibold text-[#1D1D1F]">New order total</span>
            <span class="font-semibold tabular-nums text-[#1D1D1F]">PKR {{ number_format($reduction->new_total, 0) }}</span>
        </div>
    </div>

    {{-- Surplus action --}}
    @if($reduction->surplus_action && $reduction->surplus_action !== 'none')
    <div class="card p-6 space-y-3 text-sm">
        <h2 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-3">Surplus Handling</h2>

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
            <div>
                <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Refund Amount</p>
                <p class="text-[#FF3B30] font-semibold tabular-nums">PKR {{ number_format($refund->amount, 0) }}</p>
            </div>
            @if($refund->refund_method === 'bank_transfer' && $refund->refund_reference)
            <div class="col-span-2">
                <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-0.5">Bank / Reference</p>
                <p class="text-[#1D1D1F] font-medium">{{ $refund->refund_reference }}</p>
            </div>
            @endif
            @if($refund->refund_method === 'bank_transfer' && $refund->refund_document)
            @php $ext = strtolower(pathinfo($refund->refund_document, PATHINFO_EXTENSION)); @endphp
            <div class="col-span-2">
                <p class="text-[#6E6E73] text-xs uppercase tracking-widest mb-1">Transfer Proof</p>
                @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                    <a href="{{ Storage::url($refund->refund_document) }}" target="_blank">
                        <img src="{{ Storage::url($refund->refund_document) }}"
                             class="max-h-48 rounded-lg border border-[#E8E8ED] object-contain" alt="Transfer proof">
                    </a>
                @else
                    <a href="{{ Storage::url($refund->refund_document) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-[#0066CC] hover:underline text-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                        </svg>
                        View Transfer Proof (PDF)
                    </a>
                @endif
            </div>
            @endif
        </div>
        @endif
        @endif
    </div>
    @endif

    <div>
        <a href="{{ route('orders.show', $order) }}" class="btn-secondary">← Back to Order</a>
    </div>

</div>

@endsection
