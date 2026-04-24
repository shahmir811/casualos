@extends('layouts.app')
@section('title', 'Customer Master List')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Customer Master List</span>
</div>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Customer Master List</h1>
        <p class="text-[#6E6E73] text-sm mt-1">{{ $customers->count() }} customers in the system</p>
    </div>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Name</th>
                <th class="text-left">Phone</th>
                <th class="text-left">City</th>
                <th class="text-right">Total Orders</th>
                <th class="text-left">Portal Link</th>
                <th class="text-left">Joined</th>
            </tr>
        </thead>
        <tbody>
            @forelse($customers as $customer)
            <tr>
                <td>
                    <a href="{{ route('customers.show', $customer) }}" class="font-medium text-[#0066CC] hover:underline">{{ $customer->name }}</a>
                </td>
                <td class="text-[#6E6E73]">{{ $customer->phone ?? '—' }}</td>
                <td class="text-[#6E6E73]">{{ $customer->city ?? '—' }}</td>
                <td class="text-right font-medium">{{ $customer->orders_count }}</td>
                <td>
                    <span class="text-xs text-[#86868B] font-mono">...{{ substr($customer->portal_token, -8) }}</span>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $customer->created_at->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-[#86868B] py-12">No customers found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
