@extends('layouts.app')
@section('title', 'Stitching Returns')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Stitching Returns</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Daily returns from the stitching unit</p>
    </div>
    <a href="{{ route('stitching-returns.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Log Return
    </a>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Return #</th>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Design</th>
                <th class="text-left">Return Date</th>
                <th class="text-right">Total Pieces</th>
                <th class="text-left">Logged By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($returns as $ret)
            <tr>
                <td class="font-medium text-[#0066CC]">SR-{{ str_pad($ret->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td>{{ $ret->catalogue->name ?? '—' }}</td>
                <td>{{ $ret->design->name ?? '—' }}</td>
                <td>{{ $ret->return_date->format('d M Y') }}</td>
                <td class="text-right font-medium text-green-700">{{ number_format($ret->items->sum('quantity')) }} pcs</td>
                <td class="text-[#6E6E73] text-xs">{{ $ret->loggedBy->name ?? '—' }}</td>
                <td>
                    <a href="{{ route('stitching-returns.show', $ret) }}" class="text-[#0066CC] text-sm hover:underline">View →</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center text-[#86868B] py-12">No stitching returns logged yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $returns->links() }}</div>

@endsection
