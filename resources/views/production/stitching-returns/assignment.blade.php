@extends('layouts.app')
@section('title', 'Stitching — PA-' . str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT))
@section('content')

@php
    $totalAssigned = $productionAssignment->items->sum('quantity');
    $compLabels    = ['kameez' => 'Kameez', 'shalwar' => 'Shalwar', 'dupatta' => 'Dupatta'];
    $kTotal = collect($sizes)->sum(fn($s) => $matrix[$s]['kameez']['returned']);
    $sTotal = collect($sizes)->sum(fn($s) => $matrix[$s]['shalwar']['returned']);
    $dTotal = collect($sizes)->sum(fn($s) => $matrix[$s]['dupatta']['returned']);
@endphp

<div class="flex items-center justify-between mb-7">
    <div class="flex items-center gap-3">
        <a href="{{ route('stitching-returns.index') }}" class="text-[#0066CC] hover:underline text-sm">Stitching Returns</a>
        <span class="text-[#86868B]">/</span>
        <span class="text-[#1D1D1F] text-sm font-medium">PA-{{ str_pad($productionAssignment->id, 4, '0', STR_PAD_LEFT) }}</span>
    </div>
    <a href="{{ route('stitching-assignments.report', $productionAssignment) }}"
       target="_blank"
       class="btn-secondary flex items-center gap-1.5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
        </svg>
        Download Report
    </a>
</div>

