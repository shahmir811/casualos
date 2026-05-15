@extends('layouts.app')
@section('title', 'Press')
@section('content')

<div class="flex items-center justify-between mb-6">
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

{{-- ── Filter bar ──────────────────────────────────────────────────── --}}
<div class="card p-4 mb-6">
    <div class="flex flex-wrap items-end gap-x-6 gap-y-4">

        <form method="GET" action="{{ route('press-sends.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full sm:w-auto">
                <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Design</p>
                <select name="design_id"
                        onchange="this.form.submit();"
                        class="w-full sm:w-auto apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
                    <option value="">All designs</option>
                    @foreach($catalogueDesigns as $design)
                        <option value="{{ $design->id }}" @selected($design->id === $selectedDesignId)>{{ $design->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if($selectedDesignId)
            <a href="{{ route('press-sends.index') }}"
               class="text-xs text-[#86868B] hover:text-[#1D1D1F] whitespace-nowrap pb-2">
                × Clear design
            </a>
        @endif

    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Send #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Designs</th>
                <th class="text-left">Sent Date</th>
                <th class="text-right">Sent</th>
                <th class="text-right">Returned</th>
                <th class="text-right">Outstanding</th>
                <th class="text-left">Logged By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @php
                $badgeColors = [
                    'bg-blue-50 text-blue-700',
                    'bg-violet-50 text-violet-700',
                    'bg-emerald-50 text-emerald-700',
                    'bg-rose-50 text-rose-700',
                    'bg-orange-50 text-orange-700',
                    'bg-indigo-50 text-indigo-700',
                    'bg-teal-50 text-teal-700',
                    'bg-pink-50 text-pink-700',
                ];
            @endphp
            @forelse($sends as $send)
            @php
                $totalSent       = $send->items->sum('quantity');
                $totalReturned   = $send->returns->flatMap->items->sum('quantity');
                $outstanding     = max(0, $totalSent - $totalReturned);
                $returnedByDesign = $send->returns->flatMap->items->groupBy('design_id');
                $designs          = $send->items->groupBy('design_id');
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">PS-{{ str_pad($send->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $send->catalogue->name ?? '—' }}</td>
                <td>
                    <div class="flex flex-col gap-1">
                        @foreach($designs as $designId => $designItems)
                            @php
                                $designModel = $designItems->first()->design;
                                $sent        = $designItems->sum('quantity');
                                $returned    = $returnedByDesign->get($designId, collect())->sum('quantity');
                                $color       = $badgeColors[($designId ?? $loop->index) % count($badgeColors)];
                            @endphp
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium {{ $color }} rounded px-2 py-0.5 w-fit">
                                {{ $designModel->name ?? '—' }}
                                <span class="opacity-60">· {{ number_format($sent) }} / {{ number_format($returned) }}</span>
                            </span>
                        @endforeach
                    </div>
                </td>
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
                <td colspan="9" class="text-center text-[#86868B] py-12">No press sends recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $sends->links() }}</div>

@endsection
