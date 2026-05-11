@extends('layouts.app')

@section('title', 'Customers')

@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Customers</h1>
        <p class="text-[#6E6E73] text-sm mt-1">{{ $customers->total() }} total accounts</p>
    </div>
    @if(Auth::user()->role === 'admin')
    <a href="{{ route('customers.create') }}" class="btn-primary">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        New Customer
    </a>
    @endif
</div>

{{-- Search --}}
<form method="GET" class="mb-5">
    <div class="relative flex items-center">
        {{-- search icon (left, decorative) --}}
        <svg class="absolute left-3.5 w-4 h-4 text-[#86868B] pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>

        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Search by name, email, or phone..."
            class="w-full bg-white border border-[#D2D2D7] rounded-xl text-[#1D1D1F] text-sm pl-10 pr-24 py-2.5
                   placeholder-[#86868B] outline-none transition-all
                   focus:border-[#0071E3] focus:ring-2 focus:ring-[#0071E3]/20">

        {{-- clear link (only visible when there's a search term) --}}
        @if(request('search'))
        <a href="{{ route('customers.index') }}"
           class="absolute right-20 text-xs text-[#86868B] hover:text-[#1D1D1F] transition-colors">
            Clear
        </a>
        @endif

        <button type="submit"
            class="absolute right-2 bg-[#0071E3] hover:bg-[#0077ED] text-white text-xs font-medium
                   px-3.5 py-1.5 rounded-lg transition-colors">
            Search
        </button>
    </div>
</form>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Name</th>
                <th class="text-left">Contact</th>
                <th class="text-left">City</th>
                <th class="text-left">Orders</th>
                <th class="text-left">Advance Credit</th>
                <th class="text-left"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
            <tr>
                <td class="font-medium text-[#1D1D1F]">{{ $customer->name }}</td>
                <td>
                    <div class="text-sm">{{ $customer->contact_number }}</div>
                    <div class="text-[#86868B] text-xs">{{ $customer->email }}</div>
                </td>
                <td class="text-[#6E6E73]">{{ $customer->city }}</td>
                <td class="text-[#6E6E73]">{{ $customer->orders_count ?? '—' }}</td>
                <td>
                    @if($customer->advance_credit_balance > 0)
                        <span class="text-[#30D158] text-sm font-medium">PKR {{ number_format($customer->advance_credit_balance, 0) }}</span>
                    @else
                        <span class="text-[#86868B]">—</span>
                    @endif
                </td>
                <td>
                    <div class="flex items-center gap-3">
                        {{-- Copy portal link --}}
                        <button type="button"
                            onclick="copyPortalLink('{{ route('portal.show', $customer->portal_token) }}', this)"
                            title="Copy customer portal link"
                            class="text-[#0066CC] text-xs font-medium hover:underline flex items-center gap-1 transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            Portal Link
                        </button>
                        <a href="{{ route('customers.show', $customer) }}"
                           class="text-[#0066CC] text-sm hover:underline font-medium">
                            View →
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center text-[#86868B] py-12">No customers found.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $customers->links() }}</div>

<script>
function copyPortalLink(url, btn) {
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
