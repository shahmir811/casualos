@extends('layouts.app')
@section('title', 'Naeem Pakki')
@section('content')

<div class="flex items-center justify-between mb-7">
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
                <td class="text-[#6E6E73] text-xs">
                    {{ $assignment->npDesigns->pluck('design.name')->filter()->join(', ') }}
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
