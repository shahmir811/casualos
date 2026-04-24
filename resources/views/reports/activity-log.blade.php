@extends('layouts.app')
@section('title', 'Activity Log')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Activity Log</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Activity Log</h1>
    <p class="text-[#6E6E73] text-sm mt-1">System-wide audit trail of all user actions</p>
</div>

<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Time</th>
                <th class="text-left">User</th>
                <th class="text-left">Action</th>
                <th class="text-left">Model</th>
                <th class="text-left">Description</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            @php
                $actionColor = [
                    'created' => 'bg-green-100 text-green-700',
                    'updated' => 'bg-blue-100 text-blue-700',
                    'deleted' => 'bg-red-100 text-red-700',
                ];
                $modelShort = $log->subject_type ? class_basename($log->subject_type) : '—';
            @endphp
            <tr>
                <td class="text-[#86868B] text-xs whitespace-nowrap">
                    {{ $log->created_at->format('d M Y') }}<br>
                    <span class="text-[10px]">{{ $log->created_at->format('h:i A') }}</span>
                </td>
                <td>
                    <span class="text-sm font-medium text-[#1D1D1F]">{{ $log->causer?->name ?? 'System' }}</span>
                    @if($log->causer)
                    <p class="text-[10px] text-[#86868B] uppercase tracking-wide">{{ $log->causer?->role ?? '' }}</p>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $actionColor[$log->event] ?? 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                        {{ ucfirst($log->event ?? '—') }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-xs font-mono">{{ $modelShort }} #{{ $log->subject_id }}</td>
                <td class="text-[#6E6E73] text-xs max-w-sm truncate">{{ $log->description }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-[#86868B] py-12">No activity recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $logs->links() }}</div>

@endsection
