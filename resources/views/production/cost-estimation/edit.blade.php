@extends('layouts.app')

@section('title', 'Cost Estimation — ' . $design->name)

@section('content')

@php
    $leftCategories  = ['fabric_cost', 'dupatta', 'block_printing', 'dying'];
    $rightCategories = ['computer_embroidery', 'hand_embroidery', 'accessories', 'stitching_cost'];
    $canEdit = Auth::user()->role !== 'creative_head';

    $dateValue = old('estimation_date', optional($costEstimation?->estimation_date)->format('Y-m-d')) ?: now()->format('Y-m-d');
@endphp

<div class="flex items-center gap-3 mb-7 flex-wrap">
    <a href="{{ route('catalogues.index') }}" class="text-[#0066CC] hover:underline text-sm">Catalogues</a>
    <span class="text-[#86868B]">/</span>
    <a href="{{ route('catalogues.show', $catalogue) }}" class="text-[#0066CC] hover:underline text-sm">{{ $catalogue->name }}</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">{{ $design->name }} — Cost Estimation</span>
</div>

<h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-1">Cost Estimation Sheet</h1>
<p class="text-[#6E6E73] text-sm mb-6">{{ $catalogue->name }} · {{ $design->name }}</p>

@if($errors->any())
<div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
    <ul class="list-disc list-inside space-y-1">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

