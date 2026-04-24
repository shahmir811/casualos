<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Casual Lite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #F5F5F7;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #E8E8ED;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-label { font-size: 0.75rem; color: #86868B; font-weight: 500; }
        .stat-value { font-size: 1.375rem; font-weight: 700; color: #1D1D1F; line-height: 1.2; margin-top: 0.2rem; }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 20px;
            padding: 0.2rem 0.65rem;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }
    </style>
</head>
<body class="min-h-screen pb-12">

    {{-- Top bar --}}
    <div class="bg-white border-b border-[#E8E8ED] px-5 py-4 flex items-center justify-between sticky top-0 z-10">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-[#1D1D1F] rounded-xl flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <p class="text-[#1D1D1F] text-sm font-semibold leading-tight">{{ $customer->name }}</p>
                <p class="text-[#86868B] text-xs">{{ $customer->city }}</p>
            </div>
        </div>
        <span class="text-[#0071E3] text-xs font-medium">Casual Lite</span>
    </div>

    <div class="max-w-lg mx-auto px-4 mt-5 space-y-4">

        {{-- Summary stats --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="card px-4 py-4 text-center">
                <div class="stat-label">Orders</div>
                <div class="stat-value text-xl">{{ $customer->orders->count() }}</div>
            </div>
            <div class="card px-4 py-4 text-center">
                @php
                    $outstanding = $customer->orders->sum('outstanding_balance');
                @endphp
                <div class="stat-label">Outstanding</div>
                <div class="stat-value text-xl {{ $outstanding > 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }}">
                    PKR {{ number_format($outstanding, 0) }}
                </div>
            </div>
            <div class="card px-4 py-4 text-center">
                <div class="stat-label">Advance</div>
                <div class="stat-value text-xl {{ $customer->advance_credit_balance > 0 ? 'text-[#30D158]' : '' }}">
                    PKR {{ number_format($customer->advance_credit_balance ?? 0, 0) }}
                </div>
            </div>
        </div>

        {{-- Orders list --}}
        <div class="card overflow-hidden">
            <div class="px-5 pt-5 pb-3 border-b border-[#F2F2F7]">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#86868B]">Your Orders</p>
            </div>

            @forelse($customer->orders->sortByDesc('created_at') as $order)
            @php
                $badgeMap = [
                    'received'   => 'bg-blue-100 text-blue-700',
                    'confirmed'  => 'bg-yellow-100 text-yellow-700',
                    'stitching'  => 'bg-orange-100 text-orange-700',
                    'dispatched' => 'bg-green-100 text-green-700',
                ];
            @endphp
            <div class="px-5 py-4 border-b border-[#F2F2F7] last:border-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[#1D1D1F] text-sm font-semibold">#{{ $order->id }}</span>
                            <span class="badge {{ $badgeMap[$order->status] ?? 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                                {{ ucfirst($order->status) }}
                            </span>
                        </div>
                        <p class="text-[#6E6E73] text-xs truncate">{{ $order->catalogue->name ?? '—' }}</p>
                        <p class="text-[#86868B] text-xs mt-0.5">{{ $order->created_at->format('d M Y') }}</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-[#1D1D1F] text-sm font-semibold">PKR {{ number_format($order->total_amount, 0) }}</p>
                        @if($order->outstanding_balance > 0)
                        <p class="text-[#FF3B30] text-xs mt-0.5">
                            PKR {{ number_format($order->outstanding_balance, 0) }} due
                        </p>
                        @elseif($order->total_paid >= $order->total_amount)
                        <p class="text-[#30D158] text-xs mt-0.5">Paid ✓</p>
                        @endif
                    </div>
                </div>

                {{-- Items breakdown --}}
                @if($order->items->isNotEmpty())
                <div class="mt-3 grid grid-cols-5 gap-1.5">
                    @foreach($order->items as $item)
                    <div class="bg-[#F5F5F7] rounded-lg p-2 text-center">
                        <div class="w-8 h-8 rounded-md overflow-hidden mx-auto mb-1 bg-[#E8E8ED]">
                            @if($item->design?->photo)
                                <img src="{{ Storage::url($item->design->photo) }}" alt="{{ $item->design->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                        </div>
                        <p class="text-[#1D1D1F] text-xs font-semibold leading-none">{{ $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl }}</p>
                        <p class="text-[#86868B] text-[10px] truncate">pcs</p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @empty
            <div class="px-5 py-10 text-center text-[#86868B] text-sm">
                No orders found.
            </div>
            @endforelse
        </div>

        {{-- Ledger --}}
        @if($customer->ledger->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="px-5 pt-5 pb-3 border-b border-[#F2F2F7]">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#86868B]">Account Activity</p>
            </div>
            @foreach($customer->ledger->sortByDesc('created_at')->take(10) as $entry)
            <div class="px-5 py-3.5 border-b border-[#F2F2F7] last:border-0 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[#1D1D1F] text-xs font-medium truncate">{{ $entry->notes ?? ucwords(str_replace('_', ' ', $entry->transaction_type)) }}</p>
                    <p class="text-[#86868B] text-xs mt-0.5">{{ $entry->created_at->format('d M Y') }}</p>
                </div>
                <span class="text-sm font-semibold flex-shrink-0 {{ $entry->amount > 0 ? 'text-[#30D158]' : 'text-[#1D1D1F]' }}">
                    {{ $entry->amount > 0 ? '+' : '' }}PKR {{ number_format(abs($entry->amount), 0) }}
                </span>
            </div>
            @endforeach
        </div>
        @endif

    </div>

    <p class="text-center text-[#C7C7CC] text-xs mt-8">
        © {{ date('Y') }} Casual Lite · Powered by CasualOS
    </p>

</body>
</html>
