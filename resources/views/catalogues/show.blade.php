@extends('layouts.app')

@section('title', $catalogue->name)

@section('content')

{{-- Header --}}
<div class="flex items-start justify-between mb-7">
    <div>
        <a href="{{ route('catalogues.index') }}" class="text-[#0066CC] text-sm hover:underline">← Catalogues</a>
        <div class="flex items-center gap-3 mt-3">
            <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">{{ $catalogue->name }}</h1>
            <span class="badge {{ $catalogue->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                {{ $catalogue->status }}
            </span>
        </div>
        <p class="text-[#6E6E73] text-xs mt-1">
            Created by {{ $catalogue->createdBy->name ?? 'System' }} · {{ $catalogue->created_at->format('d M Y') }}
        </p>
    </div>

    @if(in_array(Auth::user()->role, ['admin', 'production_manager']))
    <div class="flex items-center gap-2.5">
        <a href="{{ route('catalogues.edit', $catalogue) }}" class="btn-secondary">
            Edit
        </a>

        @if($catalogue->status === 'open')
        <form id="form-close-catalogue" method="POST" action="{{ route('catalogues.close', $catalogue) }}">@csrf</form>
        <button type="button" class="btn-secondary" style="color:#FF9500; border-color:#FFCDD0;"
                @click="$store.confirm.show({
                    title: 'Close Catalogue',
                    message: '{{ $catalogue->name }} will no longer accept new orders. You can reopen it at any time.',
                    formId: 'form-close-catalogue',
                    confirmText: 'Close Catalogue',
                    danger: true
                })">
            Close Catalogue
        </button>
        @else
        <form method="POST" action="{{ route('catalogues.reopen', $catalogue) }}">
            @csrf
            <button type="submit" class="btn-primary">
                Reopen
            </button>
        </form>
        @endif
    </div>
    @endif
</div>

{{-- Stats Row --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-7">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Qty Per Design</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ lacs_format($catalogue->qty_per_design) }}</p>
        <p class="text-[#86868B] text-xs mt-0.5">per design</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Qty Ordered</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ lacs_format($totalQtyOrdered) }}</p>
        <p class="text-[#86868B] text-xs mt-0.5">suits across all orders</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Pieces Ordered</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ lacs_format($totalOrdered) }}</p>
        <p class="text-[#86868B] text-xs mt-0.5">across all designs</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Available</p>
        <p class="text-2xl font-light {{ $available === 0 ? 'text-[#FF3B30]' : 'text-[#30D158]' }}">{{ lacs_format($available) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Total Orders</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ $ordersCount }}</p>
    </div>
</div>

{{-- Share Link --}}
@if(in_array(Auth::user()->role, ['admin', 'production_manager']) && $catalogue->status === 'open' && $shareUrl)
<div class="card p-5 mb-7" x-data="{ copied: false }">
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-3">Shareable Order Link</p>
    <div class="flex items-center gap-3">
        <input type="text" value="{{ $shareUrl }}" readonly
            class="apple-input text-xs flex-1 text-[#6E6E73]">
        <button @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)"
            class="btn-primary flex-shrink-0">
            <span x-show="!copied">Copy Link</span>
            <span x-show="copied">Copied ✓</span>
        </button>
    </div>
    <p class="text-[#86868B] text-xs mt-2">Share this link on WhatsApp for customers to place orders directly.</p>
</div>
@endif

{{-- Additional Info --}}
@if($catalogue->notes)
<div class="card p-5 mb-7">
    <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Notes</p>
    <p class="text-[#1D1D1F] text-sm">{{ $catalogue->notes }}</p>
</div>
@endif

{{-- Designs Section --}}
<div class="flex items-center justify-between mb-4">
    <h2 class="text-[#1D1D1F] text-sm font-semibold">Designs ({{ $catalogue->designs->count() }})</h2>
    @if(in_array(Auth::user()->role, ['admin', 'creative_head']))
    <a href="{{ route('catalogues.designs.create', $catalogue) }}" class="btn-primary">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Add Design
    </a>
    @endif
</div>

@if($catalogue->designs->isEmpty())
<div class="card p-12 text-center mb-7">
    <p class="text-[#6E6E73] text-sm">No designs yet.</p>
    @if(in_array(Auth::user()->role, ['admin', 'creative_head']))
    <a href="{{ route('catalogues.designs.create', $catalogue) }}" class="btn-primary mt-4 inline-flex">
        Add First Design
    </a>
    @endif
</div>
@else
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-7">
    @foreach($catalogue->designs as $design)
    <div class="card overflow-hidden hover:shadow-md transition-shadow">

        {{-- Design Photo --}}
        <div class="aspect-square bg-[#F5F5F7] overflow-hidden">
            @if($design->photo)
                <img src="{{ Storage::url($design->photo) }}" alt="{{ $design->name }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-[#D2D2D7]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            @endif
        </div>

        {{-- Design Info --}}
        <div class="p-4">
            <div class="flex items-start justify-between">
                {{-- Left: name + prices --}}
                <div class="flex-1 min-w-0 mr-2">
                    <h3 class="text-[#1D1D1F] text-sm font-semibold leading-tight mb-1.5">{{ $design->name }}</h3>
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-2">
                            <span class="text-[#6E6E73] text-xs font-medium uppercase tracking-wide" style="min-width:52px;">Selling</span>
                            <span class="text-[#1D1D1F] text-sm font-semibold">PKR {{ lacs_format($design->selling_price, 0) }}</span>
                        </div>
                        @if($design->discount_price)
                        <div class="flex items-center gap-2">
                            <span class="text-[#6E6E73] text-xs font-medium uppercase tracking-wide" style="min-width:52px;">Discount</span>
                            <span class="text-[#34C759] text-sm font-semibold">PKR {{ lacs_format($design->discount_price, 0) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                {{-- Right: badges --}}
                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                    <span class="badge {{ $design->manufacturing_type === 'in_house' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ $design->manufacturing_type === 'in_house' ? 'In-House' : 'Out' }}
                    </span>
                    @if($design->needs_naeem_pakki)
                    <span class="badge bg-amber-100 text-amber-700">Naeem Pakki</span>
                    @endif
                </div>
            </div>

            @if(in_array(Auth::user()->role, ['admin', 'creative_head']))
            <div class="flex items-center gap-3 mt-3 pt-3 border-t border-[#F2F2F7]">
                <a href="{{ route('designs.edit', $design) }}" class="text-[#0066CC] text-xs hover:underline">Edit</a>
                @if(Auth::user()->role === 'admin')
                <form id="form-delete-design-{{ $design->id }}" method="POST" action="{{ route('designs.destroy', $design) }}">
                    @csrf @method('DELETE')
                </form>
                <button type="button" class="text-[#FF3B30] text-xs hover:underline"
                        @click="$store.confirm.show({
                            title: 'Delete Design',
                            message: `Permanently delete {{ $design->name }}? This cannot be undone.`,
                            formId: 'form-delete-design-{{ $design->id }}',
                            confirmText: 'Delete',
                            danger: true
                        })">
                    Delete
                </button>
                @endif
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
