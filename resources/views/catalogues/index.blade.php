@extends('layouts.app')

@section('title', 'Catalogues')

@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Catalogues</h1>
        <p class="text-[#6E6E73] text-sm mt-1">All season collections</p>
    </div>
    @if(Auth::user()->role === 'admin')
    <a href="{{ route('catalogues.create') }}" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Catalogue
    </a>
    @endif
</div>

@forelse($catalogues as $catalogue)
<div class="card mb-3 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between px-5 py-4">

        {{-- Cover Photo + Info — fully clickable as View link --}}
        <a href="{{ route('catalogues.show', $catalogue) }}"
           class="flex items-center gap-4 flex-1 min-w-0 group">
            <div class="w-14 h-14 bg-[#F5F5F7] rounded-xl overflow-hidden flex-shrink-0 border border-[#E8E8ED] group-hover:border-[#0066CC]/30 transition-colors">
                @if($catalogue->cover_photo)
                    <img src="{{ Storage::url($catalogue->cover_photo) }}" alt="{{ $catalogue->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#D2D2D7]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                @endif
            </div>

            <div>
                <div class="flex items-center gap-2.5 mb-1">
                    <h2 class="text-[#1D1D1F] text-sm font-semibold group-hover:text-[#0066CC] transition-colors">{{ $catalogue->name }}</h2>
                    <span class="badge {{ $catalogue->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                        {{ strtoupper($catalogue->status) }}
                    </span>
                </div>
                <p class="text-[#6E6E73] text-xs">
                    {{ $catalogue->designs_count }} designs ·
                    {{ lacs_format($catalogue->qty_per_design) }} qty/design ·
                    {{ $catalogue->orders_count }} orders
                </p>
            </div>
        </a>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('catalogues.show', $catalogue) }}"
               class="text-[#0066CC] text-xs font-medium hover:underline">
                View →
            </a>

            {{-- Copy Order Link --}}
            @if($catalogue->status === 'open')
            <button type="button"
                onclick="copyOrderLink('{{ route('order.public', $catalogue->order_token) }}', this)"
                class="text-[#0066CC] text-xs font-medium hover:underline flex items-center gap-1 transition-colors">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Order Link
            </button>
            @endif

            @if(Auth::user()->role === 'admin')
            <a href="{{ route('catalogues.edit', $catalogue) }}"
               class="text-[#6E6E73] text-xs font-medium hover:text-[#1D1D1F] transition-colors">
                Edit
            </a>
            @if($catalogue->status === 'open')
            <form id="form-close-cat-{{ $catalogue->id }}" method="POST" action="{{ route('catalogues.close', $catalogue) }}">@csrf</form>
            <button type="button"
                    class="text-[#FF9500] text-xs font-medium hover:text-[#FF6D00] transition-colors"
                    @click="$store.confirm.show({
                        title: 'Close Catalogue',
                        message: `Close {{ $catalogue->name }}? No new orders will be accepted until it is reopened.`,
                        formId: 'form-close-cat-{{ $catalogue->id }}',
                        confirmText: 'Close Catalogue',
                        danger: true
                    })">
                Close
            </button>
            @else
            <form method="POST" action="{{ route('catalogues.reopen', $catalogue) }}" class="inline">
                @csrf
                <button type="submit"
                    class="text-[#0066CC] text-xs font-medium hover:underline">
                    Reopen
                </button>
            </form>
            @endif
            @endif
        </div>
    </div>
</div>
@empty
<div class="card p-12 text-center">
    <svg class="w-12 h-12 text-[#D2D2D7] mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
    </svg>
    <p class="text-[#6E6E73] text-sm">No catalogues found.</p>
    @if(Auth::user()->role === 'admin')
    <a href="{{ route('catalogues.create') }}" class="btn-primary mt-4 inline-flex">
        Create your first catalogue
    </a>
    @endif
</div>
@endforelse

{{-- Pagination --}}
<div class="mt-6">
    {{ $catalogues->links() }}
</div>

<script>
function copyOrderLink(url, btn) {
    navigator.clipboard.writeText(url).then(() => {
        const orig = btn.innerHTML;
        btn.innerHTML = `<svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Copied!`;
        btn.classList.add('text-[#30D158]');
        btn.classList.remove('text-[#0066CC]');
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.classList.remove('text-[#30D158]');
            btn.classList.add('text-[#0066CC]');
        }, 2000);
    });
}
</script>

@endsection
