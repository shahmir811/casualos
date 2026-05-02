@extends('layouts.app')
@section('title', 'Naeem Pakki Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('naeem-pakki-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Naeem Pakki</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">NP-{{ str_pad($naeemPakkiSend->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

@php
    $totalSent     = $naeemPakkiSend->totalPiecesSent();
    $totalReturned = $naeemPakkiSend->totalPiecesReturned();
    $outstanding   = $naeemPakkiSend->outstandingPieces();
    $totalCost     = $naeemPakkiSend->totalCost();
@endphp

{{-- Stat cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pieces Sent</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($totalSent) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Returned</p>
        <p class="text-3xl font-light text-green-600">{{ number_format($totalReturned) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p>
        <p class="text-3xl font-light {{ $outstanding > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">{{ number_format($outstanding) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Cost</p>
        <p class="text-3xl font-light text-[#1D1D1F]">Rs. {{ number_format($totalCost, 0) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Details + Log Return --}}
    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Send Details</h2>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Send ID</p>
                <p class="text-[#1D1D1F] font-medium">NP-{{ str_pad($naeemPakkiSend->id, 4, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F]">{{ $naeemPakkiSend->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Design</p>
                <p class="text-[#1D1D1F] font-medium">{{ $naeemPakkiSend->design->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Send Date</p>
                <p class="text-[#1D1D1F]">{{ $naeemPakkiSend->sent_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Pieces Sent</p>
                <p class="text-[#1D1D1F] font-semibold text-lg">{{ number_format($totalSent) }} pcs</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Rate</p>
                <p class="text-[#1D1D1F]">Rs. {{ number_format($naeemPakkiSend->per_piece_price, 0) }} / piece</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Total Cost</p>
                <p class="text-[#1D1D1F] font-semibold">Rs. {{ number_format($totalCost, 0) }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $naeemPakkiSend->loggedBy->name ?? '—' }}</p>
            </div>
        </div>

        {{-- Log Return form --}}
        @if($outstanding > 0)
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-[#1D1D1F] mb-1">Log Return</h3>
            <p class="text-xs text-[#86868B] mb-4">{{ number_format($outstanding) }} pieces still outstanding</p>
            @if(session('success'))
            <div class="mb-3 px-3 py-2 bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg">
                {{ session('success') }}
            </div>
            @endif
            <form method="POST" action="{{ route('naeem-pakki.return', $naeemPakkiSend) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                    <input type="date" name="return_date" value="{{ date('Y-m-d') }}" class="apple-input" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                        Pieces Returned <span class="text-[#FF3B30]">*</span>
                    </label>
                    <input type="number" name="quantity" min="1" max="{{ $outstanding }}"
                           value="{{ $outstanding }}" class="apple-input" required>
                    <p class="mt-1 text-xs text-[#86868B]">Max: {{ number_format($outstanding) }} pcs</p>
                </div>
                <button type="submit" class="btn-primary w-full justify-center">Log Return</button>
            </form>
        </div>
        @else
        <div class="card p-5 bg-green-50 border-green-200">
            <div class="flex items-center gap-2 text-green-700">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm font-medium">All pieces returned</p>
            </div>
        </div>
        @if(session('success'))
        <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
        @endif
        @endif
    </div>

    {{-- Right: Return history --}}
    <div class="lg:col-span-2">
        @if($naeemPakkiSend->returns->count())
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Return History</h2>
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Return Date</th>
                        <th class="text-right">Pieces Returned</th>
                        <th class="text-left">Logged By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($naeemPakkiSend->returns as $ret)
                    <tr>
                        <td class="text-[#6E6E73]">{{ $ret->return_date->format('d M Y') }}</td>
                        <td class="text-right text-green-700 font-semibold">{{ number_format($ret->quantity) }} pcs</td>
                        <td class="text-[#6E6E73] text-xs">{{ $ret->loggedBy->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                    <tr class="border-t-2 border-[#E8E8ED]">
                        <td class="font-semibold">Total returned</td>
                        <td class="text-right font-bold text-green-700">{{ number_format($totalReturned) }} pcs</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        @else
        <div class="card p-8 text-center text-[#86868B]">
            <p>No returns logged yet for this send.</p>
        </div>
        @endif
    </div>
</div>

@endsection
