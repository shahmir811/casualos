@extends('layouts.app')
@section('title', 'Press Send #PS-' . str_pad($pressSend->id, 4, '0', STR_PAD_LEFT))
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('press-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Press</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">PS-{{ str_pad($pressSend->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 bg-[#F0FFF4] border border-[#BBF7D0] text-[#166534] text-sm rounded-xl">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Header --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">
            PS-{{ str_pad($pressSend->id, 4, '0', STR_PAD_LEFT) }}
        </h1>
        <p class="text-[#6E6E73] text-sm mt-1">
            {{ $pressSend->catalogue->name ?? '—' }} &mdash; Sent {{ $pressSend->sent_date->format('d M Y') }}
        </p>
    </div>
    @php
        $totalSent     = $pressSend->items->sum('quantity');
        $totalReturned = $pressSend->returns->flatMap->items->sum('quantity');
        $outstanding   = max(0, $totalSent - $totalReturned);
    @endphp
    <div class="text-right">
        <p class="text-xs text-[#6E6E73] uppercase tracking-widest">Outstanding</p>
        <p class="text-2xl font-light {{ $outstanding > 0 ? 'text-[#FF9500]' : 'text-[#34C759]' }}">
            {{ lacs_format($outstanding) }} pcs
        </p>
    </div>
</div>

{{-- Sent items summary --}}
<div class="card overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-[#F2F2F7]">
        <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces Sent</h3>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Design</th>
                @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                <th class="text-right">Returned</th>
                <th class="text-right">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sentByDesign as $designId => $sentItems)
            @php
                $design        = $designsById[$designId] ?? null;
                $returnedItems = $pressSend->returns->flatMap->items->where('design_id', $designId);
                $designReturned = $returnedItems->sum('quantity');
                $designSent     = $sentItems->sum('quantity');
                $designOutstanding = max(0, $designSent - $designReturned);
            @endphp
            <tr>
                <td class="font-medium">{{ $design->name ?? '—' }}</td>
                @foreach($sizes as $size)
                <td class="text-right">{{ $sentItems->where('size', $size)->sum('quantity') ?: '—' }}</td>
                @endforeach
                <td class="text-right text-[#34C759] font-medium">{{ lacs_format($designReturned) }}</td>
                <td class="text-right {{ $designOutstanding > 0 ? 'text-[#FF9500]' : 'text-[#86868B]' }} font-medium">
                    {{ $designOutstanding > 0 ? lacs_format($designOutstanding) : '—' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Return history --}}
