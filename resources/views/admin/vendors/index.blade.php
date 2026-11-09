@extends('layouts.app')
@section('title', 'Vendors')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Vendors</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Manage the vendors available when assigning a catalogue's production</p>
    </div>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-sm" style="background:#FFF0EF; color:#FF3B30; border:1px solid #FFCDD0;">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Add Vendor --}}
<div class="card mb-6 p-5">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-4">Add Vendor</h2>
    <form method="POST" action="{{ route('vendors.store') }}" class="flex items-end gap-3">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">
                Vendor Name <span class="text-[#FF3B30]">*</span>
            </label>
            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100"
                   class="apple-input" placeholder="e.g. Al-Rehman Textiles">
        </div>
        <div class="flex-shrink-0 pb-px">
            <button type="submit" class="btn-primary">Add Vendor</button>
        </div>
    </form>
</div>

{{-- Vendors List --}}
<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Name</th>
                <th class="text-left">Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($vendors as $vendor)
            <tr class="{{ $vendor->is_active ? '' : 'opacity-50' }}">
                <td class="font-medium text-[#1D1D1F]">{{ $vendor->name }}</td>
                <td>
                    @if($vendor->is_active)
                    <span class="badge" style="background:#F0FFF4; color:#15803D; border-color:#BBF7D0;">Active</span>
                    @else
                    <span class="badge" style="background:#FEF2F2; color:#DC2626; border-color:#FECACA;">Inactive</span>
                    @endif
                </td>
                <td class="text-right">
                    <form method="POST" action="{{ route('vendors.toggle', $vendor) }}">
                        @csrf
                        <button type="submit"
                                class="text-sm {{ $vendor->is_active ? 'text-[#FF3B30]' : 'text-[#34C759]' }} hover:underline">
                            {{ $vendor->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center text-[#86868B] py-12">No vendors added yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="mt-4 text-xs text-[#86868B]">
    Inactive vendors will not appear in the Production dropdown when creating a new catalogue.
</p>

@endsection
