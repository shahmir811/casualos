@extends('layouts.app')
@section('title', 'Tarpai Finishing')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Tarpai Finishing</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Track Kameez pieces sent for tarpai and returns</p>
    </div>
    <a href="{{ route('tarpai-sends.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Log Send
    </a>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Send #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">House</th>
                <th class="text-left">Sent Date</th>
                <th class="text-right">Sent</th>
                <th class="text-right">Returned</th>
                <th class="text-right">Rate</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sends as $send)
            @php
                $sent        = $send->items->sum('quantity');
                $returned    = $send->returns->flatMap->items->sum('quantity');
                $outstanding = max(0, $sent - $returned);
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">TP-{{ str_pad($send->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $send->catalogue->name ?? '—' }}</td>
                <td>
                    <span class="badge {{ $send->tarpai_house === 'rashid_bhai' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' }}">
                        {{ $send->tarpaiHouseLabel() }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $send->sent_date->format('d M Y') }}</td>
                <td class="text-right">{{ number_format($sent) }}</td>
                <td class="text-right text-green-700">{{ number_format($returned) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">Rs. {{ number_format($send->per_piece_price, 0) }}/pc</td>
                <td>
                    @if($outstanding === 0 && $sent > 0)
                        <span class="badge bg-green-100 text-green-700">Complete</span>
                    @elseif($outstanding > 0)
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @else
                        <span class="badge bg-[#F5F5F7] text-[#6E6E73]">—</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('tarpai.gate-pass', $send) }}" target="_blank"
                           class="text-[#6E6E73] hover:text-[#1D1D1F] text-xs flex items-center gap-1" title="Print Gate Pass">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Gate Pass
                        </a>
                        <a href="{{ route('tarpai-sends.show', $send) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-[#86868B] py-12">No tarpai sends recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $sends->links() }}</div>

@endsection
