<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Casualite</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Apple brand fonts */
        body {
            font-family: 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .font-display {
            font-family: 'SF Pro Display', 'SF Pro Text', 'Helvetica Neue', Helvetica, Arial, sans-serif;
        }

        /* Apple brand colors */
        :root {
            --bg:       #F5F5F7;
            --surface:  #FFFFFF;
            --primary:  #0071E3;
            --link:     #0066CC;
            --text:     #1D1D1F;
            --text-2:   #6E6E73;
            --text-3:   #86868B;
            --border:   #D2D2D7;
            --border-2: #E8E8ED;
            --input-bg: #F5F5F7;
            --sidebar-w: 240px;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D2D2D7; border-radius: 3px; }

        [x-cloak] { display: none !important; }

        /* Sidebar */
        #sidebar {
            width: var(--sidebar-w);
            transition: transform 0.3s ease;
        }

        /* Nav active */
        .nav-item { transition: background 0.15s ease, color 0.15s ease; }
        .nav-item.active { background: #EBF4FF; color: #0071E3; }
        .nav-item:hover:not(.active) { background: #F5F5F7; }

        /* Pill buttons */
        .btn-primary {
            background: #0071E3;
            color: #fff;
            border-radius: 980px;
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            padding: 0.55rem 1.25rem;
            transition: background 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        .btn-primary:hover { background: #0077ED; }
        .btn-primary:active { background: #006EDE; }

        .btn-secondary {
            background: #F5F5F7;
            color: #0066CC;
            border: 1px solid #D2D2D7;
            border-radius: 980px;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.5rem 1.2rem;
            transition: background 0.15s, border-color 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        .btn-secondary:hover { background: #EBEBF0; border-color: #B2B2B7; }

        .btn-danger {
            background: #FFF0EF;
            color: #FF3B30;
            border: 1px solid #FFCDD0;
            border-radius: 980px;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.5rem 1.2rem;
            transition: background 0.15s;
            display: inline-flex;
            align-items: center;
        }
        .btn-danger:hover { background: #FFE5E4; }

        /* Apple-style inputs */
        .apple-input {
            width: 100%;
            background: #F5F5F7;
            border: 1px solid transparent;
            border-radius: 10px;
            color: #1D1D1F;
            font-size: 0.9rem;
            padding: 0.7rem 1rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .apple-input:focus {
            border-color: #0071E3;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.15);
        }
        .apple-input::placeholder { color: #86868B; }

        /* Cards */
        .card {
            background: #FFFFFF;
            border: 1px solid #E8E8ED;
            border-radius: 12px;
        }

        /* Table */
        .apple-table thead th {
            background: #F5F5F7;
            color: #6E6E73;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.75rem 1.25rem;
        }
        .apple-table tbody tr {
            border-bottom: 1px solid #F2F2F7;
            transition: background 0.1s;
        }
        .apple-table tbody tr:hover { background: #FAFAFA; }
        .apple-table tbody td {
            padding: 0.85rem 1.25rem;
            color: #1D1D1F;
            font-size: 0.875rem;
        }
        .apple-table tbody tr:last-child { border-bottom: none; }

        /* Badge */
        .badge {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 0.2rem 0.6rem;
            border-radius: 980px;
        }

        /* Stat card */
        .stat-card {
            background: #FFFFFF;
            border: 1px solid #E8E8ED;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
        }

        /* Overlay for mobile sidebar */
        #sidebar-overlay {
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(2px);
        }
    </style>
</head>
<body class="bg-[#F5F5F7] text-[#1D1D1F] min-h-screen" x-data="{ sidebarOpen: false }">

{{-- Mobile Overlay --}}
<div id="sidebar-overlay"
     x-show="sidebarOpen"
     x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 z-20 lg:hidden">
</div>

<div class="flex min-h-screen">

    {{-- ===================== SIDEBAR ===================== --}}
    <aside id="sidebar"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
           class="fixed top-0 left-0 h-full bg-white border-r border-[#D2D2D7] z-30 flex flex-col overflow-y-auto lg:sticky lg:top-0 lg:h-screen">

        {{-- Brand --}}
        <div class="px-5 py-5 border-b border-[#F2F2F7]">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-[#0071E3] flex items-center justify-center flex-shrink-0">
                    <svg width="20" height="20" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="28" cy="28" r="18" stroke="white" stroke-width="1.5"/>
                        <line x1="36" y1="18" x2="36" y2="46" stroke="white" stroke-width="1.5"/>
                        <line x1="36" y1="46" x2="50" y2="46" stroke="white" stroke-width="1.5"/>
                    </svg>
                </div>
                <div>
                    <p class="font-display font-semibold text-[#1D1D1F] text-sm leading-tight tracking-tight">Casualite</p>
                    <p class="text-[#86868B] text-[10px] tracking-wide uppercase leading-tight">Operations</p>
                </div>
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="px-3 py-2 space-y-0">

            @php $r = Auth::user()->role; @endphp

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            {{-- Catalogue --}}
            <p class="px-3 pt-3 pb-0.5 text-[10px] font-semibold text-[#86868B] tracking-widest uppercase">Catalogue</p>

            <a href="{{ route('catalogues.index') }}"
               class="nav-item {{ request()->routeIs('catalogues.*') || request()->routeIs('designs.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Catalogues
            </a>

            {{-- Customers --}}
            @if(in_array($r, ['admin','accountant']))
            <p class="px-3 pt-3 pb-0.5 text-[10px] font-semibold text-[#86868B] tracking-widest uppercase">Sales</p>

            <a href="{{ route('customers.index') }}"
               class="nav-item {{ request()->routeIs('customers.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Customers
            </a>

            <a href="{{ route('orders.index') }}"
               class="nav-item {{ request()->routeIs('orders.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Orders
            </a>
            @endif

            {{-- Production --}}
            @if(in_array($r, ['admin','manager']))
            <p class="px-3 pt-3 pb-0.5 text-[10px] font-semibold text-[#86868B] tracking-widest uppercase">Production</p>

            <a href="{{ route('fabric-batches.index') }}"
               class="nav-item {{ request()->routeIs('fabric-batches.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                Fabric Batches
            </a>

            <a href="{{ route('production-assignments.index') }}"
               class="nav-item {{ request()->routeIs('production-assignments.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Assignments
            </a>

            <a href="{{ route('naeem-pakki-sends.index') }}"
               class="nav-item {{ request()->routeIs('naeem-pakki-sends.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                </svg>
                Naeem Pakki
            </a>

            <a href="{{ route('stitching-returns.index') }}"
               class="nav-item {{ request()->routeIs('stitching-returns.*') || request()->routeIs('stitching-assignments.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Stitching
            </a>

            <a href="{{ route('tarpai-sends.index') }}"
               class="nav-item {{ request()->routeIs('tarpai-sends.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                Tarpai
            </a>

            <a href="{{ route('press-pack.index') }}"
               class="nav-item {{ request()->routeIs('press-pack.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                Press & Pack
            </a>

            <a href="{{ route('dispatch.index') }}"
               class="nav-item {{ request()->routeIs('dispatch.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                </svg>
                Dispatch
            </a>

            <a href="{{ route('wages.index') }}"
               class="nav-item {{ request()->routeIs('wages.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Wages
            </a>

            <a href="{{ route('packed-inventory.index') }}"
               class="nav-item {{ request()->routeIs('packed-inventory.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                Inventory
            </a>

            <a href="{{ route('production.tracker') }}"
               class="nav-item {{ request()->routeIs('production.tracker') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                </svg>
                Production Tracker
            </a>
            @endif

            {{-- Reports --}}
            @if(in_array($r, ['admin','accountant']))
            <p class="px-3 pt-3 pb-0.5 text-[10px] font-semibold text-[#86868B] tracking-widest uppercase">Analytics</p>

            <a href="{{ route('reports.index') }}"
               class="nav-item {{ request()->routeIs('reports.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Reports
            </a>
            @endif

            {{-- System --}}
            @if($r === 'admin')
            <p class="px-3 pt-3 pb-0.5 text-[10px] font-semibold text-[#86868B] tracking-widest uppercase">System</p>

            <a href="{{ route('stitching-units.index') }}"
               class="nav-item {{ request()->routeIs('stitching-units.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Stitching Units
            </a>

            <a href="{{ route('bank-accounts.index') }}"
               class="nav-item {{ request()->routeIs('bank-accounts.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                Bank Accounts
            </a>

            <a href="{{ route('users.index') }}"
               class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                User Accounts
            </a>

            <a href="{{ route('backups.index') }}"
               class="nav-item {{ request()->routeIs('backups.*') ? 'active' : '' }} flex items-center gap-3 px-3 py-1.5 rounded-lg text-sm font-medium text-[#1D1D1F]">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                </svg>
                Backups
            </a>
            @endif

        </nav>

        {{-- User footer — flex-shrink-0 keeps it pinned at the bottom --}}
        <div class="border-t border-[#F2F2F7] p-4 flex-shrink-0 mt-auto">
            <div class="flex items-center gap-3">
                {{-- Clicking avatar/name goes to profile --}}
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 flex-1 min-w-0 group rounded-lg hover:bg-[#F5F5F7] transition-colors -m-1 p-1">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold flex-shrink-0 transition-opacity
                        {{ request()->routeIs('profile.*') ? 'bg-[#005BB5]' : 'bg-[#0071E3]' }}">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[#1D1D1F] text-xs font-medium truncate group-hover:text-[#0071E3] transition-colors">
                            {{ Auth::user()->name }}
                        </p>
                        <p class="text-[#86868B] text-[10px] uppercase tracking-wide">{{ Auth::user()->role }}</p>
                    </div>
                </a>

                {{-- Sign out --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sign Out"
                        class="text-[#86868B] hover:text-[#FF3B30] transition-colors p-1 rounded flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ===================== MAIN AREA ===================== --}}
    <div class="flex-1 flex flex-col min-w-0">

        {{-- Top Bar --}}
        <header class="sticky top-0 z-10 bg-white border-b border-[#E8E8ED] px-4 sm:px-6 h-14 flex items-center gap-4">
            {{-- Mobile hamburger --}}
            <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-[#6E6E73] hover:text-[#1D1D1F] transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Page title --}}
            <h1 class="font-display font-semibold text-[#1D1D1F] text-base">@yield('title', 'Dashboard')</h1>

            <div class="flex-1"></div>

            {{-- Flagged orders badge --}}
            @php $flaggedCount = \App\Models\Order::where('is_flagged', true)->count(); @endphp
            @if($flaggedCount > 0)
            <a href="{{ route('orders.flagged') }}"
               class="flex items-center gap-1.5 bg-[#FFF0EF] text-[#FF3B30] text-xs font-medium px-3 py-1.5 rounded-full border border-[#FFCDD0]">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                {{ $flaggedCount }} Flagged
            </a>
            @endif

            {{-- Date --}}
            <span class="hidden sm:block text-[#86868B] text-xs">{{ now()->format('d M Y') }}</span>
        </header>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="mx-4 sm:mx-6 mt-4 flex items-center gap-3 px-4 py-3 bg-[#F0FDF4] border border-[#BBF7D0] text-[#15803D] text-sm rounded-xl" x-data x-init="setTimeout(() => $el.remove(), 4000)">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mx-4 sm:mx-6 mt-4 flex items-center gap-3 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl" x-data x-init="setTimeout(() => $el.remove(), 5000)">
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
        @endif

        {{-- Page Content --}}
        <main class="flex-1 p-4 sm:p-6 overflow-x-hidden">
            @yield('content')
        </main>

    </div>
</div>

</body>
</html>