<form id="form-cost-estimation" method="POST" action="{{ route('designs.cost-estimation.update', $design) }}"
      x-data="{
        categories: {{ Js::from(array_keys($itemsByCategory)) }},
        items: {{ Js::from($itemsByCategory) }},
        pakkiAmount: {{ (float) $pakkiAmount }},
        addRow(cat) {
            this.items[cat].push({ particulars: '', avg: '', qty: '', rate: '' });
        },
        removeRow(cat, idx) {
            this.items[cat].splice(idx, 1);
        },
        rowAmount(row) {
            return (parseFloat(row.qty) || 0) * (parseFloat(row.rate) || 0);
        },
        subtotal(cat) {
            return (this.items[cat] || []).reduce((s, r) => s + this.rowAmount(r), 0);
        },
        get totalCost() {
            return this.categories.reduce((s, c) => s + this.subtotal(c), 0) + this.pakkiAmount;
        },
        get perUnitCost() {
            return {{ $productionPlanQty }} > 0 ? this.totalCost / {{ $productionPlanQty }} : 0;
        },
        formatNum(n) {
            const neg = n < 0;
            const str = String(Math.round(Math.abs(n)));
            const formatted = str.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            return (neg ? '-' : '') + formatted;
        },
        formatPkr(n) {
            return 'PKR ' + this.formatNum(n);
        }
      }">
    @csrf

    {{-- Header fields --}}
    <div class="card p-6 mb-6">
        <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-4">Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Date</label>
                <input type="date" name="estimation_date" class="apple-input"
                       value="{{ $dateValue }}" {{ $canEdit ? '' : 'disabled' }}>
            </div>
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Production Qty <span class="font-normal normal-case text-[#86868B]">(from Fabric Batch)</span></label>
                <input type="text" class="apple-input bg-[#F5F5F7]" value="{{ number_format($productionPlanQty) }}" readonly>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Stitched By <span class="font-normal normal-case text-[#86868B]">(from Stitching Returns)</span></label>
                <div class="apple-input bg-[#F5F5F7] flex items-center min-h-[42px]">
                    @if($stitchedByDisplay)
                        <span class="text-[#1D1D1F] text-sm">{{ $stitchedByDisplay }}</span>
                    @else
                        <span class="text-[#86868B] text-sm">No stitching returns logged yet</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Cost sections — two columns, mirroring the paper sheet --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="space-y-6">
            @foreach($leftCategories as $category)
                @include('production.cost-estimation._category', ['category' => $category, 'label' => $categories[$category], 'canEdit' => $canEdit])
            @endforeach
        </div>
        <div class="space-y-6">
            {{-- Pakki Embroidery — fully system-computed, never a manual list --}}
            <div class="card overflow-hidden {{ $pakki === null ? 'opacity-60' : '' }}">
                <div class="px-5 py-3 border-b border-[#F2F2F7] flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-[#1D1D1F]">Pakki Embroidery</h3>
                    <span class="text-xs font-semibold text-[#0071E3] tabular-nums">PKR {{ number_format($pakkiAmount, 0) }}</span>
                </div>
                @if($pakki === null)
                <div class="px-5 py-5 text-center">
                    <p class="text-xs text-[#86868B]">
                        {{ $design->needs_naeem_pakki ? 'No Naeem Pakki batch recorded yet for this design.' : 'Not required for this design.' }}
                    </p>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-[#F2F2F7] bg-[#FAFAFA]">
                                <th class="text-left text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-3 py-2">Particulars</th>
                                <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-20">Avg</th>
                                <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-28">Qty</th>
                                <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-28">Rate</th>
                                <th class="text-right text-[11px] font-semibold text-[#6E6E73] uppercase tracking-widest px-2 py-2 w-28">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="px-3 py-2 text-xs text-[#1D1D1F]">Naeem Pakki</td>
                                <td class="px-2 py-2 text-right text-xs text-[#C7C7CC]">—</td>
                                <td class="px-2 py-2 text-right text-xs text-[#1D1D1F] tabular-nums">{{ number_format($pakki['qty']) }}</td>
                                <td class="px-2 py-2 text-right text-xs text-[#1D1D1F] tabular-nums">{{ number_format($pakki['rate'], 2) }}</td>
                                <td class="px-2 py-2 text-right text-xs font-medium text-[#1D1D1F] tabular-nums">{{ number_format($pakki['amount'], 0) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="px-3 py-2 text-[11px] text-[#86868B] border-t border-[#F2F2F7]">Sum of pieces sent × rate across every Naeem Pakki batch for this design — system-calculated, not editable.</p>
                @endif
            </div>

            @foreach($rightCategories as $category)
                @include('production.cost-estimation._category', ['category' => $category, 'label' => $categories[$category], 'canEdit' => $canEdit])
            @endforeach
        </div>
    </div>

    {{-- Summary --}}
    <div class="card p-6 mb-6">
        <h3 class="text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-4">Summary</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div>
                <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Actual Cost Total</p>
                <p class="text-[#1D1D1F] text-xl font-semibold tabular-nums" x-text="formatPkr(totalCost)"></p>
            </div>
            <div>
                <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Per Unit Cost</p>
                <p class="text-[#FF9500] text-xl font-semibold tabular-nums" x-text="formatPkr(perUnitCost)"></p>
            </div>
            <div>
                <label class="block text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Market Rate</label>
                <input type="number" step="0.01" min="0" name="market_rate" class="apple-input" value="{{ old('market_rate', $costEstimation?->market_rate) }}" {{ $canEdit ? '' : 'disabled' }}>
            </div>
            <div>
                <label class="block text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Margin</label>
                <input type="number" step="0.01" name="margin" class="apple-input" value="{{ old('margin', $costEstimation?->margin) }}" {{ $canEdit ? '' : 'disabled' }}>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Prepared By</label>
                <input type="text" class="apple-input bg-[#F5F5F7]" value="{{ $costEstimation?->preparedBy->name ?? Auth::user()->name }}" readonly>
            </div>
            <div>
                <label class="block text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Approved By</label>
                <input type="text" name="approved_by" class="apple-input" value="{{ old('approved_by', $costEstimation?->approved_by) }}" {{ $canEdit ? '' : 'disabled' }}>
            </div>
        </div>
    </div>

    @if($canEdit)
    <div class="flex gap-3 items-center">
        <button type="submit" class="btn-primary">Save Cost Estimation</button>
        <a href="{{ route('catalogues.show', $catalogue) }}" class="btn-secondary">Cancel</a>
    </div>
    @else
    <a href="{{ route('catalogues.show', $catalogue) }}" class="btn-secondary">Back</a>
    @endif
</form>

@endsection
