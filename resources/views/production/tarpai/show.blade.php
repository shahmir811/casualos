@extends('layouts.app')
@section('title', 'Tarpai Send')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div class="flex items-center gap-3">
        <a href="{{ route('tarpai-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Tarpai</a>
        <span class="text-[#86868B]">/</span>
        <span class="text-[#1D1D1F] text-sm font-medium">TP-{{ str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) }}</span>
    </div>
    <div class="flex items-center gap-3">
        @if($tarpaiSend->tarpai_house !== 'in_house')
        <a href="{{ route('tarpai.gate-pass', $tarpaiSend) }}" target="_blank"
           class="btn-secondary flex items-center gap-2 text-sm">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Print Gate Pass
        </a>
        @endif
        @if(Auth::user()->role !== 'creative_head')
        <form method="POST" action="{{ route('tarpai-sends.destroy', $tarpaiSend) }}"
              onsubmit="return confirm('{{ $tarpaiSend->returns->count() > 0
                  ? 'Delete TP-' . str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) . ' and its ' . $tarpaiSend->returns->count() . ' return batch(es)? This cannot be undone.'
                  : 'Delete TP-' . str_pad($tarpaiSend->id, 4, '0', STR_PAD_LEFT) . '? This cannot be undone.' }}')">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="flex items-center gap-2 text-sm px-4 py-2 rounded-full border border-red-200 text-red-600 bg-red-50 hover:bg-red-100 hover:border-red-300 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Send
            </button>
        </form>
        @endif {{-- creative_head guard --}}
    </div>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 bg-[#F0FFF4] border border-[#C6F6D5] text-green-700 text-sm rounded-xl">
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

@php
    $totalSent     = $tarpaiSend->totalPiecesSent();
    $totalReturned = $tarpaiSend->totalPiecesReturned();
    $outstanding   = $tarpaiSend->outstandingPieces();
    $totalCost     = $tarpaiSend->totalCost();
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pieces Sent</p><p class="text-3xl font-light text-[#1D1D1F]">{{ lacs_format($totalSent) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Returned</p><p class="text-3xl font-light text-green-600">{{ lacs_format($totalReturned) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p><p class="text-3xl font-light {{ $outstanding > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">{{ lacs_format($outstanding) }}</p></div>
    <div class="stat-card"><p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Cost</p><p class="text-3xl font-light text-[#1D1D1F]">Rs. {{ lacs_format($totalCost, 0) }}</p></div>
</div>

<div class="space-y-5">

    {{-- Send Details --}}
    <div class="card p-5">
        <h2 class="text-sm font-semibold text-[#1D1D1F] mb-4">Send Details</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p><p class="text-[#1D1D1F]">{{ $tarpaiSend->catalogue->name ?? '—' }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Tarpai House</p>
                <span class="badge {{ $tarpaiSend->tarpai_house === 'rashid_bhai' ? 'bg-purple-100 text-purple-700' : ($tarpaiSend->tarpai_house === 'yousaf_bhai' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700') }}">
                    {{ $tarpaiSend->tarpaiHouseLabel() }}
                </span>
            </div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Send Date</p><p class="text-[#1D1D1F]">{{ $tarpaiSend->sent_date->format('d M Y') }}</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Rate</p><p class="text-[#1D1D1F]">Rs. {{ lacs_format($tarpaiSend->per_piece_price, 0) }} / piece</p></div>
            <div><p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p><p class="text-[#1D1D1F]">{{ $tarpaiSend->loggedBy->name ?? '—' }}</p></div>
        </div>
    </div>

    {{-- Design Breakdown --}}
    <div class="card overflow-x-auto">
        <div class="px-5 py-4 border-b border-[#F2F2F7]">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Pieces Sent by Design &amp; Size (Kameez)</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#F5F5F7]">
                <tr>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Design</th>
                    @foreach($sizes as $size)
                    <th class="text-center px-4 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">{{ strtoupper($size) }}</th>
                    @endforeach
                    <th class="text-right px-5 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Sent</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Returned</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Outstanding</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F2F2F7]">
                @forelse($sentByDesign as $designId => $designItems)
                @php
                    $design        = $designsById[$designId] ?? null;
                    $designSent    = $designItems->sum('quantity');
                    $returnedItems = $tarpaiSend->returns->flatMap->items->where('design_id', $designId);
                    $designReturned = $returnedItems->sum('quantity');
                    $designOut     = max(0, $designSent - $designReturned);
                @endphp
                <tr>
                    <td class="px-5 py-3 font-medium text-[#1D1D1F]">{{ $design?->name ?? "Design #{$designId}" }}</td>
                    @foreach($sizes as $size)
                    <td class="text-center px-4 py-3 tabular-nums text-[#1D1D1F]">
                        {{ $designItems->where('size', $size)->sum('quantity') ?: '—' }}
                    </td>
                    @endforeach
                    <td class="text-right px-5 py-3 font-semibold tabular-nums">{{ $designSent }}</td>
                    <td class="text-right px-5 py-3 text-green-700 tabular-nums">{{ $designReturned }}</td>
                    <td class="text-right px-5 py-3 tabular-nums {{ $designOut > 0 ? 'text-orange-500 font-semibold' : 'text-[#86868B]' }}">{{ $designOut ?: '✓' }}</td>
                </tr>
                @empty
                <tr><td colspan="{{ 3 + count($sizes) }}" class="text-center text-[#86868B] py-8">No items.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Return History --}}
    @if($tarpaiSend->returns->count())
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-[#F2F2F7]"><h2 class="text-sm font-semibold text-[#1D1D1F]">Return History</h2></div>
        <div class="divide-y divide-[#F2F2F7]">
            @foreach($tarpaiSend->returns as $ret)
            @php $retByDesign = $ret->items->groupBy('design_id'); @endphp
            <div class="px-5 py-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-[#6E6E73]">RTN-{{ $loop->iteration }}</span>
                        <span class="text-sm text-[#1D1D1F]">{{ $ret->return_date->format('d M Y') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-green-700">{{ $ret->items->sum('quantity') }} pcs returned</span>
                        @if(Auth::user()->role !== 'creative_head')
                        <form method="POST" action="{{ route('tarpai.return.destroy', [$tarpaiSend, $ret]) }}"
                              onsubmit="return confirm('Delete this return entry? The pieces will go back to outstanding.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="text-xs text-red-500 hover:text-red-700 font-medium underline underline-offset-2">
                                Delete
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @foreach($retByDesign as $dId => $dItems)
                @php $dName = $designsById[$dId]?->name ?? ($dId ? "Design #{$dId}" : 'Unknown Design'); @endphp
                <div class="bg-[#F9F9FB] rounded-lg px-4 py-3 {{ !$loop->first ? 'mt-2' : '' }}">
                    <p class="text-xs font-semibold text-[#1D1D1F] mb-2">{{ $dName }}</p>
                    <div class="flex gap-5 flex-wrap">
                        @foreach($sizes as $size)
                        @php $qty = $dItems->where('size', $size)->sum('quantity'); @endphp
                        @if($qty > 0)
                        <div class="text-center">
                            <p class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">{{ strtoupper($size) }}</p>
                            <p class="text-sm font-semibold text-green-700">{{ $qty }}</p>
                        </div>
                        @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Log Return Form --}}
    @if(Auth::user()->role !== 'creative_head')
    @if($outstanding > 0)
    <div class="card p-5"
         x-data="{
             quantities: {},
             maxQty: {{ Js::from($outstandingByDesign) }},

             getQty(designId, size) {
                 return this.quantities?.[designId]?.[size] ?? 0;
             },
             setQty(designId, size, val) {
                 if (!this.quantities[designId]) this.quantities[designId] = {};
                 const n = parseInt(val);
                 this.quantities[designId][size] = isNaN(n) ? 0 : n;
             },
             getMax(designId, size) {
                 return this.maxQty?.[designId]?.[size] ?? 0;
             },
             isInvalid(designId, size) {
                 const qty  = this.getQty(designId, size);
                 const max  = this.getMax(designId, size);
                 return qty < 0 || qty > max;
             },
             get isFormValid() {
                 return !Object.entries(this.quantities).some(([dId, sizes]) =>
                     Object.entries(sizes).some(([sz]) => this.isInvalid(dId, sz))
                 );
             }
         }">
        <h3 class="text-sm font-semibold text-[#1D1D1F] mb-1">Log Return Batch</h3>
        <p class="text-xs text-[#86868B] mb-5">Enter pieces returning per design per size. Leave blank if none returning for that design.</p>

        <form method="POST" action="{{ route('tarpai.return', $tarpaiSend) }}" class="space-y-5">
            @csrf
            <div class="max-w-xs">
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                <input type="date" name="return_date" value="{{ date('Y-m-d') }}" class="apple-input" required>
            </div>

            @php $dIdx = 0; @endphp
            @foreach($sentByDesign as $designId => $designItems)
            @php
                $design    = $designsById[$designId] ?? null;
                $hasAnyOut = collect($outstandingByDesign[$designId] ?? [])->sum() > 0;
            @endphp
            @if($hasAnyOut)
            <div class="border border-[#E8E8ED] rounded-xl p-4">
                <p class="text-sm font-medium text-[#1D1D1F] mb-3">{{ $design?->name ?? "Design #{$designId}" }}</p>
                <input type="hidden" name="designs[{{ $dIdx }}][design_id]" value="{{ $designId }}">
                <div class="grid grid-cols-5 gap-3">
                    @foreach($sizes as $si => $size)
                    @php $maxQty = $outstandingByDesign[$designId][$size] ?? 0; @endphp
                    <div>
                        <label class="block text-xs font-semibold text-[#86868B] uppercase tracking-widest mb-1.5 text-center">{{ strtoupper($size) }}</label>
                        <input type="hidden" name="designs[{{ $dIdx }}][items][{{ $si }}][size]" value="{{ $size }}">
                        <input type="number"
                               name="designs[{{ $dIdx }}][items][{{ $si }}][qty]"
                               value="0"
                               @input="setQty({{ $designId }}, '{{ $size }}', $event.target.value)"
                               :readonly="getMax({{ $designId }}, '{{ $size }}') === 0"
                               class="apple-input text-center"
                               :class="getMax({{ $designId }}, '{{ $size }}') === 0
                                   ? 'text-[#C7C7CC] cursor-not-allowed'
                                   : isInvalid({{ $designId }}, '{{ $size }}')
                                       ? '!bg-red-50 !border !border-red-500'
                                       : ''">
                        <p class="text-xs text-center mt-1"
                           :class="isInvalid({{ $designId }}, '{{ $size }}') && getMax({{ $designId }}, '{{ $size }}') > 0
                               ? 'text-red-500 font-medium'
                               : 'text-[#86868B]'">
                            Max: {{ $maxQty }}
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
            @php $dIdx++; @endphp
            @endif
            @endforeach

            <div class="flex items-center gap-3">
                <button type="submit"
                        :disabled="!isFormValid"
                        :class="!isFormValid ? 'btn-primary opacity-40 cursor-not-allowed' : 'btn-primary'">
                    Log Return
                </button>
                <span x-show="!isFormValid" class="text-sm text-red-500 font-medium">
                    Some quantities are invalid (negative or above max).
                </span>
            </div>
        </form>
    </div>
    @else
    <div class="card p-5 bg-green-50 border-green-200">
        <div class="flex items-center gap-2 text-green-700">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            <p class="text-sm font-medium">All pieces returned — send complete</p>
        </div>
    </div>
    @endif
    @endif {{-- creative_head guard --}}

</div>

@endsection
