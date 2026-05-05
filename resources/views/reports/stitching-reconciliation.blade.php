@extends('layouts.app')
@section('title', 'Stitching Reconciliation')
@section('content')

<div class="flex items-center gap-3 mb-7">
    <a href="{{ route('reports.index') }}" class="text-[#0066CC] hover:underline text-sm">Reports</a>
    <span class="text-[#86868B]">/</span>
    <span class="text-[#1D1D1F] text-sm font-medium">Stitching Reconciliation</span>
</div>

<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Stitching Reconciliation</h1>
    <p class="text-[#6E6E73] text-sm mt-1">Naeem Pakki and Tarpai — pieces sent vs returned vs outstanding</p>
</div>

{{-- Naeem Pakki Section --}}
@php
    use App\Models\ProductionAssignmentNpDesign;
    $npDesignRows = ProductionAssignmentNpDesign::with(['assignment.catalogue', 'design', 'returnItems'])
        ->latest('id')->get();
    $npTotalSent = 0; $npTotalReturned = 0;
@endphp

<div class="mb-8">
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-purple-500"></span> Naeem Pakki
    </h2>
    <div class="card overflow-hidden">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Assignment</th>
                    <th class="text-left">Design</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-right">Sent</th>
                    <th class="text-right">Returned</th>
                    <th class="text-right">Outstanding</th>
                    <th class="text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($npDesignRows as $npDesign)
                @php
                    $s = (int) $npDesign->quantity;
                    $r = $npDesign->totalReturned();
                    $o = $npDesign->outstandingPieces();
                    $npTotalSent += $s;
                    $npTotalReturned += $r;
                @endphp
                <tr>
                    <td class="font-medium text-[#0066CC]">
                        PA-{{ str_pad($npDesign->assignment->id, 4, '0', STR_PAD_LEFT) }}
                    </td>
                    <td>{{ $npDesign->design->name ?? '—' }}</td>
                    <td class="text-[#6E6E73] text-xs">{{ $npDesign->assignment->catalogue->name ?? '—' }}</td>
                    <td class="text-right">{{ $s }}</td>
                    <td class="text-right text-green-700">{{ $r }}</td>
                    <td class="text-right {{ $o > 0 ? 'text-orange-600 font-semibold' : 'text-[#86868B]' }}">{{ $o }}</td>
                    <td>
                        <span class="badge {{ $o === 0 && $s > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                            {{ $o === 0 && $s > 0 ? 'Complete' : 'Pending' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-[#86868B] py-8">No Naeem Pakki records.</td></tr>
                @endforelse
                @if($npDesignRows->count())
                <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-3 font-semibold text-sm" colspan="3">Totals</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $npTotalSent }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm text-green-600">{{ $npTotalReturned }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm {{ ($npTotalSent - $npTotalReturned) > 0 ? 'text-orange-600' : 'text-[#86868B]' }}">{{ $npTotalSent - $npTotalReturned }}</td>
                    <td></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

{{-- Tarpai Section --}}
@php
    use App\Models\TarpaiSend;
    $tarpaiSends = TarpaiSend::with(['catalogue', 'design', 'items', 'returns.items'])->latest()->get();
    $tpTotalSent = 0; $tpTotalReturned = 0;
@endphp

<div>
    <h2 class="text-sm font-semibold text-[#1D1D1F] mb-3 flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-blue-500"></span> Tarpai Finishing
    </h2>
    <div class="card overflow-hidden">
        <table class="w-full apple-table">
            <thead>
                <tr>
                    <th class="text-left">Send #</th>
                    <th class="text-left">Design</th>
                    <th class="text-left">Catalogue</th>
                    <th class="text-right">Sent</th>
                    <th class="text-right">Returned</th>
                    <th class="text-right">Outstanding</th>
                    <th class="text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tarpaiSends as $send)
                @php
                    $s = $send->totalPiecesSent();
                    $r = $send->totalPiecesReturned();
                    $o = $send->outstandingPieces();
                    $tpTotalSent += $s;
                    $tpTotalReturned += $r;
                @endphp
                <tr>
                    <td class="font-medium">TP-{{ str_pad($send->id, 4, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $send->design?->name ?? '—' }}</td>
                    <td class="text-[#6E6E73] text-xs">{{ $send->catalogue?->name ?? '—' }}</td>
                    <td class="text-right">{{ $s }}</td>
                    <td class="text-right text-green-700">{{ $r }}</td>
                    <td class="text-right {{ $o > 0 ? 'text-orange-600 font-semibold' : 'text-[#86868B]' }}">{{ $o }}</td>
                    <td>
                        <span class="badge {{ $o === 0 && $s > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                            {{ $o === 0 && $s > 0 ? 'Complete' : 'Pending' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-[#86868B] py-8">No Tarpai records.</td></tr>
                @endforelse
                @if($tarpaiSends->count())
                <tr class="border-t-2 border-[#E8E8ED] bg-[#F5F5F7]">
                    <td class="px-5 py-3 font-semibold text-sm" colspan="3">Totals</td>
                    <td class="px-5 py-3 text-right font-bold text-sm">{{ $tpTotalSent }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm text-green-600">{{ $tpTotalReturned }}</td>
                    <td class="px-5 py-3 text-right font-bold text-sm {{ ($tpTotalSent - $tpTotalReturned) > 0 ? 'text-orange-600' : 'text-[#86868B]' }}">{{ $tpTotalSent - $tpTotalReturned }}</td>
                    <td></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

@endsection
