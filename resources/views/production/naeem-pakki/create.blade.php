@extends('layouts.app')
@section('title', 'Log Naeem Pakki Send')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('naeem-pakki-sends.index') }}" class="text-[#0066CC] hover:underline text-sm">Naeem Pakki</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Log Send</span>
</div>

<div class="max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F] mb-6">Log Naeem Pakki Send</h1>

    @if($errors->any())
    <div class="mb-5 px-4 py-3 bg-[#FFF0EF] border border-[#FFCDD0] text-[#FF3B30] text-sm rounded-xl">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($assignments->isEmpty())
    <div class="card p-8 text-center">
        <p class="text-[#86868B]">No production assignments routed to Naeem Pakki yet.</p>
        <a href="{{ route('production-assignments.create') }}" class="btn-primary mt-4">Create Assignment First</a>
    </div>
    @else

    <form method="POST" action="{{ route('naeem-pakki-sends.store') }}" class="space-y-5">
        @csrf

        <div class="card p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Production Assignment</label>
                <select name="production_assignment_id" class="apple-input" required>
                    <option value="">— Select assignment —</option>
                    @foreach($assignments as $a)
                    <option value="{{ $a->id }}" {{ old('production_assignment_id') == $a->id ? 'selected' : '' }}>
                        PA-{{ str_pad($a->id, 4, '0', STR_PAD_LEFT) }} — {{ $a->design?->catalogue?->name }} / {{ $a->design?->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Send Date</label>
                <input type="date" name="sent_date" value="{{ old('sent_date', date('Y-m-d')) }}" class="apple-input" required>
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2">Per Piece Rate (Rs.)</label>
                <input type="number" name="per_piece_price" value="{{ old('per_piece_price') }}" step="0.01" min="0" class="apple-input" placeholder="e.g. 150" required>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-[#F2F2F7]">
                <h3 class="text-sm font-semibold text-[#1D1D1F]">Pieces Sent by Size</h3>
            </div>
            <div class="p-5 grid grid-cols-5 gap-3">
                @foreach(['xs','s','m','l','xl'] as $size)
                <div>
                    <label class="block text-xs font-semibold text-[#6E6E73] uppercase tracking-widest mb-2 text-center">{{ strtoupper($size) }}</label>
                    <input type="hidden" name="items[{{ $loop->index }}][size]" value="{{ $size }}">
                    <input type="number" name="items[{{ $loop->index }}][quantity]"
                           min="0" value="{{ old("items.{$loop->index}.quantity", 0) }}"
                           class="apple-input text-center">
                </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">Save Send</button>
            <a href="{{ route('naeem-pakki-sends.index') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
    @endif
</div>

@endsection
