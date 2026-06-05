@extends('layouts.app')
@section('title', 'Tarpai Charges Detail')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('tarpai-charges.index') }}" class="text-[#0066CC] hover:underline text-sm">Tarpai Charges</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">
        {{ $tarpaiPayment->houseLabel() }}
        &middot; {{ $tarpaiPayment->week_start->format('d M') }}–{{ $tarpaiPayment->week_end->format('d M Y') }}
    </span>
</div>

{{-- Header row --}}
<div class="flex items-start justify-between mb-6 gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">{{ $tarpaiPayment->houseLabel() }}</h1>
        <p class="text-[#6E6E73] text-sm mt-1">
            {{ $tarpaiPayment->catalogue->name ?? '—' }} &middot;
            {{ $tarpaiPayment->week_start->format('d M') }} – {{ $tarpaiPayment->week_end->format('d M Y') }}
        </p>
    </div>

    @if(!$tarpaiPayment->is_confirmed)
    <form id="form-confirm" method="POST" action="{{ route('tarpai-charges.confirm', $tarpaiPayment) }}">@csrf</form>
    <button type="button" class="btn-primary"
            @click="$store.confirm.show({
                title: 'Confirm Payment',
                message: `Mark Rs. {{ lacs_format($tarpaiPayment->total_amount, 0) }} as paid to {{ $tarpaiPayment->houseLabel() }}? This cannot be undone.`,
                formId: 'form-confirm',
                confirmText: 'Yes, Confirm Payment'
            })">
        Confirm Payment
    </button>
    @else
    <div class="text-right">
        <span class="badge bg-green-100 text-green-700 text-sm px-3 py-1">Confirmed &amp; Paid</span>
        <p class="text-xs text-[#86868B] mt-1">{{ $tarpaiPayment->confirmed_at?->format('d M Y, g:i A') }}</p>
        <p class="text-xs text-[#86868B] mt-0.5">
            by <span class="font-medium text-[#1D1D1F]">{{ $tarpaiPayment->confirmedBy?->name ?? '—' }}</span>
        </p>
    </div>
    @endif
</div>

{{-- Summary stats --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Pieces Sent</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ lacs_format($tarpaiPayment->total_pieces_sent) }}</p>
        <p class="text-xs text-[#86868B] mt-1">pieces this week</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">House</p>
        <p class="text-xl font-semibold mt-1">
            <span class="badge {{ $tarpaiPayment->houseBadgeClass() }} text-sm px-3 py-1">{{ $tarpaiPayment->houseLabel() }}</span>
        </p>
        <p class="text-xs text-[#86868B] mt-2">{{ $tarpaiPayment->catalogue->name ?? '—' }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Charges</p>
        <p class="text-3xl font-light {{ $tarpaiPayment->is_confirmed ? 'text-green-600' : 'text-orange-500' }}">
            Rs. {{ lacs_format($tarpaiPayment->total_amount, 0) }}
        </p>
        <p class="text-xs text-[#86868B] mt-1">{{ $tarpaiPayment->is_confirmed ? 'paid' : 'pending confirmation' }}</p>
    </div>
</div>

{{-- Per-send breakdown --}}
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-[#E5E5EA]">
        <h2 class="font-semibold text-[#1D1D1F]">Send Breakdown for This Week</h2>
        <p class="text-xs text-[#6E6E73] mt-0.5">Each Tarpai send that falls within this week window</p>
    </div>

    @if($sends->isEmpty())
    <div class="px-5 py-10 text-center text-[#86868B] text-sm">
        No Tarpai send data found for this week window.
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Send Ref</th>
                    <th class="text-left">Date</th>
                    <th class="text-right">Pieces</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sends as $send)
                <tr>
                    <td>
                        <a href="{{ route('tarpai-sends.show', $send->id) }}"
                           class="font-medium text-[#0066CC] hover:underline text-sm">
                            TP-{{ $send->id }}
                        </a>
                    </td>
                    <td class="text-[#6E6E73]">{{ \Carbon\Carbon::parse($send->sent_date)->format('d M Y') }}</td>
                    <td class="text-right">{{ lacs_format($send->pieces) }} pcs</td>
                    <td class="text-right text-[#6E6E73] text-xs">Rs. {{ lacs_format($send->per_piece_price, 0) }}/pc</td>
                    <td class="text-right font-semibold">Rs. {{ lacs_format($send->amount, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
            @php
                $grandPieces = $sends->sum('pieces');
                $grandAmount = $sends->sum('amount');
            @endphp
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td class="text-right">{{ lacs_format($grandPieces) }} pcs</td>
                    <td></td>
                    <td class="text-right">Rs. {{ lacs_format($grandAmount, 0) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Formula callout --}}
    <div class="px-5 py-4 bg-[#F5F5F7] border-t border-[#E5E5EA] text-sm text-[#6E6E73]">
        @foreach($sends as $send)
        <span class="inline-block mr-4">
            TP-{{ $send->id }}: {{ lacs_format($send->pieces) }} pcs
            &times; Rs. {{ lacs_format($send->per_piece_price, 0) }}/pc
            = <strong class="text-[#1D1D1F]">Rs. {{ lacs_format($send->amount, 0) }}</strong>
        </span>
        @endforeach
        @if($sends->count() > 1)
        <div class="mt-2 pt-2 border-t border-[#D2D2D7] font-medium text-[#1D1D1F]">
            Total: {{ lacs_format($grandPieces) }} pieces = Rs. {{ lacs_format($grandAmount, 0) }}
        </div>
        @endif
    </div>
    @endif
</div>

@endsection
