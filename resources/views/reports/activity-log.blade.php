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
    <p class="text-[#6E6E73] text-sm mt-1">Granular audit trail — every action with full item details</p>
</div>

{{-- Filter bar --}}
<div class="card p-4 mb-6">
    <form method="GET" action="{{ route('reports.activity-log') }}" class="flex flex-wrap gap-3 items-end">
        <div>
            <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Search</p>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Description…"
                   class="apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 w-48 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
        </div>
        <div>
            <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">User</p>
            <select name="causer_id" class="apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
                <option value="">All users</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected(request('causer_id') == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Action</p>
            <select name="event" class="apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
                <option value="">All actions</option>
                <option value="detail"  @selected(request('event') === 'detail')>Detail (quantities)</option>
                <option value="created" @selected(request('event') === 'created')>Created</option>
                <option value="updated" @selected(request('event') === 'updated')>Updated</option>
                <option value="deleted" @selected(request('event') === 'deleted')>Deleted</option>
            </select>
        </div>
        <div>
            <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">Category</p>
            <select name="model" class="apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
                <option value="">All categories</option>
                @foreach([
                    'orders'              => 'Orders',
                    'payments'            => 'Payments',
                    'catalogues'          => 'Catalogues',
                    'fabric_batches'      => 'Fabric Batches',
                    'outsourced_batches'  => 'Outsourced Batches',
                    'press_sends'         => 'Press Sends',
                    'press_returns'       => 'Press Returns',
                    'tarpai_sends'        => 'Tarpai Sends',
                    'tarpai_returns'      => 'Tarpai Returns',
                    'dispatch_batches'    => 'Dispatch Batches',
                    'assignments'         => 'Production Assignments',
                    'stitching_returns'   => 'Stitching Returns',
                    'naeem_pakki_returns' => 'Naeem Pakki Returns',
                    'order_reductions'    => 'Order Reductions',
                    'wages'               => 'Wages',
                ] as $key => $label)
                    <option value="{{ $key }}" @selected(request('model') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">From</p>
            <input type="date" name="start_date" value="{{ request('start_date') }}"
                   class="apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
        </div>
        <div>
            <p class="text-[10px] font-semibold text-[#86868B] uppercase tracking-widest mb-1.5">To</p>
            <input type="date" name="end_date" value="{{ request('end_date') }}"
                   class="apple-input text-sm rounded-lg border border-[#D2D2D7] px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#0071E3]">
        </div>
        <button type="submit" class="btn-primary text-sm px-4 py-2">Filter</button>
        @if(request()->hasAny(['search','event','model','causer_id','start_date','end_date']))
            <a href="{{ route('reports.activity-log') }}" class="text-sm text-[#86868B] hover:text-[#1D1D1F] py-2">× Clear</a>
        @endif
    </form>
</div>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full apple-table min-w-[780px]">
        <thead>
            <tr>
                <th class="text-left w-32">Time</th>
                <th class="text-left w-36">User</th>
                <th class="text-left w-24">Action</th>
                <th class="text-left">Subject</th>
                <th class="text-left">Description</th>
                <th class="text-right w-16"></th>
            </tr>
        </thead>

        @forelse($logs as $log)
        @php
            $actionColor = [
                'created' => 'bg-green-100 text-green-700',
                'updated' => 'bg-blue-100 text-blue-700',
                'deleted' => 'bg-red-100 text-red-700',
                'detail'  => 'bg-violet-100 text-violet-700',
            ];
            $modelShort   = $log->subject_type ? class_basename($log->subject_type) : '—';
            $subjectType  = $log->subject_type ? ltrim($log->subject_type, '\\') : null;
            $props        = $log->properties->toArray();
            $isDetail     = $log->event === 'detail';
            $hasProps     = !empty($props) && ($isDetail || isset($props['attributes']) || isset($props['old']));

            // Build a human-readable subject label
            $subjectLabel = match($subjectType) {
                'App\Models\Order' => $log->subject
                    ? 'Order #' . $log->subject->order_number . ($log->subject->catalogue ? ' · ' . $log->subject->catalogue->name : '')
                    : "Order #{$log->subject_id} (deleted)",
                'App\Models\Payment' => $log->subject
                    ? 'Payment #' . $log->subject_id . ($log->subject->order ? ' · Order #' . $log->subject->order->order_number : '')
                    : "Payment #{$log->subject_id} (deleted)",
                'App\Models\OutsourcedBatch' => 'OB-' . str_pad($log->subject_id, 4, '0', STR_PAD_LEFT)
                    . ($log->subject?->catalogue ? ' · ' . $log->subject->catalogue->name : ''),
                'App\Models\FabricBatch' => 'FB-' . str_pad($log->subject_id, 4, '0', STR_PAD_LEFT)
                    . ($log->subject?->catalogue ? ' · ' . $log->subject->catalogue->name : ''),
                'App\Models\PressSend' => 'PS-' . str_pad($log->subject_id, 4, '0', STR_PAD_LEFT)
                    . ($log->subject?->catalogue ? ' · ' . $log->subject->catalogue->name : ''),
                'App\Models\PressReturn' => 'PR-' . str_pad($log->subject_id, 4, '0', STR_PAD_LEFT)
                    . ($log->subject?->send?->catalogue ? ' · ' . $log->subject->send->catalogue->name : ''),
                'App\Models\TarpaiSend' => 'TS-' . str_pad($log->subject_id, 4, '0', STR_PAD_LEFT)
                    . ($log->subject?->catalogue ? ' · ' . $log->subject->catalogue->name : ''),
                'App\Models\TarpaiReturn' => 'TR-' . str_pad($log->subject_id, 4, '0', STR_PAD_LEFT)
                    . ($log->subject?->send?->catalogue ? ' · ' . $log->subject->send->catalogue->name : ''),
                'App\Models\DispatchBatch' => ($log->subject
                    ? 'Batch #' . $log->subject->batch_number . ' · Order #' . ($log->subject->order?->order_number ?? '?')
                      . ($log->subject->order?->customer ? ' (' . $log->subject->order->customer->name . ')' : '')
                    : "DispatchBatch #{$log->subject_id}"),
                'App\Models\ProductionAssignment' => 'Assignment #' . $log->subject_id
                    . ($log->subject?->catalogue ? ' · ' . $log->subject->catalogue->name : '')
                    . ($log->subject?->design ? ' · ' . $log->subject->design->name : ''),
                'App\Models\StitchingReturn' => 'Return #' . $log->subject_id
                    . ($log->subject?->catalogue ? ' · ' . $log->subject->catalogue->name : '')
                    . ($log->subject?->design ? ' · ' . $log->subject->design->name : ''),
                'App\Models\NaeemPakkiReturn' => 'NP Return #' . $log->subject_id
                    . ($log->subject?->assignment?->catalogue ? ' · ' . $log->subject->assignment->catalogue->name : ''),
                default => $modelShort . ' #' . $log->subject_id,
            };
        @endphp

        <tbody x-data="{ open: false }">
            <tr class="cursor-pointer hover:bg-[#F9F9F9]" @if($hasProps) @click="open = !open" @endif>
                <td class="text-[#86868B] text-xs whitespace-nowrap">
                    {{ $log->created_at->format('d M Y') }}<br>
                    <span class="text-[10px]">{{ $log->created_at->format('h:i A') }}</span>
                </td>
                <td>
                    <span class="text-sm font-medium text-[#1D1D1F]">{{ $log->causer?->name ?? 'System' }}</span>
                    @if($log->causer)
                    <p class="text-[10px] text-[#86868B] uppercase tracking-wide">{{ $log->causer->role ?? '' }}</p>
                    @endif
                </td>
                <td>
                    <span class="badge {{ $actionColor[$log->event] ?? 'bg-[#F5F5F7] text-[#6E6E73]' }}">
                        {{ $log->event === 'detail' ? 'Detail' : ucfirst($log->event ?? '—') }}
                    </span>
                </td>
                <td class="text-[#6E6E73] text-xs">{{ $subjectLabel }}</td>
                <td class="text-[#6E6E73] text-xs max-w-xs truncate">{{ $log->description }}</td>
                <td class="text-right">
                    @if($hasProps)
                    <span class="text-[10px] text-[#0066CC]" x-text="open ? '▲ hide' : '▼ details'"></span>
                    @endif
                </td>
            </tr>

            {{-- Expandable properties row --}}
            @if($hasProps)
            <tr x-show="open" x-cloak>
                <td colspan="6" class="bg-[#F9F9F9] px-6 py-4 border-t border-[#F2F2F7]">
                    @if($isDetail)
                        {{-- Detail event: show structured properties --}}
                        <div class="flex flex-wrap gap-x-8 gap-y-2 mb-3 text-xs">
                            @foreach(collect($props)->except('items') as $key => $val)
                                <div>
                                    <span class="text-[10px] text-[#86868B] uppercase tracking-widest">{{ str_replace('_', ' ', $key) }}</span>
                                    <p class="font-medium text-[#1D1D1F] mt-0.5">{{ $val }}</p>
                                </div>
                            @endforeach
                        </div>
                        @if(!empty($props['items']))
                        <table class="text-xs border-collapse w-auto">
                            <thead>
                                <tr>
                                    @php $firstItem = $props['items'][0] ?? []; @endphp
                                    @foreach(array_keys($firstItem) as $col)
                                        <th class="text-left text-[10px] uppercase tracking-widest text-[#86868B] pr-6 pb-1 font-semibold">{{ $col }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($props['items'] as $row)
                                <tr>
                                    @foreach($row as $cell)
                                        <td class="pr-6 py-0.5 text-[#1D1D1F] font-medium">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @endif
                    @else
                        {{-- Spatie auto-log: show attribute changes --}}
                        @php
                            $attributes = $props['attributes'] ?? [];
                            $old        = $props['old'] ?? [];
                        @endphp
                        <table class="text-xs border-collapse w-auto">
                            <thead>
                                <tr>
                                    <th class="text-left text-[10px] uppercase tracking-widest text-[#86868B] pr-8 pb-1 font-semibold">Field</th>
                                    @if(!empty($old))<th class="text-left text-[10px] uppercase tracking-widest text-[#86868B] pr-8 pb-1 font-semibold">Old value</th>@endif
                                    <th class="text-left text-[10px] uppercase tracking-widest text-[#86868B] pr-8 pb-1 font-semibold">{{ empty($old) ? 'Value' : 'New value' }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attributes as $field => $newVal)
                                <tr>
                                    <td class="pr-8 py-0.5 text-[#86868B]">{{ str_replace('_', ' ', $field) }}</td>
                                    @if(!empty($old))<td class="pr-8 py-0.5 text-red-600 line-through">{{ is_array($old[$field] ?? null) ? json_encode($old[$field]) : ($old[$field] ?? '—') }}</td>@endif
                                    <td class="pr-8 py-0.5 text-[#1D1D1F] font-medium">{{ is_array($newVal) ? json_encode($newVal) : $newVal }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </td>
            </tr>
            @endif
        </tbody>

        @empty
        <tbody>
            <tr><td colspan="6" class="text-center text-[#86868B] py-12">No activity recorded yet.</td></tr>
        </tbody>
        @endforelse
    </table>
    </div>
</div>

<div class="mt-5">{{ $logs->links() }}</div>

@endsection
