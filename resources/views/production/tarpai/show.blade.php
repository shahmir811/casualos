@extends('layouts.app')
@section('title', 'Tarpai Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('tarpai-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Tarpai</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">TP-{{ str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

@php
    $totalSent     = $tarpaiSend->totalPiecesSent();
    $totalReturned = $tarpaiSend->totalPiecesReturned();
    $outstanding   = $tarpaiSend->outstandingPieces();
    $totalCost     = $tarpaiSend->totalCost();
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pieces Sent</p><p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($totalSent) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Returned</p><p class="text-3xl font-light text-green-600">{{ number_format($totalReturned) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p><p class="text-3xl font-light {{ $outstanding > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">{{ number_format($outstanding) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Cost</p><p class="text-3xl font-light text-[#1D1D1F]">Rs. {{ number_format($totalCost, 0) }}</p></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <div class="lg:col-span-1 space-y-4">
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Send Details</h2>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p><p class="text-[#1D1D1F]">{{ $tarpaiSend->catalogue->name ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Design</p><p class="text-[#1D1D1F] font-medium">{{ $tarpaiSend->design->name ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Send Date</p><p class="text-[#1D1D1F]">{{ $tarpaiSend->sent_date->format('d M Y') }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Rate</p><p class="text-[#1D1D1F]">Rs. {{ number_format($tarpaiSend->per_piece_price, 0) }} / piece</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p><p class="text-[#1D1D1F]">{{ $tarpaiSend->loggedBy->name ?? '—' }}</p></div>
        </div>

        @if($outstanding > 0)
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-[#1D1D1F] mb-4">Log Return</h3>
            <form method="POST" action="{{ route('tarpai.return', $tarpaiSend) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                    <input type="date" name="return_date" value="{{ date('Y-m-d') }}" class="apple-input" required>
                </div>
                <div class="grid grid-cols-5 gap-2">
                    @foreach(['xs','s','m','l','xl'] as $size)
                    <div>
                        <label class="block text-xs font-semibold text-[#86868B] uppercase mb-1 text-center">{{ strtoupper($size) }}</label>
                        <input type="hidden" name="items[{{ $loop->index }}][size]" value="{{ $size }}">
                        <input type="number" name="items[{{ $loop->index }}][qty]" min="0" value="0" class="apple-input text-center px-1">
                    </div>
                    @endforeach
                </div>
                <button type="submit" class="btn-primary w-full justify-center">Log Return</button>
            </form>
        </div>
        @else
        <div class="card p-5 bg-green-50 border-green-200">
            <div class="flex items-center gap-2 text-green-700">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <p class="text-sm font-medium">All pieces returned</p>
            </div>
        </div>
        @endif
    </div>

    <div class="lg:col-span-2 space-y-4">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]"><h2 class="text-sm font-semibold text-[#1D1D1F]">Pieces Sent by Size</h2></div>
            <table class="w-full apple-table">
                <thead><tr><th class="text-left">Size</th><th class="text-right">Qty</th></tr></thead>
                <tbody>
                    @forelse($tarpaiSend->items as $item)
                    <tr><td class="uppercase font-medium">{{ $item->size }}</td><td class="text-right">{{ $item->quantity }} pcs</td></tr>
                    @empty
                    <tr><td colspan="2" class="text-center text-[#86868B] py-8">No size details.</td></tr>
                    @endforelse
                    @if($tarpaiSend->items->count())
                    <tr class="border-t-2 border-[#E8E8ED]"><td class="font-semibold">Total</td><td class="text-right font-bold">{{ $totalSent }} pcs</td></tr>
                    @endif
                </tbody>
            </table>
        </div>

        @if($tarpaiSend->returns->count())
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]"><h2 class="text-sm font-semibold text-[#1D1D1F]">Return History</h2></div>
            <table class="w-full apple-table">
                <thead><tr><th class="text-left">Return Date</th><th class="text-right">Pieces</th></tr></thead>
                <tbody>
                    @foreach($tarpaiSend->returns as $ret)
                    <tr><td class="text-[#6E6E73]">{{ $ret->return_date->format('d M Y') }}</td><td class="text-right text-green-700 font-medium">{{ $ret->items->sum('quantity') }} pcs</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
