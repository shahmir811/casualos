@extends('layouts.app')
@section('title', 'Announcement Read Stats')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <a href="{{ route('announcements.index') }}" class="text-[#0066CC] text-sm font-medium hover:underline">&larr; Back to Announcements</a>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mt-2">{{ $announcement->title }}</h1>
        <p class="text-[#6E6E73] text-sm mt-1">
            Sent {{ $announcement->sent_at->format('M j, Y g:i A') }} by {{ $announcement->sentBy?->name ?? '—' }}
        </p>
    </div>
</div>

@if(! $tracked)
<div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background:#FFF8E6; color:#946200; border:1px solid #FCE3A3;">
    Not tracked — this announcement was sent before read analytics was added, so read stats aren't available for it.
</div>
@else

<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-7">
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Sent To</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ number_format($totalCount) }}</p>
        <p class="text-[#86868B] text-xs mt-0.5">customers</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Read</p>
        <p class="text-2xl font-light {{ $readCount > 0 ? 'text-[#30D158]' : 'text-[#1D1D1F]' }}">{{ number_format($readCount) }}</p>
        <p class="text-[#86868B] text-xs mt-0.5">customers</p>
    </div>
    <div class="stat-card">
        <p class="text-[#6E6E73] text-xs font-medium uppercase tracking-widest mb-1">Read Rate</p>
        <p class="text-[#1D1D1F] text-2xl font-light">{{ $totalCount > 0 ? number_format(($readCount / $totalCount) * 100, 0) : 0 }}%</p>
    </div>
</div>

<div class="card overflow-hidden" x-data="{
        reads: {{ Js::from($readsData) }},
        search: '',
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.reads;
            return this.reads.filter(r => r.name.toLowerCase().includes(q) || r.city.toLowerCase().includes(q));
        },
    }">
    <div class="p-4 border-b border-[#F2F2F7]">
        <input type="text" x-model="search" placeholder="Search by customer name or city…"
               class="apple-input w-full md:max-w-sm">
    </div>

    {{-- Desktop table --}}
    <div class="overflow-x-auto hidden md:block">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Customer</th>
                    <th class="text-left">City</th>
                    <th class="text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="read in filtered" :key="read.name + read.city + read.readAt">
                    <tr>
                        <td class="font-medium text-[#1D1D1F]" x-text="read.name"></td>
                        <td class="text-[#6E6E73]" x-text="read.city"></td>
                        <td>
                            <template x-if="read.read">
                                <span class="badge" style="background:#F0FFF4; color:#15803D; border-color:#BBF7D0;">
                                    Read <span x-text="read.readAt"></span>
                                </span>
                            </template>
                            <template x-if="!read.read">
                                <span class="badge" style="background:#F5F5F7; color:#6E6E73; border-color:#E8E8ED;">
                                    Not read yet
                                </span>
                            </template>
                        </td>
                    </tr>
                </template>
                <tr x-show="reads.length === 0" x-cloak>
                    <td colspan="3" class="text-center text-[#86868B] py-12">No customers were sent this announcement.</td>
                </tr>
                <tr x-show="reads.length > 0 && filtered.length === 0" x-cloak>
                    <td colspan="3" class="text-center text-[#86868B] py-12">No customers match "<span x-text="search"></span>".</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Mobile cards --}}
    <div class="md:hidden divide-y divide-[#F2F2F7]">
        <template x-for="read in filtered" :key="read.name + read.city + read.readAt">
            <div class="p-4">
                <p class="font-medium text-[#1D1D1F] text-sm" x-text="read.name"></p>
                <p class="text-[#6E6E73] text-sm mt-0.5" x-text="read.city"></p>
                <div class="mt-2">
                    <template x-if="read.read">
                        <span class="badge" style="background:#F0FFF4; color:#15803D; border-color:#BBF7D0;">
                            Read <span x-text="read.readAt"></span>
                        </span>
                    </template>
                    <template x-if="!read.read">
                        <span class="badge" style="background:#F5F5F7; color:#6E6E73; border-color:#E8E8ED;">
                            Not read yet
                        </span>
                    </template>
                </div>
            </div>
        </template>
        <div class="p-10 text-center text-[#86868B] text-sm" x-show="reads.length === 0" x-cloak>No customers were sent this announcement.</div>
        <div class="p-10 text-center text-[#86868B] text-sm" x-show="reads.length > 0 && filtered.length === 0" x-cloak>No customers match "<span x-text="search"></span>".</div>
    </div>
</div>
@endif

@endsection
