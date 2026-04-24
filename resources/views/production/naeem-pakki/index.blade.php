@extends('layouts.app')
@section('title', 'Naeem Pakki')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Naeem Pakki Sends</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Track embroidery batches sent and returned</p>
    </div>
    <a href="{{ route('naeem-pakki-sends.create') }}" class="btn-primary">
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
                <th class="text-left">Design</th>
                <th class="text-left">Sent Date</th>
                <th class="text-right">Sent</th>
                <th class="text-right">Returned</th>
                <th class="text-right">Outstanding</th>
                <th class="text-right">Rate</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sends as $send)
            @php
                $sent       = $send->items->sum('quantity');
                $returned   = $send->returns->flatMap->items->sum('quantity');
                $outstanding = max(0, $sent - $returned);
                $done = $outstanding === 0 && $sent > 0;
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">NP-{{ str_pad($send->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $send->assignment?->design?->name ?? '—' }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $send->sent_date->format('d M Y') }}</td>
                <td class="text-right">{{ number_format($sent) }}</td>
                <td class="text-right text-green-700">{{ number_format($returned) }}</td>
                <td class="text-right {{ $outstanding > 0 ? 'text-orange-600 font-semibold' : 'text-[#86868B]' }}">{{ number_format($outstanding) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">Rs. {{ number_format($send->per_piece_price, 0) }}/pc</td>
                <td>
                    @if($done)
                        <span class="badge bg-green-100 text-green-700">Complete</span>
                    @elseif($outstanding > 0)
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @else
                        <span class="badge bg-[#F5F5F7] text-[#6E6E73]">—</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('naeem-pakki-sends.show', $send) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-[#86868B] py-12">No Naeem Pakki sends recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $sends->links() }}</div>

@endsection
