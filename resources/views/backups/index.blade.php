@extends('layouts.app')

@section('title', 'Database Backups')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-[#1D1D1F] font-semibold text-lg">Database Backups</h2>
            <p class="text-[#6E6E73] text-sm mt-0.5">Full MySQL dumps stored on this server.</p>
        </div>

        {{-- Trigger new backup --}}
        <form method="POST" action="{{ route('backups.store') }}"
              onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Creating…'">
            @csrf
            <button type="submit" class="btn-primary" style="width:auto; padding:0.6rem 1.25rem;">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/>
                </svg>
                Create Backup
            </button>
        </form>
    </div>

    {{-- Backup list --}}
    <div class="card p-0 overflow-hidden">

        @if($files->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-12 h-12 rounded-2xl bg-[#F5F5F7] flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8"/>
                    </svg>
                </div>
                <p class="text-[#1D1D1F] font-medium text-sm">No backups yet</p>
                <p class="text-[#86868B] text-xs mt-1">Click "Create Backup" to take your first snapshot.</p>
            </div>

        @else
            <table class="apple-table w-full">
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Created</th>
                        <th>Size</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($files as $file)
                    <tr>
                        {{-- Filename --}}
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-[#F0F7FF] flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 text-[#0071E3]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V9l-5-5H7c-2 0-3 1-3 3z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v5h5"/>
                                    </svg>
                                </div>
                                <span class="text-[#1D1D1F] text-sm font-medium font-mono">{{ $file->name }}</span>
                            </div>
                        </td>

                        {{-- Date --}}
                        <td>
                            <span class="text-[#1D1D1F] text-sm">{{ $file->created_at->format('d M Y') }}</span>
                            <span class="block text-[#86868B] text-xs">{{ $file->created_at->format('h:i A') }}</span>
                        </td>

                        {{-- Size --}}
                        <td>
                            <span class="badge" style="background:#F0F7FF; color:#0071E3;">{{ $file->size_human }}</span>
                        </td>

                        {{-- Actions --}}
                        <td class="text-right">
                            <div class="flex items-center justify-end gap-2">
                                {{-- Download --}}
                                <a href="{{ route('backups.download', $file->name) }}"
                                   class="inline-flex items-center gap-1.5 text-[#0071E3] text-xs font-medium px-3 py-1.5 rounded-lg border border-[#C7E0FF] bg-[#F0F7FF] hover:bg-[#dbeeff] transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/>
                                    </svg>
                                    Download
                                </a>

                                {{-- Delete --}}
                                <form method="POST" action="{{ route('backups.destroy', $file->name) }}"
                                      onsubmit="return confirm('Delete this backup? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex items-center gap-1.5 text-[#FF3B30] text-xs font-medium px-3 py-1.5 rounded-lg border border-[#FFCDD0] bg-[#FFF0EF] hover:bg-[#ffe0de] transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

    </div>

    {{-- Info note --}}
    <div class="flex items-start gap-3 px-4 py-3 bg-[#F5F5F7] border border-[#E8E8ED] rounded-xl text-xs text-[#6E6E73]">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
        </svg>
        Backups are stored at <code class="mx-1 px-1 bg-white rounded border border-[#E8E8ED] text-[#1D1D1F]">storage/app/backups/</code> on the server. Download and store copies off-site for safekeeping.
    </div>

</div>
@endsection
