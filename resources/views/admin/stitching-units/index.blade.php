@extends('layouts.app')
@section('title', 'Stitching Units')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Stitching Units</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Manage the stitching units used in production assignments</p>
    </div>
    <a href="{{ route('stitching-units.create') }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Unit
    </a>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Unit #</th>
                <th class="text-left">Name</th>
                <th class="text-left">Payment Type</th>
                <th class="text-left">Rate</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($stitchingUnits as $unit)
            <tr class="{{ $unit->is_active ? '' : 'opacity-50' }}">
                <td>
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl text-sm font-bold"
                          style="background:#F5EEFF; color:#AF52DE;">
                        {{ $unit->number }}
                    </span>
                </td>
                <td class="font-medium text-[#1D1D1F]">{{ $unit->name }}</td>
                <td>
                    @if($unit->isPerPiece())
                    <span class="badge" style="background:#E3F2FD; color:#1565C0; border-color:#BBDEFB;">Per Piece</span>
                    @else
                    <span class="badge" style="background:#FFF8E1; color:#E65100; border-color:#FFE0B2;">Salary</span>
                    @endif
                </td>
                <td class="text-[#6E6E73] text-sm">
                    @if($unit->isPerPiece() && $unit->per_piece_rate)
                        Rs. {{ lacs_format($unit->per_piece_rate, 0) }}/pc
                    @else
                        <span class="text-[#D2D2D7]">—</span>
                    @endif
                </td>
                <td>
                    @if($unit->is_active)
                    <span class="badge" style="background:#F0FFF4; color:#15803D; border-color:#BBF7D0;">Active</span>
                    @else
                    <span class="badge" style="background:#FEF2F2; color:#DC2626; border-color:#FECACA;">Inactive</span>
                    @endif
                </td>
                <td class="text-right">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('stitching-units.edit', $unit) }}"
                           class="text-[#0066CC] text-sm hover:underline">Edit</a>
                        <form method="POST" action="{{ route('stitching-units.toggle', $unit) }}">
                            @csrf
                            <button type="submit"
                                    class="text-sm {{ $unit->is_active ? 'text-[#FF3B30]' : 'text-[#34C759]' }} hover:underline">
                                {{ $unit->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-[#86868B] py-12">No stitching units found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-4 text-xs text-[#86868B]">
    Inactive units will not appear in production assignment or stitching return forms.
    Salary-based units are tracked externally — CasualiteOS only tracks per-piece units for wages.
</p>

@endsection
