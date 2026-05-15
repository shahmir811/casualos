@extends('layouts.app')
@section('title', 'Naeem Pakki')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Naeem Pakki</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Track embroidery pieces sent to and returned from Naeem Pakki</p>
    </div>
    <a href="{{ route('production-assignments.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Assignment
    </a>
</div>

{{-- ── Filter bar ──────────────────────────────────────────────────── --}}
<div class="card p-4 mb-6">
    <div class="flex flex-wrap items-end gap-x-6 gap-y-4">

        <form method="GET" action="{{ route('naeem-pakki-sends.index') }}" class="flex flex-wrap items-end gap-4">

            <div class="w-full sm:w-auto">
                <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Catalogue</p>
                <select name="catalogue_id"
                        onchange="document.querySelector('select[name=design_id]').value=''; this.form.submit();"
                        class="w-full sm:w-auto apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
                    <option value="">All catalogues</option>
                    @foreach($catalogues as $cat)
                        <option value="{{ $cat->id }}" @selected($cat->id == $selectedCatalogueId)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-auto">
                <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Design</p>
                <select name="design_id"
                        onchange="this.form.submit();"
                        class="w-full sm:w-auto apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]"
                        @disabled(!$selectedCatalogueId)>
                    <option value="">All designs</option>
                    @foreach($catalogueDesigns as $design)
                        <option value="{{ $design->id }}" @selected($design->id == $selectedDesignId)>{{ $design->name }}</option>
                    @endforeach
                </select>
            </div>

        </form>

        @if($selectedCatalogueId || $selectedDesignId)
            <a href="{{ route('naeem-pakki-sends.index') }}"
               class="text-xs text-[#86868B] hover:text-[#1D1D1F] whitespace-nowrap pb-2">
                × Clear filters
            </a>
        @endif

    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Assignment</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Designs</th>
                <th class="text-left">Date</th>
                <th class="text-right">Total Sent</th>
                <th class="text-right">Returned</th>
                <th class="text-right">Outstanding</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $assignment)
            @php
                $totalSent        = $assignment->npDesigns->sum('quantity');
                $totalReturned    = $assignment->npDesigns->sum(fn($d) => $d->totalReturned());
                $totalOutstanding = $assignment->npDesigns->sum(fn($d) => $d->outstandingPieces());
                $done             = $totalOutstanding === 0 && $totalSent > 0;
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">
                    <a href="{{ route('naeem-pakki-sends.show', $assignment) }}">
                        PA-{{ str_pad($assignment->id, 4, '0', STR_PAD_LEFT) }}
                    </a>
                </td>
                <td class="text-[#6E6E73]">{{ $assignment->catalogue->name ?? '—' }}</td>
                <td>
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
                    <div class="flex flex-col gap-1">
                        @foreach($assignment->npDesigns as $npDesign)
                            @php $color = $badgeColors[($npDesign->design->id ?? $loop->index) % count($badgeColors)]; @endphp
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium {{ $color }} rounded px-2 py-0.5 w-fit">
                                {{ $npDesign->design->name ?? '—' }}
                                <span class="opacity-60">· {{ number_format($npDesign->quantity) }}</span>
                            </span>
                        @endforeach
                    </div>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $assignment->assignment_date->format('d M Y') }}</td>
                <td class="text-right tabular-nums">{{ number_format($totalSent) }} pcs</td>
                <td class="text-right tabular-nums text-green-700">{{ number_format($totalReturned) }} pcs</td>
                <td class="text-right tabular-nums {{ $totalOutstanding > 0 ? 'text-orange-600 font-semibold' : 'text-[#86868B]' }}">
                    {{ number_format($totalOutstanding) }} pcs
                </td>
                <td>
                    @if($done)
                        <span class="badge bg-green-100 text-green-700">Complete</span>
                    @else
                        <span class="badge bg-orange-100 text-orange-700">Pending</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('naeem-pakki-sends.show', $assignment) }}"
                       class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center text-[#86868B] py-12">No Naeem Pakki assignments recorded yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $assignments->links() }}</div>

@endsection
