@extends('layouts.app')
@section('title', 'Record Wages')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('wages.index') }}" class="text-[#0066CC] hover:underline text-sm">Wages</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Record</span>
</div>

<div class="max-w-xl"
     x-data="{
        suits: {{ old('total_suits_stitched', 0) }},
        rate: {{ old('wage_rate', 0) }},
        selectedUnitId: '{{ old('stitching_unit_id', '') }}',
        units: {{ Js::from($units) }},
        get total() { return (this.suits || 0) * (this.rate || 0); },
        selectUnit(id) {
            this.selectedUnitId = id;
            const unit = this.units.find(u => u.id == id);
            this.rate = unit ? (unit.per_piece_rate || 0) : 0;
        }
     }">

    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Record Weekly Wages</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('wages.store') }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Catalogue</label>
                <select name="catalogue_id" class="apple-input" required>
                    <option value="">— Select catalogue —</option>
                    @foreach($catalogues as $cat)
                    <option value="{{ $cat->id }}" {{ old('catalogue_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Stitching Unit</label>
                @if($units->isEmpty())
                <p class="text-sm text-[#FF3B30]">No active per-piece stitching units found. Please add one first.</p>
                @else
                <select name="stitching_unit_id" class="apple-input" required
                        @change="selectUnit($event.target.value)">
                    <option value="">— Select unit —</option>
                    @foreach($units as $unit)
                    <option value="{{ $unit->id }}"
                            {{ old('stitching_unit_id') == $unit->id ? 'selected' : '' }}>
                        Unit {{ $unit->number }} — {{ $unit->name }}
                        @if($unit->per_piece_rate)
                            (Rs. {{ lacs_format($unit->per_piece_rate, 0) }}/pc)
                        @endif
                    </option>
                    @endforeach
                </select>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Week Start</label>
                    <input type="date" name="week_start" value="{{ old('week_start') }}" class="apple-input" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Week End (Friday)</label>
                    <input type="date" name="week_end" value="{{ old('week_end') }}" class="apple-input" required>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Total Suits Stitched</label>
                <input type="number" name="total_suits_stitched" x-model="suits"
                       value="{{ old('total_suits_stitched') }}" min="1" class="apple-input" placeholder="e.g. 120" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Rate (Rs. per suit)
                    <span class="text-[#86868B] font-normal normal-case text-[10px] ml-1">auto-filled from unit</span>
                </label>
                <input type="number" x-model="rate" step="0.01" min="0" class="apple-input"
                       placeholder="Select a unit above" readonly>
            </div>
        </div>

        {{-- Live calculation --}}
        <div class="card p-5 flex items-center justify-between">
            <div>
                <p class="text-xs text-[#6E6E73] uppercase tracking-widest mb-1">Calculated Total Wages</p>
                <p class="text-2xl font-light text-[#1D1D1F]">Rs. <span x-text="total.toLocaleString('en-PK', {maximumFractionDigits: 0})">0</span></p>
            </div>
            <div class="text-[#86868B] text-sm">
                <span x-text="suits || 0">0</span> suits × Rs. <span x-text="rate || 0">0</span>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save Wage Record</button>
            <a href="{{ route('wages.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