{{-- Stat cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Assigned</p>
        <p class="text-3xl font-light text-[#1D1D1F]">{{ number_format($totalAssigned) }}</p>
        <p class="text-[#86868B] text-xs mt-1">pieces</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Kameez</p>
        <p class="text-3xl font-light {{ $kTotal >= $totalAssigned ? 'text-[#34C759]' : 'text-[#FF9500]' }}">{{ number_format($kTotal) }}</p>
        <p class="text-[#86868B] text-xs mt-1">/ {{ $totalAssigned }} pcs</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Shalwar</p>
        <p class="text-3xl font-light {{ $sTotal >= $totalAssigned ? 'text-[#34C759]' : 'text-[#FF9500]' }}">{{ number_format($sTotal) }}</p>
        <p class="text-[#86868B] text-xs mt-1">/ {{ $totalAssigned }} pcs</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Dupatta</p>
        <p class="text-3xl font-light {{ $dTotal >= $totalAssigned ? 'text-[#34C759]' : 'text-[#FF9500]' }}">{{ number_format($dTotal) }}</p>
        <p class="text-[#86868B] text-xs mt-1">/ {{ $totalAssigned }} pcs</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── LEFT: Details + Log Return form ─────────────────────────── --}}
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
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Design</p>
                <p class="text-[#1D1D1F] font-medium">{{ $productionAssignment->design->name ?? '—' }}</p>
            </div>
            @if($productionAssignment->stitchingUnit)
            <div>
                <p class="text-[#86868B] text-xs uppercase tracking-widest mb-1">Stitching Unit</p>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold" style="background:#F5EEFF; color:#AF52DE;">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Unit {{ $productionAssignment->stitchingUnit->number }} — {{ $productionAssignment->stitchingUnit->name }}
                </span>
            </div>
            @endif
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
        @if(!$isFullyComplete)
        <div class="card p-5"
             x-data="{
                selectedComponents: [],
                componentSizes: {
                    kameez:  { xs: 0, s: 0, m: 0, l: 0, xl: 0 },
                    shalwar: { xs: 0, s: 0, m: 0, l: 0, xl: 0 },
                    dupatta: { xs: 0, s: 0, m: 0, l: 0, xl: 0 },
                },
                remaining: {{ Js::from($remainingPerSizePerComponent) }},
                maxFor(comp, size) {
                    return (this.remaining[size] && this.remaining[size][comp] != null) ? this.remaining[size][comp] : 0;
                },
                overLimitFor(comp, size) {
                    return (parseInt(this.componentSizes[comp][size]) || 0) > this.maxFor(comp, size);
                },
                compTotal(comp) {
                    return Object.values(this.componentSizes[comp]).reduce((sum, v) => sum + (parseInt(v) || 0), 0);
                },
                get totalQty() {
                    return this.selectedComponents.reduce((sum, c) => sum + this.compTotal(c), 0);
                },
                get overLimit() {
                    return this.selectedComponents.some(c =>
                        ['xs','s','m','l','xl'].some(s => this.overLimitFor(c, s))
                    );
                },
                get canSubmit() {
                    return this.selectedComponents.length > 0 && this.totalQty > 0 && !this.overLimit;
                },
                onToggle(comp) {
                    if (!this.selectedComponents.includes(comp)) {
                        this.componentSizes[comp] = { xs: 0, s: 0, m: 0, l: 0, xl: 0 };
                    }
                }
             }">

            <h3 class="text-sm font-semibold text-[#1D1D1F] mb-1">Log Return Batch</h3>
            <p class="text-xs text-[#86868B] mb-4">Select components and enter quantities per size for each.</p>

            @if($errors->any())
            <div class="mb-3 px-3 py-2 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg">
                {{ $errors->first('items') ?? $errors->first('components') }}
            </div>
            @endif

            @if(session('success'))
            <div class="mb-3 px-3 py-2 bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg">
                {{ session('success') }}
            </div>
            @endif

            <form method="POST" action="{{ route('stitching-assignments.return', $productionAssignment) }}" class="space-y-4">
                @csrf

                {{-- Return date --}}
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Return Date</label>
                    <input type="date" name="return_date" value="{{ old('return_date', date('Y-m-d')) }}" class="apple-input" required>
                </div>

                {{-- Per-component checkboxes + size grids --}}
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                        Components Returned
                    </label>
                    <div class="space-y-3">
                        @foreach(['kameez' => $kTotal, 'shalwar' => $sTotal, 'dupatta' => $dTotal] as $comp => $compReturned)
                        @php $compRemaining = max(0, $totalAssigned - $compReturned); @endphp
                        <div>
                            <label class="flex items-center justify-between p-3 rounded-xl cursor-pointer transition-colors
                                          {{ $compRemaining === 0 ? 'opacity-50 cursor-not-allowed bg-[#F5F5F7]' : 'bg-[#F5F5F7] hover:bg-[#EEEEF2]' }}">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox"
                                           name="components[]"
                                           value="{{ $comp }}"
                                           x-model="selectedComponents"
                                           @change="onToggle('{{ $comp }}')"
                                           {{ $compRemaining === 0 ? 'disabled' : '' }}
                                           class="w-4 h-4 rounded accent-[#0071E3]">
                                    <span class="text-sm font-medium text-[#1D1D1F]">{{ ucfirst($comp) }}</span>
                                </div>
                                <span class="text-[11px] {{ $compRemaining > 0 ? 'text-[#FF9500] font-semibold' : 'text-[#34C759] font-semibold' }}">
                                    {{ $compRemaining > 0 ? $compRemaining . ' outstanding' : '✓ done' }}
                                </span>
                            </label>

                            {{-- Per-component size grid --}}
                            <div x-show="selectedComponents.includes('{{ $comp }}')" x-cloak
                                 class="mt-2 pl-2 border-l-2 border-[#0071E3]/20 space-y-1">
                                <div class="grid grid-cols-5 gap-1.5">
                                    @foreach(['xs','s','m','l','xl'] as $size)
                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase tracking-widest mb-1 text-center transition-colors"
                                               :class="overLimitFor('{{ $comp }}', '{{ $size }}') ? 'text-[#FF3B30]' : 'text-[#6E6E73]'">
                                            {{ strtoupper($size) }}
                                        </label>
                                        <input type="number"
                                               name="component_items[{{ $comp }}][{{ $size }}]"
                                               min="0"
                                               x-model.number="componentSizes.{{ $comp }}.{{ $size }}"
                                               class="apple-input text-center text-sm"
                                               :class="overLimitFor('{{ $comp }}', '{{ $size }}') ? 'border-[#FF3B30] bg-[#FFF0EF] text-[#FF3B30]' : ''">
                                        <div class="mt-0.5 text-center">
                                            <span class="text-[9px]"
                                                  :class="overLimitFor('{{ $comp }}', '{{ $size }}') ? 'text-[#FF3B30] font-semibold' : (maxFor('{{ $comp }}', '{{ $size }}') > 0 ? 'text-[#86868B]' : 'text-[#C7C7CC]')"
                                                  x-text="overLimitFor('{{ $comp }}', '{{ $size }}') ? 'max ' + maxFor('{{ $comp }}', '{{ $size }}') : maxFor('{{ $comp }}', '{{ $size }}') + ' left'">
                                            </span>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-[10px] text-[#6E6E73]">{{ ucfirst($comp) }} total:</span>
                                    <span class="text-xs font-semibold"
                                          :class="['xs','s','m','l','xl'].some(s => overLimitFor('{{ $comp }}', s)) ? 'text-[#FF3B30]' : (compTotal('{{ $comp }}') > 0 ? 'text-[#34C759]' : 'text-[#1D1D1F]')"
                                          x-text="compTotal('{{ $comp }}')"></span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Grand total --}}
                <div x-show="selectedComponents.length > 0" x-cloak
                     class="flex items-center justify-between pt-2 border-t border-[#F2F2F7]">
                    <span class="text-xs text-[#6E6E73]">Total pieces:</span>
                    <span class="text-sm font-semibold"
                          :class="overLimit ? 'text-[#FF3B30]' : (totalQty > 0 ? 'text-[#34C759]' : 'text-[#1D1D1F]')"
                          x-text="totalQty"></span>
                </div>
                <p x-show="overLimit" x-cloak class="mt-1 text-xs text-[#FF3B30] font-medium">
                    ⚠ A size exceeds its remaining quantity.
                </p>

                <button type="submit"
                        class="btn-primary w-full justify-center transition-opacity"
                        :disabled="!canSubmit"
                        :class="!canSubmit ? 'opacity-40 cursor-not-allowed' : ''">
                    Save Return Batch
                </button>
            </form>
        </div>
        @else
        <div class="card p-5" style="background:#F0FFF4; border-color:#BBF7D0;">
            <div class="flex items-center gap-2 text-green-700">
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm font-medium">All components fully returned</p>
            </div>
        </div>

        @if(session('success'))
        <div class="card px-4 py-3 bg-green-50 border-green-200 text-green-700 text-sm">
            {{ session('success') }}
        </div>
        @endif
        @endif

    </div>

    {{-- ── RIGHT: Component matrix + Return history ─────────────────── --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Component × Size matrix --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Component Breakdown</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">Assigned vs returned per size and component</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full apple-table">
                    <thead>
                        <tr>
                            <th class="text-left">Component</th>
                            @foreach($sizes as $size)
                            <th class="text-center uppercase">{{ $size }}</th>
                            @endforeach
                            <th class="text-right">Total</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($components as $comp)
                        @php
                            $compTotal     = collect($sizes)->sum(fn($s) => $matrix[$s][$comp]['returned']);
                            $compAssigned  = $totalAssigned;
                            $compDone      = $compAssigned > 0 && $compTotal >= $compAssigned;
                            $compPartial   = !$compDone && $compTotal > 0;
                        @endphp
                        <tr>
                            <td class="font-medium capitalize text-[#1D1D1F]">{{ $comp }}</td>
                            @foreach($sizes as $size)
                            @php
                                $cell      = $matrix[$size][$comp];
                                $cellDone  = $cell['assigned'] > 0 && $cell['remaining'] === 0;
                                $cellEmpty = $cell['assigned'] === 0;
                            @endphp
                            <td class="text-center">
                                @if($cellEmpty)
                                <span class="text-[#D2D2D7] text-xs">—</span>
                                @elseif($cellDone)
                                <span class="text-[11px] font-semibold text-[#34C759]">{{ $cell['returned'] }}</span>
                                @else
                                <div class="text-[11px]">
                                    <span class="font-semibold {{ $cell['returned'] > 0 ? 'text-[#FF9500]' : 'text-[#86868B]' }}">{{ $cell['returned'] }}</span>
                                    <span class="text-[#D2D2D7]">/{{ $cell['assigned'] }}</span>
                                </div>
                                @endif
                            </td>
                            @endforeach
                            <td class="text-right tabular-nums">
                                <span class="{{ $compDone ? 'text-[#34C759]' : ($compPartial ? 'text-[#FF9500]' : 'text-[#86868B]') }} font-semibold">
                                    {{ $compTotal }} / {{ $compAssigned }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($compAssigned === 0)
                                <span class="text-[10px] text-[#D2D2D7]">—</span>
                                @elseif($compDone)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:#F0FFF4; color:#34C759;">DONE</span>
                                @elseif($compPartial)
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:#FFFBF0; color:#FF9500;">PARTIAL</span>
                                @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background:#F5F5F7; color:#86868B;">PENDING</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                            <td class="font-semibold text-xs uppercase tracking-wide">Assigned</td>
                            @foreach($sizes as $size)
                            <td class="text-center text-xs font-semibold tabular-nums text-[#1D1D1F]">
                                {{ $matrix[$size]['kameez']['assigned'] > 0 ? $matrix[$size]['kameez']['assigned'] : '—' }}
                            </td>
                            @endforeach
                            <td class="text-right font-bold tabular-nums">{{ $totalAssigned }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Return history --}}
        @if($stitchingReturns->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h2 class="text-sm font-semibold text-[#1D1D1F]">Return History</h2>
                <p class="text-xs text-[#6E6E73] mt-0.5">{{ $stitchingReturns->count() }} batch{{ $stitchingReturns->count() > 1 ? 'es' : '' }} logged</p>
            </div>
            @foreach($stitchingReturns as $batchIndex => $ret)
            @php
                $batchComponents = $ret->items->pluck('component')->unique()->values();
                $batchTotal      = $ret->items->sum('quantity');
            @endphp
            <div class="{{ !$loop->last ? 'border-b border-[#F2F2F7]' : '' }} px-5 py-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-semibold text-[#6E6E73] uppercase tracking-wide">
                            SR-{{ str_pad($ret->id, 4, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="text-xs text-[#6E6E73]">{{ $ret->return_date->format('d M Y') }}</span>
                        {{-- Component badges --}}
                        <div class="flex gap-1">
                            @foreach($batchComponents as $comp)
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded" style="background:#E8F4FD; color:#0066CC;">
                                {{ ucfirst($comp) }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-sm font-semibold text-green-700">{{ number_format((int)$batchTotal) }} pcs</span>
                        <span class="text-xs text-[#86868B] ml-1">total</span>
                    </div>
                </div>
                {{-- Size breakdown per component --}}
                @foreach($batchComponents as $comp)
                @php
                    $sizeOrder = ['xs' => 0, 's' => 1, 'm' => 2, 'l' => 3, 'xl' => 4];
                    $compItems = $ret->items->where('component', $comp)->sortBy(fn($i) => $sizeOrder[$i->size] ?? 99);
                @endphp
                <div class="mb-2">
                    <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1">{{ ucfirst($comp) }}</p>
                    <div class="flex gap-3 flex-wrap">
                        @foreach($compItems as $item)
                        <span class="text-xs text-[#1D1D1F]">
                            <span class="font-semibold uppercase">{{ $item->size }}</span>: {{ $item->quantity }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endforeach
                <p class="text-[10px] text-[#86868B] mt-2">Logged by {{ $ret->loggedBy->name ?? '—' }}</p>
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
