@extends('layouts.app')

@section('title', 'Reports')

@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Reports</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Business intelligence and operations reports</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

    @php
    $reports = [
        ['title' => 'Catalogue Summary', 'desc' => 'Orders, pieces, and revenue per catalogue', 'route' => 'reports.catalogue-summary', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['title' => 'Customer Orders', 'desc' => 'All orders grouped by customer', 'route' => 'reports.customer-orders', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['title' => 'Customer Ledger', 'desc' => 'Advance credit and payment history', 'route' => 'reports.customer-ledger', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['title' => 'Customer Master List', 'desc' => 'Full customer database export', 'route' => 'reports.customer-master-list', 'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
        ['title' => 'Production Status', 'desc' => 'Fabric batches and stitching progress', 'route' => 'reports.production-status', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['title' => 'Stitching Reconciliation', 'desc' => 'Sent vs received vs damage reconciliation', 'route' => 'reports.stitching-reconciliation', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ['title' => 'Packed Inventory', 'desc' => 'Press pack quantities by design', 'route' => 'reports.packed-inventory', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
        ['title' => 'Dispatch History', 'desc' => 'All dispatched orders and delivery status', 'route' => 'reports.dispatch-history', 'icon' => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
        ['title' => 'Outsourced Designs', 'desc' => 'Designs sent for outsourced stitching', 'route' => 'reports.outsourced-designs', 'icon' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
        ['title' => 'Damage & Reductions', 'desc' => 'Order reductions and damage log', 'route' => 'reports.damage-reductions', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        ['title' => 'Payroll History', 'desc' => 'Weekly wage records by catalogue', 'route' => 'reports.payroll-history', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ['title' => 'Activity Log', 'desc' => 'System audit trail and user actions', 'route' => 'reports.activity-log', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
    ];

    $paymentReports = [
        ['title' => 'Customer Order Bill', 'desc' => 'Total bill, amount received, and outstanding balance per customer per catalogue', 'route' => 'reports.customer-order-bill', 'icon' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5'],
        ['title' => 'Bank Account Breakdown', 'desc' => 'Payments per customer broken down by each bank account for a catalogue', 'route' => 'reports.bank-account-breakdown', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
        ['title' => 'Receivables by Bank', 'desc' => 'Customers with outstanding balances and their payment history per bank account', 'route' => 'reports.receivables-by-bank', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['title' => 'Bank Collection', 'desc' => 'Actual payments collected per bank account vs expected amounts for a catalogue', 'route' => 'reports.bank-collection', 'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
    ];
    @endphp

    @foreach($reports as $report)
    <a href="{{ route($report['route']) }}"
       class="card p-5 hover:shadow-md hover:border-[#0071E3] transition-all group">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-[#EBF4FF] rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-[#0071E3] transition-colors">
                <svg class="w-5 h-5 text-[#0071E3] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $report['icon'] }}"/>
                </svg>
            </div>
            <div>
                <h3 class="text-[#1D1D1F] font-semibold text-sm group-hover:text-[#0071E3] transition-colors leading-tight">{{ $report['title'] }}</h3>
                <p class="text-[#6E6E73] text-xs mt-1 leading-relaxed">{{ $report['desc'] }}</p>
            </div>
        </div>
    </a>
    @endforeach

</div>

<div class="mt-8 mb-4">
    <h2 class="text-base font-semibold text-[#1D1D1F]">Payment Reports</h2>
    <p class="text-[#6E6E73] text-sm mt-0.5">Catalogue-based financial reports for accountants — viewable on screen and downloadable as PDF</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($paymentReports as $report)
    <a href="{{ route($report['route']) }}"
       class="card p-5 hover:shadow-md hover:border-[#0071E3] transition-all group">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 bg-[#FFF3E0] rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-[#0071E3] transition-colors">
                <svg class="w-5 h-5 text-[#E65100] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $report['icon'] }}"/>
                </svg>
            </div>
            <div>
                <h3 class="text-[#1D1D1F] font-semibold text-sm group-hover:text-[#0071E3] transition-colors leading-tight">{{ $report['title'] }}</h3>
                <p class="text-[#6E6E73] text-xs mt-1 leading-relaxed">{{ $report['desc'] }}</p>
            </div>
        </div>
    </a>
    @endforeach
</div>

@endsection
