@extends('layouts.app')
@section('title', 'Press')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Press</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Track pieces sent to and returned from the press unit</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('packed-inventory.index') }}" class="btn-secondary">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
            Packed Inventory
        </a>
        <a href="{{ route('press-sends.create') }}" class="btn-primary">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Log Press Send
        </a>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Send #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Sent Date</th>
                <th class="text-right">Sent</th>
                <th class="text-right">Returned</th>
                <th class="text-right">Outstanding</th>
                <th class="text-left">Logged By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($sends as $send)
            @php
                $totalSent      = $send->items->sum('quantity');
                $totalReturned  = $send->returns->flatMap->items->sum('quantity');
                $outstanding    = max(0, $totalSent - $totalReturned);
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">PS-{{ str_pad($send->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $send->catalogue->name ?? '—' }}</td>
                <td>{{ $send->sent_date->format('d M Y') }}</td>
                <td class="text-right">{{ number_format($totalSent) }} pcs</td>
                <td class="text-right">{{ number_format($totalReturned) }} pcs</td>
                <td class="text-right">
                    @if($outstanding > 0)
                        <span class="badge badge-warning">{{ number_format($outstanding) }} pcs</span>
                    @else
                        <span class="badge badge-success">Done</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $send->loggedBy->name ?? '—' }}</td>
                <td class="text-right">
                    <a href="{{ route('press-sends.show', $send) }}" class="text-[#0066CC] text-sm hover:underline">View</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-[#86868B] py-12">No press sends recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $sends->links() }}</div>

@endsection
