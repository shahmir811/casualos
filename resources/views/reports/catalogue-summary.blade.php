@extends('layouts.app')
@section('title', 'Catalogue Summary')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Catalogue Summary</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Catalogue Summary</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Orders, designs, and revenue per catalogue</p>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Catalogue</th>
                <th class="text-left">Status</th>
                <th class="text-right">Designs</th>
                <th class="text-right">Orders</th>
                <th class="text-right">Total Revenue</th>
                <th class="text-right">Avg Order</th>
                <th class="text-left">Created</th>
            </tr>
        </thead>
        <tbody>
            @forelse($catalogues as $cat)
            @php
                $revenue = $cat->orders->sum('total_amount');
                $orderCount = $cat->orders_count;
                $avg = $orderCount > 0 ? $revenue / $orderCount : 0;
            @endphp
            <tr>
                <td class="font-medium">{{ $cat->name }}</td>
                <td>
                    <span class="badge {{ $cat->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                        {{ ucfirst($cat->status) }}
                    </span>
                </td>
                <td class="text-right">{{ $cat->designs_count }}</td>
                <td class="text-right">{{ $orderCount }}</td>
                <td class="text-right font-semibold">PKR {{ number_format($revenue, 0) }}</td>
                <td class="text-right text-[#6E6E73] text-xs">PKR {{ number_format($avg, 0) }}</td>
                <td class="text-[#6E6E73] text-xs">{{ $cat->created_at->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-[#86868B] py-12">No catalogues found.</td></tr>
            @endforelse
        </tbody>
        @if($catalogues->count())
        <tfoot>
            <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                <td class="px-5 py-3 font-semibold text-sm" colspan="4">Total</td>
                <td class="px-5 py-3 text-right font-bold text-sm">PKR {{ number_format($catalogues->sum(fn($c) => $c->orders->sum('total_amount')), 0) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@endsection
