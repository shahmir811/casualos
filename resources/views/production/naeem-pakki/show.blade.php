@extends('layouts.app')
@section('title', 'Naeem Pakki — PA-' . str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT))
@section('content')

@php
    $totalSent        = $productionAssignment->npDesigns->sum('quantity');
    $totalReturned    = $productionAssignment->npDesigns->sum(fn($d) => $d->totalReturned());
    $totalOutstanding = $productionAssignment->npDesigns->sum(fn($d) => $d->outstandingPieces());
    $totalCost        = $productionAssignment->npDesigns->sum(fn($d) => $d->totalCost());
    $batches          = $productionAssignment->naeemPakkiReturns->sortByDesc('return_date');
@endphp

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('naeem-pakki-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Naeem Pakki</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</span>
</div>

{{-- Stat cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Sent</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ lacs_format($totalSent) }}</p>
        <p class="text-[#86868B] text-xs mt-1">pieces</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Returned</p>
        <p class="text-3xl font-light text-green-600">{{ lacs_format($totalReturned) }}</p>
        <p class="text-[#86868B] text-xs mt-1">pieces</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Outstanding</p>
        <p class="text-3xl font-light {{ $totalOutstanding > 0 ? 'text-orange-500' : 'text-[#86868B]' }}">
            {{ lacs_format($totalOutstanding) }}
        </p>
        <p class="text-[#86868B] text-xs mt-1">pieces</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Cost</p>
        <p class="text-2xl font-light text-[#1D1D1F]">Rs. {{ lacs_format($totalCost, 0) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── LEFT: Details + Log Return form ─────────────────────────────── --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Assignment details --}}
        <div class="card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-[#1D1D1F]">Assignment Details</h2>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Assignment ID</p>
                <a href="{{ route('production-assignments.show', $productionAssignment) }}"
                   class="font-medium text-[#0066CC] hover:underline">
                    PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}
                </a>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Catalogue</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->catalogue->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Date</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->assignment_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Logged By</p>
                <p class="text-[#1D1D1F]">{{ $productionAssignment->loggedBy->name ?? '—' }}</p>
            </div>
        </div>

        {{-- Log Return form --}}
        @if(Auth::user()->role !== 'creative_head')
        @if($totalOutstanding > 0)
        <div class="card p-5">
            <h3 class="text-sm font-semibold text-[#1D1D1F] mb-1">Log Return Batch</h3>
            <p class="text-xs text-[#86868B] mb-4">
                {{ lacs_format($totalOutstanding) }} pcs still outstanding. Enter 0 for designs not returning in this batch.
            </p>

            @if($errors->any())
            <div class="mb-3 px-3 py-2 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg">
                {{ $errors->first('items') }}
            </div>
            @endif

            <form method="POST" action="{{ route('naeem-pakki.return', $productionAssignment) }}"
                  class="space-y-4"
                  x-data="{
                      quantities: {!! json_encode($productionAssignment->npDesigns->mapWithKeys(fn($_, $i) => [$i => 0])) !!},
                      maxQty: {!! json_encode($productionAssignment->npDesigns->mapWithKeys(fn($d, $i) => [$i => $d->outstandingPieces()])) !!},
                      isOver(i) { return parseInt(this.quantities[i] ?? 0) > this.maxQty[i]; },
                      hasErrors() { return Object.keys(this.quantities).some(i => this.isOver(i)); }
                  }">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                    <input type="date" name="return_date" value="{{ date('Y-m-d') }}" class="apple-input" required>
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest">Pieces Returned Per Design</label>
                    @foreach($productionAssignment->npDesigns as $i => $npDesign)
                    @php $outstanding = $npDesign->outstandingPieces(); @endphp
                    <div class="p-3 rounded-xl transition-colors"
                         :class="isOver({{ $i }}) ? 'bg-red-50 ring-1 ring-red-400' : 'bg-[#F5F5F7]'">
                        @if($outstanding > 0)
                        <input type="hidden" name="items[{{ $i }}][np_design_id]" value="{{ $npDesign->id }}">
                        @endif
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-semibold text-[#1D1D1F]">{{ $npDesign->design->name ?? '—' }}</span>
                            <span class="text-[10px]"
                                  :class="isOver({{ $i }}) ? 'text-red-600 font-semibold' : 'text-[#86868B]'">
                                {{ lacs_format($outstanding) }} outstanding
                            </span>
                        </div>
                        <input type="number"
                               name="items[{{ $i }}][quantity]"
                               min="0"
                               max="{{ $outstanding }}"
                               x-model="quantities[{{ $i }}]"
                               value="{{ old("items.{$i}.quantity", 0) }}"
                               class="apple-input text-sm transition-colors"
                               :class="isOver({{ $i }}) ? 'border border-red-400 bg-red-50 text-red-700 focus:ring-red-300' : ''"
                               {{ $outstanding === 0 ? 'disabled' : '' }}>
                        <p class="text-[10px] mt-1 transition-all"
                           :class="isOver({{ $i }}) ? 'text-red-600' : 'text-transparent'"
                           x-text="isOver({{ $i }}) ? 'Exceeds outstanding — max {{ $outstanding }} pcs' : ''"></p>
                        @if($outstanding === 0)
                        <p class="text-[10px] text-green-600 mt-1">All returned</p>
                        @endif
                    </div>
                    @endforeach
                </div>

                <button type="submit"
                        class="btn-primary w-full justify-center transition-opacity"
                        :disabled="hasErrors()"
                        :class="hasErrors() ? 'opacity-40 cursor-not-allowed' : ''">
                    Save Return Batch
                </button>
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
        @endif
        @endif {{-- creative_head guard --}}

        @if(session('success'))
        <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl">
            {{ session('success') }}
        </div>
        @endif

    </div>

    {{-- ── RIGHT: Design breakdown + Batch history ──────────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Design breakdown --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Design Breakdown</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">Running totals per design</p>
            </div>
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        <th class="text-right">Sent</th>
                        <th class="text-right">Returned</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">Rate</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productionAssignment->npDesigns as $npDesign)
                    @php
                        $returned    = $npDesign->totalReturned();
                        $outstanding = $npDesign->outstandingPieces();
                    @endphp
                    <tr>
                        <td class="font-medium text-[#1D1D1F]">{{ $npDesign->design->name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ lacs_format($npDesign->quantity) }} pcs</td>
                        <td class="text-right tabular-nums text-green-700">{{ lacs_format($returned) }} pcs</td>
                        <td class="text-right tabular-nums {{ $outstanding > 0 ? 'text-orange-600 font-semibold' : 'text-[#86868B]' }}">
                            {{ lacs_format($outstanding) }} pcs
                        </td>
                        <td class="text-right tabular-nums text-[#6E6E73] text-xs">
                            Rs. {{ lacs_format((float) $npDesign->per_piece_price, 0) }}/pc
                        </td>
                        <td class="text-right tabular-nums font-semibold" style="color:#FF9500">
                            Rs. {{ lacs_format($npDesign->totalCost(), 0) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                        <td class="font-semibold text-xs uppercase tracking-wide">Total</td>
                        <td class="text-right font-bold tabular-nums">{{ lacs_format($totalSent) }} pcs</td>
                        <td class="text-right font-bold tabular-nums text-green-700">{{ lacs_format($totalReturned) }} pcs</td>
                        <td class="text-right font-bold tabular-nums {{ $totalOutstanding > 0 ? 'text-orange-600' : 'text-[#86868B]' }}">
                            {{ lacs_format($totalOutstanding) }} pcs
                        </td>
                        <td></td>
                        <td class="text-right font-bold tabular-nums" style="color:#FF9500">
                            Rs. {{ lacs_format($totalCost, 0) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Return batch history --}}
        @if($batches->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Return History</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">{{ $batches->count() }} batch{{ $batches->count() > 1 ? 'es' : '' }} logged</p>
            </div>

            @foreach($batches as $batchIndex => $batch)
            @php $batchTotal = $batch->items->sum('quantity'); @endphp
            <div class="{{ !$loop->last ? 'border-b border-[#F2F2F7]' : '' }} px-5 py-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wide">
                            Batch {{ $batches->count() - $batchIndex }}
                        </span>
                        <span class="text-xs text-[#6E6E73]">{{ $batch->return_date->format('d M Y') }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-green-700">{{ lacs_format($batchTotal) }} pcs</span>
                        <span class="text-xs text-[#86868B] ml-1">returned</span>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach($batch->items as $item)
                        <tr>
                            <td class="py-1 text-[#1D1D1F]">{{ $item->npDesign->design->name ?? '—' }}</td>
                            <td class="py-1 text-right tabular-nums text-green-700 font-medium">
                                {{ lacs_format($item->quantity) }} pcs
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-[10px] text-[#86868B] mt-2">Logged by {{ $batch->loggedBy->name ?? '—' }}</p>
            </div>
            @endforeach
        </div>
        @else
        <div class="card p-8 text-center text-[#86868B]">
            <p>No return batches logged yet.</p>
        </div>
        @endif

    </div>
</div>

@endsection
