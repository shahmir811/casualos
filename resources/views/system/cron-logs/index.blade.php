@extends('layouts.app')
@section('title', 'Cron Logs')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Cron Logs</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Execution history for all scheduled and manually triggered jobs</p>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('cron-logs.index') }}" class="card px-5 py-4 mb-5">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1.5">Job</label>
            <select name="job_name" class="apple-input text-sm">
                <option value="">All Jobs</option>
                <option value="wages:calculate-weekly"  {{ request('job_name') === 'wages:calculate-weekly'  ? 'selected' : '' }}>Worker Wages</option>
                <option value="tarpai:calculate-weekly" {{ request('job_name') === 'tarpai:calculate-weekly' ? 'selected' : '' }}>Tarpai Charges</option>
                <option value="audit-log:prune"         {{ request('job_name') === 'audit-log:prune'         ? 'selected' : '' }}>Audit Log Pruning</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1.5">Triggered By</label>
            <select name="triggered_by" class="apple-input text-sm">
                <option value="">All</option>
                <option value="Scheduler" {{ request('triggered_by') === 'Scheduler' ? 'selected' : '' }}>Scheduler</option>
                <option value="manual"    {{ request('triggered_by') === 'manual'    ? 'selected' : '' }}>Manual</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-1.5">Status</label>
            <select name="status" class="apple-input text-sm">
                <option value="">All</option>
                <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
                <option value="failed"  {{ request('status') === 'failed'  ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div class="flex gap-2 pb-0.5">
            <button type="submit" class="btn-primary">Filter</button>
            @if(request()->hasAny(['job_name','triggered_by','status']))
            <a href="{{ route('cron-logs.index') }}" class="btn-secondary">Clear</a>
            @endif
        </div>
    </div>
</form>

{{-- Log Table --}}
<div class="card overflow-hidden">
    <div class="px-5 py-3 border-b border-[#E5E5EA] flex items-center justify-between">
        <p class="text-sm font-medium text-[#1D1D1F]">Run History</p>
        <p class="text-xs text-[#86868B]">{{ $logs->total() }} {{ Str::plural('entry', $logs->total()) }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Job</th>
                    <th class="text-left">Week</th>
                    <th class="text-left">Triggered By</th>
                    <th class="text-center">Created</th>
                    <th class="text-center">Updated</th>
                    <th class="text-center">Skipped</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Ran At</th>
                    <th class="w-8"></th>
                </tr>
            </thead>

            @forelse($logs as $log)
            {{-- Each row pair lives in its own tbody so x-data scopes correctly --}}
            <tbody x-data="{ open: false }">
                <tr class="cursor-pointer select-none"
                    :class="open ? 'bg-[#F5F5F7]' : 'hover:bg-[#FAFAFA]'"
                    @click="open = !open">
                    <td>
                        <div class="flex items-center gap-2">
                            @if($log->job_name === 'wages:calculate-weekly')
                                <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                            @elseif($log->job_name === 'tarpai:calculate-weekly')
                                <span class="w-2 h-2 rounded-full bg-purple-500 flex-shrink-0"></span>
                            @elseif($log->job_name === 'audit-log:prune')
                                <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                            @else
                                <span class="w-2 h-2 rounded-full bg-gray-400 flex-shrink-0"></span>
                            @endif
                            <span class="font-medium text-sm">{{ $log->job_label }}</span>
                        </div>
                    </td>
                    <td class="text-[#6E6E73] text-sm">
                        @if($log->week_start)
                            {{ $log->week_start->format('d M') }} – {{ $log->week_end?->format('d M Y') }}
                        @else
                            <span class="text-[#D2D2D7]">—</span>
                        @endif
                    </td>
                    <td>
                        @if(str_starts_with($log->triggered_by, 'Manual'))
                        <div class="flex items-center gap-1.5">
                            <span class="badge bg-blue-100 text-blue-700">Manual</span>
                            <span class="text-[#6E6E73] text-xs">{{ str_replace('Manual — ', '', $log->triggered_by) }}</span>
                        </div>
                        @else
                        <span class="badge bg-[#F0F0F5] text-[#6E6E73]">Scheduler</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($log->records_created > 0)
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-green-100 text-green-700 text-xs font-semibold">+{{ $log->records_created }}</span>
                        @else
                            <span class="text-[#D2D2D7] text-sm">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($log->records_updated > 0)
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold">{{ $log->records_updated }}</span>
                        @else
                            <span class="text-[#D2D2D7] text-sm">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($log->records_skipped > 0)
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-orange-100 text-orange-600 text-xs font-semibold">{{ $log->records_skipped }}</span>
                        @else
                            <span class="text-[#D2D2D7] text-sm">—</span>
                        @endif
                    </td>
                    <td>
                        @if($log->status === 'success')
                            <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Success
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-red-600 text-xs font-medium">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Failed
                            </span>
                        @endif
                    </td>
                    <td class="text-[#6E6E73] text-xs whitespace-nowrap">{{ $log->ran_at->format('d M Y, g:i A') }}</td>
                    <td class="pr-4 text-center">
                        <svg class="w-4 h-4 text-[#C7C7CC] transition-transform duration-200 inline-block"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </td>
                </tr>

                {{-- Output row — hidden by default, toggled by clicking the main row --}}
                <tr x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    class="{{ $log->status === 'failed' ? 'bg-red-50' : 'bg-[#F9F9FB]' }}">
                    <td colspan="9" class="px-5 py-3 border-b border-[#E5E5EA]">
                        <div class="flex items-start gap-2.5">
                            @if($log->status === 'success')
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            @else
                            <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            @endif
                            <div>
                                <p class="text-[10px] font-semibold text-[#6E6E73] uppercase tracking-widest mb-0.5">Output</p>
                                <p class="text-sm font-mono {{ $log->status === 'failed' ? 'text-red-700' : 'text-[#1D1D1F]' }}">
                                    {{ $log->output ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
            @empty
            <tbody>
                <tr>
                    <td colspan="9" class="text-center py-16">
                        <div class="flex flex-col items-center gap-2">
                            <svg class="w-8 h-8 text-[#D2D2D7]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <p class="text-[#86868B] text-sm">No log entries yet.</p>
                            <p class="text-[#86868B] text-xs">Entries appear here after each scheduled or manual run.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
            @endforelse
        </table>
    </div>
</div>

<div class="mt-5">{{ $logs->links() }}</div>

@endsection
