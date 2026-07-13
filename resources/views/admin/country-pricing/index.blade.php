@extends('layouts.app')
@section('title', 'Country Pricing')
@section('content')

<div class="flex items-center justify-between mb-7">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-[#1D1D1F]">Country Pricing</h1>
        <p class="text-[#6E6E73] text-sm mt-1">Set the per-country tag price for each design in the active catalogue</p>
    </div>
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

@if(! $catalogue)
<div class="card p-8 text-center text-[#86868B]">No catalogue found. Create one first.</div>
@else

<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-[#F2F2F7] flex items-center justify-between">
        <h2 class="text-sm font-semibold text-[#1D1D1F]">{{ $catalogue->name }}</h2>
        <span class="text-xs text-[#86868B]">{{ $catalogue->designs->count() }} designs</span>
    </div>

    @if($catalogue->designs->isEmpty())
    <div class="p-8 text-center text-[#86868B]">This catalogue has no designs yet.</div>
    @else

    <form method="POST" action="{{ route('country-pricing.store', $catalogue) }}">
        @csrf
        <div class="overflow-x-auto">
            <table class="w-full apple-table">
                <thead>
                    <tr>
                        <th class="text-left">Design</th>
                        @foreach($countries as $country)
                        <th class="text-left">{{ $country }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($catalogue->designs as $design)
                    @php $existing = $design->countryPrices->keyBy('country'); @endphp
                    <tr>
                        <td class="font-medium text-[#1D1D1F] whitespace-nowrap">{{ $design->name }}</td>
                        @foreach($countries as $country)
                        <td>
                            <input type="number" step="0.01" min="0"
                                   name="prices[{{ $design->id }}][{{ $country }}]"
                                   value="{{ old('prices.' . $design->id . '.' . $country, $existing[$country]->price ?? '') }}"
                                   class="apple-input" style="width:110px;"
                                   placeholder="{{ \App\Models\Customer::CURRENCY_SYMBOLS[$country] ?? '' }}">
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-[#F2F2F7]">
            <button type="submit" class="btn-primary">Save Prices</button>
        </div>
    </form>
    @endif
</div>

<p class="mt-4 text-xs text-[#86868B]">
    Leave a cell blank to remove that country's price for a design. Tags cannot be printed for an order until the customer's country has a price set for every design in that order.
</p>

@endif

@endsection
