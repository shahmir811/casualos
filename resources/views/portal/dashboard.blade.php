<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Casualite</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

@php
    // Pakistani lakhs format: 1,87,500 instead of 187,500
    function formatPKR(float|int $amount): string {
        $n = (int) abs($amount);
        $s = (string) $n;
        if (strlen($s) <= 3) return 'Rs ' . $s;
        $last3 = substr($s, -3);
        $rest   = substr($s, 0, -3);
        $parts  = [];
        while ($rest !== '') {
            $parts[] = substr($rest, -2);
            $rest = strlen($rest) > 2 ? substr($rest, 0, -2) : '';
        }
        return 'Rs ' . implode(',', array_reverse($parts)) . ',' . $last3;
    }

    $totalOutstanding = $customer->orders->sum('outstanding_balance');
    $totalOrders      = $customer->orders->count();
    $advance          = $customer->advance_credit_balance ?? 0;

    $statusLabels = [
        'received'             => 'Order Received',
        'confirmed'            => 'Order Confirmed',
        'stitching'            => 'Being Stitched',
        'partially_dispatched' => 'Partially Dispatched',
        'dispatched'           => 'Dispatched',
    ];
    $badgeMap = [
        'received'             => 'bg-blue-100 text-blue-700',
        'confirmed'            => 'bg-yellow-100 text-yellow-700',
        'stitching'            => 'bg-orange-100 text-orange-700',
        'partially_dispatched' => 'bg-purple-100 text-purple-700',
        'dispatched'           => 'bg-green-100 text-green-700',
    ];
    $activityLabels = [
        'order_charged'      => 'Order Placed',
        'payment_received'   => 'Payment Received',
        'credit_applied'     => 'Advance Credit Applied',
        'order_reduced'      => 'Order Adjusted',
        'surplus_to_advance' => 'Moved to Account Credit',
    ];
    // Ledger entries not tied to a specific order (shown separately)
    $generalLedger = $customer->ledger->filter(
        fn($e) => $e->transaction_type === 'advance_received'
    )->sortByDesc('created_at');
