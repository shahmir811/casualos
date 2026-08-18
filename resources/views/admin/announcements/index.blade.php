@extends('layouts.app')
@section('title', 'Announcements')
@section('content')

<div class="mb-7">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Announcements</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Post an update to the customer app's Timeline. Every customer gets a push notification.</p>
</div>

@if(session('success'))
<div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background:#F0FFF4; color:#15803D; border:1px solid #BBF7D0;">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-sm" style="background:#FFF0EF; color:#FF3B30; border:1px solid #FFCDD0;">
    <ul class="list-disc list-inside space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
</div>
@endif

{{-- Compose --}}
<div class="max-w-2xl mb-6">
    <form id="announcement-form" method="POST" action="{{ route('announcements.store') }}"
          enctype="multipart/form-data" class="card p-7 space-y-5">
        @csrf

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Title <span class="text-[#FF3B30]">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                class="apple-input"
                placeholder="e.g. New Catalogue: ISHQIA">
            @error('title')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Body <span class="text-[#FF3B30]">*</span></label>
            <textarea name="body" required rows="4"
                class="apple-input resize-none"
                placeholder="What's the update?">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{-- Image --}}
        <div x-data="{ preview: null }">
            <label class="block text-[#1D1D1F] text-sm font-medium mb-1.5">Image</label>

            <div x-show="preview" class="mb-3">
                <img :src="preview" alt="Preview"
                    class="w-32 h-32 object-cover rounded-xl border border-[#E8E8ED] shadow-sm">
                <p class="text-[#86868B] text-xs mt-1">Preview — this is how it will appear.</p>
            </div>

            <input type="file" name="image" accept="image/*"
                class="apple-input file:bg-[#0071E3] file:border-0 file:text-white file:text-xs file:px-3 file:py-1 file:rounded-full file:mr-3 file:cursor-pointer cursor-pointer"
                @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
            <p class="mt-1 text-[#86868B] text-xs">Optional. Max 10MB.</p>
            @error('image')
                <p class="mt-1.5 text-[#FF3B30] text-xs">{{ $message }}</p>
            @enderror
        </div>

        {{--
            The Send button is type="button" and submits through the global
            confirm modal rather than a plain type="submit" — this form (with
            its title/body/image fields) is what actually gets submitted, so
            formId points at it directly. Every other use of $store.confirm in
            this codebase submits a separate hidden no-field form instead,
            since proceed() just calls formId.submit() with no knowledge of
            field contents — but this send genuinely needs the real form's
            data, so pointing at it directly is correct here.
        --}}
        <button type="button" class="btn-primary"
                @click="$store.confirm.show({
                    title: 'Send Announcement',
                    message: 'Send this to every customer? This cannot be undone.',
                    formId: 'announcement-form',
                    confirmText: 'Send to All Customers'
                })">
            Send to All Customers
        </button>
    </form>
</div>

{{-- History --}}
<div class="card overflow-hidden">
    <table class="w-full apple-table">
        <thead>
            <tr>
                <th class="text-left">Title</th>
                <th class="text-left">Sent By</th>
                <th class="text-left">Sent At</th>
                <th class="text-left">Recipients</th>
            </tr>
        </thead>
        <tbody>
            @forelse($announcements as $announcement)
            <tr>
                <td class="font-medium text-[#1D1D1F]">
                    <div class="flex items-center gap-3">
                        @if($announcement->image_path)
                        <img src="{{ Storage::url($announcement->image_path) }}" alt=""
                             class="w-10 h-10 object-cover rounded-lg border border-[#E8E8ED] flex-shrink-0">
                        @endif
                        <div>
                            {{ $announcement->title }}
                            <p class="text-[#86868B] text-xs font-normal mt-0.5">{{ \Illuminate\Support\Str::limit($announcement->body, 80) }}</p>
                        </div>
                    </div>
                </td>
                <td>{{ $announcement->sentBy?->name ?? '—' }}</td>
                <td>{{ $announcement->sent_at->format('M j, Y g:i A') }}</td>
                <td>{{ number_format($announcement->recipient_count) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="text-center text-[#86868B] py-12">No announcements sent yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-5">{{ $announcements->links() }}</div>

@endsection
