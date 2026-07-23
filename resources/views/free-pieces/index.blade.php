@extends('layouts.app')
@section('title', 'Free Pieces')
@section('content')

@php
    $sizes = ['xs', 's', 'm', 'l', 'xl'];
@endphp

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Free Pieces</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Pieces freed up by deleted orders in the active catalogue, available to assign to any customer</p>
    </div>
    @if($catalogue && $freePieces->isNotEmpty())
    <a href="{{ route('free-pieces.assign') }}" class="btn-primary">Assign Free Pieces</a>
    @endif
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

@if(! $catalogue)
<div class="card p-8 text-center text-[#86868B]">No catalogue found.</div>
@else

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-[#F2F2F7]">
        <h2 class="text-sm font-semibold text-[#1D1D1F]">{{ $catalogue->name }}</h2>
    </div>

    @if($freePieces->isEmpty())
    <div class="p-8 text-center text-[#86868B]">No free pieces for this catalogue right now.</div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Sizes</th>
                    @foreach($sizes as $size)
                    <th class="text-center">{{ strtoupper($size) }}</th>
                    @endforeach
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-medium text-[#1D1D1F] whitespace-nowrap">&nbsp;</td>
                    @foreach($sizes as $size)
                    <td class="text-center tabular-nums {{ $sizeTotals[$size] > 0 ? 'font-medium text-[#1D1D1F]' : 'text-[#D1D1D6]' }}">
                        {{ $sizeTotals[$size] > 0 ? $sizeTotals[$size] : '—' }}
                    </td>
                    @endforeach
                    <td class="text-center font-semibold text-[#0071E3] tabular-nums">{{ $grandTotal }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif
</div>

@endif

@endsection