@if($pressSend->returns->count() > 0)
<div class="card overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-[#F2F2F7]">
        <h3 class="text-sm font-semibold text-[#1D1D1F]">Return History</h3>
    </div>
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Return #</th>
                <th class="text-left">Return Date</th>
                <th class="text-left">Design</th>
                @foreach($sizes as $size)<th class="text-right">{{ strtoupper($size) }}</th>@endforeach
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pressSend->returns as $ret)
            @php $retItemsByDesign = $ret->items->groupBy('design_id'); @endphp
            @foreach($retItemsByDesign as $dId => $retItems)
            <tr>
                @if($loop->parent->first && $loop->first)
                <td class="font-medium text-[#0066CC]" rowspan="{{ $retItemsByDesign->count() }}">PR-{{ str_pad($ret->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td class="text-[#6E6E73] text-xs" rowspan="{{ $retItemsByDesign->count() }}">{{ $ret->return_date->format('d M Y') }}</td>
                @elseif($loop->first)
                <td class="font-medium text-[#0066CC]" rowspan="{{ $retItemsByDesign->count() }}">PR-{{ str_pad($ret->id, 4, '0', STR_PAD_LEFT) }}</td>
                <td class="text-[#6E6E73] text-xs" rowspan="{{ $retItemsByDesign->count() }}">{{ $ret->return_date->format('d M Y') }}</td>
                @endif
                <td class="font-medium">{{ $designsById[$dId]->name ?? '—' }}</td>
                @foreach($sizes as $size)
                <td class="text-right">{{ $retItems->where('size', $size)->sum('quantity') ?: '—' }}</td>
                @endforeach
                <td class="text-right font-bold text-[#0071E3]">{{ lacs_format($retItems->sum('quantity')) }}</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Log return form --}}
@if($outstanding > 0)
@php
    // Build the Alpine initialisation data in PHP so Blade loops don't get messy inside x-data
    $alpineVals  = [];
    $alpineMaxes = [];
    foreach ($sentByDesign as $dId => $sentItems) {
        foreach ($sizes as $size) {
            $key = $dId . '_' . $size;
            $alpineVals[$key]  = 0;
            $alpineMaxes[$key] = $outstandingByDesign[$dId][$size] ?? 0;
        }
    }
@endphp
<div class="card overflow-hidden"
     x-data="{
         vals:  {{ Js::from($alpineVals) }},
         maxes: {{ Js::from($alpineMaxes) }},
         isOver(key) {
             return (this.maxes[key] ?? 0) > 0 && parseInt(this.vals[key] ?? 0) > this.maxes[key];
         },
         get hasOverflow() {
             return Object.keys(this.maxes).some(k => this.isOver(k));
         },
         get hasAnyQty() {
             return Object.values(this.vals).some(v => parseInt(v) > 0);
         }
     }">
    <div class="px-5 py-4 border-b border-[#F2F2F7]">
        <h3 class="text-sm font-semibold text-[#1D1D1F]">Log Press Return</h3>
        <p class="text-xs text-[#6E6E73] mt-0.5">These pieces will immediately enter packed inventory.</p>
    </div>
    <div class="p-5">
        <form method="POST" action="{{ route('press.return', $pressSend) }}"
              autocomplete="off"
              x-init="$nextTick(() => $el.querySelectorAll('input[type=number]:not([disabled])').forEach(el => { el.value = 0; }))">
            @csrf
            <div class="mb-5">
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                <input type="date" name="return_date" value="{{ date('Y-m-d') }}" class="apple-input max-w-xs" required>
            </div>

            @foreach($sentByDesign as $designId => $sentItems)
            @php
                $design = $designsById[$designId] ?? null;
                $dIndex = $loop->index;
            @endphp
            <div class="mb-5">
                <p class="text-sm font-semibold text-[#1D1D1F] mb-3">{{ $design->name ?? '—' }}</p>
                <input type="hidden" name="designs[{{ $dIndex }}][design_id]" value="{{ $designId }}">
                <div class="grid grid-cols-5 gap-3">
                    @foreach($sizes as $sIndex => $size)
                    @php
                        $outstanding_size = $outstandingByDesign[$designId][$size] ?? 0;
                        $alpineKey        = $designId . '_' . $size;
                    @endphp
                    <div>
                        <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1 text-center">
                            {{ strtoupper($size) }}
                        </label>
                        <p class="text-xs text-center mb-2"
                           :class="isOver('{{ $alpineKey }}') ? 'text-red-500 font-semibold' : 'text-[#86868B]'">
                            Max: <span class="font-medium" :class="isOver('{{ $alpineKey }}') ? 'text-red-500' : 'text-[#1D1D1F]'">{{ $outstanding_size }}</span>
                        </p>
                        <input type="hidden" name="designs[{{ $dIndex }}][items][{{ $sIndex }}][size]" value="{{ $size }}">
                        @if($outstanding_size === 0)
                        <input type="number"
                               name="designs[{{ $dIndex }}][items][{{ $sIndex }}][qty]"
                               value="0"
                               class="apple-input text-center opacity-40 cursor-not-allowed"
                               disabled readonly>
                        @else
                        <input type="number"
                               name="designs[{{ $dIndex }}][items][{{ $sIndex }}][qty]"
                               min="0"
                               max="{{ $outstanding_size }}"
                               value="0"
                               autocomplete="off"
                               @input="vals['{{ $alpineKey }}'] = parseFloat($event.target.value) || 0"
                               :class="isOver('{{ $alpineKey }}')
                                   ? 'apple-input text-center ring-2 ring-red-400 bg-red-50 text-red-600'
                                   : 'apple-input text-center'">
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

            <div class="flex items-center gap-4 mt-2">
                <button type="submit"
                        class="btn-primary"
                        :disabled="!hasAnyQty || hasOverflow"
                        :class="(!hasAnyQty || hasOverflow) ? 'opacity-50 cursor-not-allowed' : ''">
                    Log Return
                </button>
                <p x-show="hasOverflow" class="text-sm text-red-500">
                    Some quantities exceed the outstanding maximum.
                </p>
            </div>
        </form>
    </div>
</div>
@else
<div class="card p-6 text-center">
    <p class="text-[#34C759] font-medium">All pieces have been returned from press.</p>
</div>
@endif

@endsection
