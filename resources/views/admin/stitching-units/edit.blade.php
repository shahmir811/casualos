@extends('layouts.app')
@section('title', 'Edit Stitching Unit')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('stitching-units.index') }}" class="text-[#0066CC] hover:underline text-sm">Stitching Units</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Edit Unit {{ $stitchingUnit->number }}</span>
</div>

<div class="max-w-lg" x-data="{ paymentType: '{{ old('payment_type', $stitchingUnit->payment_type) }}' }">
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-[#1D1D1F] mb-6">
            Edit Unit {{ $stitchingUnit->number }}
        </h2>

        @if($errors->any())
        <div class="mb-5 px-4 py-3 rounded-xl text-sm" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA;">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('stitching-units.update', $stitchingUnit) }}" class="space-y-5">
            @csrf
            @method('PUT')

            {{-- Unit Number (read-only) --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Unit Number
                </label>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl text-base font-bold"
                          style="background:#F5EEFF; color:#AF52DE;">
                        {{ $stitchingUnit->number }}
                    </span>
                    <span class="text-sm text-[#86868B]">Unit numbers cannot be changed</span>
                </div>
            </div>

            {{-- Name --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Name <span class="text-[#FF3B30]">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $stitchingUnit->name) }}"
                       placeholder="e.g. Bashir Unit, City Tailors"
                       class="apple-input" required>
            </div>

            {{-- Payment Type --}}
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Payment Type <span class="text-[#FF3B30]">*</span>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_type" value="per_piece"
                               x-model="paymentType" class="sr-only peer"
                               {{ old('payment_type', $stitchingUnit->payment_type) === 'per_piece' ? 'checked' : '' }}>
                        <div class="flex flex-col items-center justify-center p-4 border-2 rounded-xl text-center transition-all
                                    border-[#E8E8ED] bg-white text-[#6E6E73]
                                    peer-checked:border-[#0071E3] peer-checked:bg-[#F0F7FF] peer-checked:text-[#0071E3]
                                    hover:border-[#0071E3]">
                            <span class="text-sm font-semibold">Per Piece</span>
                            <span class="text-[10px] mt-0.5 text-current opacity-70">Wages calculated by CasualOS</span>
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_type" value="salary"
                               x-model="paymentType" class="sr-only peer"
                               {{ old('payment_type', $stitchingUnit->payment_type) === 'salary' ? 'checked' : '' }}>
                        <div class="flex flex-col items-center justify-center p-4 border-2 rounded-xl text-center transition-all
                                    border-[#E8E8ED] bg-white text-[#6E6E73]
                                    peer-checked:border-[#FF9500] peer-checked:bg-[#FFF8F0] peer-checked:text-[#FF9500]
                                    hover:border-[#FF9500]">
                            <span class="text-sm font-semibold">Salary</span>
                            <span class="text-[10px] mt-0.5 text-current opacity-70">Managed externally</span>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Salary Amount --}}
            <div x-show="paymentType === 'salary'" x-cloak>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                    Monthly Salary (Rs.) <span class="text-[#86868B] font-normal normal-case text-[10px]">optional</span>
                </label>
                <input type="number" name="salary_amount"
                       value="{{ old('salary_amount', $stitchingUnit->salary_amount) }}"
                       placeholder="0" min="0" step="0.01"
                       class="apple-input">
                <p class="mt-1.5 text-[10px] text-[#86868B]">For reference only — salary units are tracked externally.</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="{{ route('stitching-units.index') }}" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
