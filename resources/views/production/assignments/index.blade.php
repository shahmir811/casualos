@extends('layouts.app')
@section('title', 'Production Assignments')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Production Assignments</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Route designs to Naeem Pakki or Stitching Unit</p>
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
                <th class="text-left">Assignment #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-left">Destination</th>
                <th class="text-left">Date</th>
                <th class="text-left">Total Pieces</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($assignments as $a)
            @php
                $dest = $a->destination === 'naeem_pakki' ? 'Naeem Pakki' : 'Stitching Unit';
                $destColor = $a->destination === 'naeem_pakki' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700';
            @endphp
            <tr>
                <td class="font-medium text-[#0066CC]">PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $a->catalogue->name ?? '—' }}</td>
                <td>{{ $a->design->name ?? '—' }}</td>
                <td><span class="badge {{ $destColor }}">{{ $dest }}</span></td>
                <td class="text-[#6E6E73] text-xs">{{ $a->assignment_date->format('d M Y') }}</td>
                <td>{{ number_format($a->items->sum('quantity')) }} pcs</td>
                <td>
                    <a href="{{ route('production-assignments.show', $a) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">No production assignments yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $assignments->links() }}</div>

@endsection