@endphp

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
        <span class="text-[#0071E3] text-xs font-medium">Casualite</span>
    </div>

    <div class="max-w-lg mx-auto px-4 mt-5 space-y-4">

        {{-- Action Card --}}
        <div class="card px-5 py-5">
            @if($totalOutstanding > 0)
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 bg-red-50 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-[#FF3B30]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[#86868B] text-xs font-medium mb-0.5">Amount Due</p>
                        <p class="text-[#FF3B30] text-2xl font-bold tracking-tight">{{ formatPKR($totalOutstanding) }}</p>
                        <p class="text-[#6E6E73] text-xs mt-1">Please contact Casualite to clear your balance.</p>
                    </div>
                </div>
            @else
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 bg-green-50 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-[#30D158]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[#86868B] text-xs font-medium mb-0.5">Payment Status</p>
                        <p class="text-[#30D158] text-2xl font-bold tracking-tight">All Paid Up!</p>
                        <p class="text-[#6E6E73] text-xs mt-1">You have no outstanding balance. Thank you!</p>
                    </div>
                </div>
            @endif

            @if($advance > 0)
            <div class="mt-4 pt-4 border-t border-[#F2F2F7] flex items-center justify-between">
                <p class="text-[#6E6E73] text-xs">Advance Credit with Casualite</p>
                <p class="text-[#30D158] text-sm font-semibold">{{ formatPKR($advance) }}</p>
            </div>
            @endif

            <div class="mt-4 pt-4 border-t border-[#F2F2F7] flex items-center justify-between">
                <p class="text-[#6E6E73] text-xs">Total Orders Placed</p>
                <p class="text-[#1D1D1F] text-sm font-semibold">{{ $totalOrders }}</p>
            </div>
        </div>

        {{-- Orders list --}}
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#86868B] mb-2 px-1">Your Orders</p>
            <div class="space-y-3">

                @forelse($customer->orders->sortByDesc('created_at') as $order)
                @php
                    $pieceCount = $order->items->sum(
                        fn($i) => $i->qty_xs + $i->qty_s + $i->qty_m + $i->qty_l + $i->qty_xl
                    );

                    // Collect this order's payment IDs for ledger filtering
                    $orderPaymentIds = $order->payments->pluck('id');

                    // Filter ledger entries that belong to this order
                    $orderActivity = $customer->ledger->filter(function ($entry) use ($order, $orderPaymentIds) {
                        if ($entry->reference_type === 'App\Models\Order' && (int) $entry->reference_id === $order->id) {
                            return true;
                        }
                        if ($entry->reference_type === 'App\Models\Payment') {
                            return $orderPaymentIds->contains((int) $entry->reference_id);
                        }
                        return false;
                    })->sortBy('created_at');
                @endphp

                <div class="card overflow-hidden" x-data="{ open: false }">

                    {{-- Collapsed header — always visible --}}
                    <button @click="open = !open" class="w-full px-5 py-4 flex items-center justify-between text-left gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="mb-1">
                                <span class="badge {{ $badgeMap[$order->status] ?? 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                                    {{ $statusLabels[$order->status] ?? ucfirst($order->status) }}
                                </span>
                            </div>
                            <p class="text-[#1D1D1F] text-sm font-semibold">{{ $order->catalogue->name ?? '—' }}</p>
                            <p class="text-[#86868B] text-xs mt-0.5">{{ $order->created_at->format('d M Y') }} · {{ $pieceCount }} pieces</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-[#1D1D1F] text-sm font-semibold">{{ formatPKR($order->total_amount) }}</p>
                            @if($order->outstanding_balance > 0)
                                <p class="text-[#FF3B30] text-xs mt-0.5">{{ formatPKR($order->outstanding_balance) }} due</p>
                            @elseif($order->total_paid >= $order->total_amount)
                                <p class="text-[#30D158] text-xs mt-0.5">Paid ✓</p>
                            @endif
                            <svg class="w-4 h-4 text-[#C7C7CC] mt-2 ml-auto transition-transform duration-200"
                                 :class="{ 'rotate-180': open }"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </button>

                    {{-- Expanded detail --}}
                    <div x-show="open" x-transition class="border-t border-[#F2F2F7]">
                        <div class="px-5 py-4 space-y-4">

                            {{-- Payment summary --}}
                            <div class="bg-[#F5F5F7] rounded-xl p-3 grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-[#86868B] text-xs mb-0.5">Paid So Far</p>
                                    <p class="text-[#1D1D1F] text-sm font-semibold">{{ formatPKR($order->total_paid) }}</p>
                                </div>
                                <div>
                                    <p class="text-[#86868B] text-xs mb-0.5">Still Owed</p>
                                    <p class="text-sm font-semibold {{ $order->outstanding_balance > 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }}">
                                        @if($order->outstanding_balance > 0)
                                            {{ formatPKR($order->outstanding_balance) }}
                                        @else
                                            Nothing ✓
                                        @endif
                                    </p>
                                </div>
                            </div>

                            {{-- Order activity --}}
                            @if($orderActivity->isNotEmpty())
                            <div>
                                <p class="text-[#86868B] text-xs font-medium uppercase tracking-wide mb-2">Activity</p>
                                <div class="space-y-0 divide-y divide-[#F2F2F7] rounded-xl overflow-hidden border border-[#F2F2F7]">
                                    @foreach($orderActivity as $entry)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2.5 bg-white">
                                        <div class="min-w-0">
                                            <p class="text-[#1D1D1F] text-xs font-medium">
                                                {{ $activityLabels[$entry->transaction_type] ?? ucwords(str_replace('_', ' ', $entry->transaction_type)) }}
                                            </p>
                                            <p class="text-[#86868B] text-[10px] mt-0.5">{{ $entry->created_at->format('d M Y') }}</p>
                                        </div>
                                        <span class="text-xs font-semibold flex-shrink-0 text-[#1D1D1F]">
                                            {{ formatPKR(abs($entry->amount)) }}
                                        </span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- Pieces ordered --}}
                            @if($order->items->isNotEmpty())
                            <div>
                                <p class="text-[#86868B] text-xs font-medium uppercase tracking-wide mb-2">Pieces Ordered</p>
                                <div class="space-y-2">
                                    @foreach($order->items as $item)
                                    @php
                                        $itemTotal = $item->qty_xs + $item->qty_s + $item->qty_m + $item->qty_l + $item->qty_xl;
                                    @endphp
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg overflow-hidden flex-shrink-0 bg-[#E8E8ED]">
                                            @if($item->design?->photo)
                                                <img src="{{ Storage::url($item->design->photo) }}" alt="{{ $item->design->name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-[#C7C7CC]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-[#1D1D1F] text-xs font-medium truncate">{{ $item->design?->name ?? 'Design' }}</p>
                                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                                @foreach(['xs' => 'XS', 's' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'] as $key => $label)
                                                @php $qty = $item->{'qty_' . $key}; @endphp
                                                @if($qty > 0)
                                                    <span class="text-[10px] text-[#6E6E73]">{{ $label }} <strong class="text-[#1D1D1F]">{{ $qty }}</strong></span>
                                                @endif
                                                @endforeach
                                                <span class="ml-auto text-[10px] text-[#86868B]">{{ $itemTotal }} pcs</span>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                </div>
                @empty
                <div class="card px-5 py-10 text-center text-[#86868B] text-sm">
                    No orders found.
                </div>
                @endforelse

            </div>
        </div>

        {{-- General account activity (advance payments not tied to a specific order) --}}
        @if($generalLedger->isNotEmpty())
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-[#86868B] mb-2 px-1">Account Credits</p>
            <div class="card overflow-hidden divide-y divide-[#F2F2F7]">
                @foreach($generalLedger as $entry)
                <div class="px-5 py-3.5 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[#1D1D1F] text-xs font-medium">Advance Payment Received</p>
                        <p class="text-[#86868B] text-xs mt-0.5">{{ $entry->created_at->format('d M Y') }}</p>
                    </div>
                    <span class="text-sm font-semibold text-[#30D158] flex-shrink-0">
                        {{ formatPKR(abs($entry->amount)) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    <p class="text-center text-[#C7C7CC] text-xs mt-8">
        © {{ date('Y') }} Casualite · Powered by CasualiteOS
    </p>

</body>
</html>
