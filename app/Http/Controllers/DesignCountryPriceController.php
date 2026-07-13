<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\DesignCountryPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignCountryPriceController extends Controller
{
    public function index()
    {
        $catalogueId = (int) session('active_catalogue_id', 0) ?: null;
        $catalogue   = $catalogueId
            ? Catalogue::find($catalogueId)
            : Catalogue::orderByDesc('created_at')->first();

        $catalogue?->load('designs.countryPrices');
        $countries = Customer::COUNTRIES;

        return view('admin.country-pricing.index', compact('catalogue', 'countries'));
    }

    public function store(Request $request, Catalogue $catalogue)
    {
        $validated = $request->validate([
            'prices'                 => 'array',
            'prices.*.*'             => 'nullable|numeric|min:0',
        ]);

        $designIds = $catalogue->designs()->pluck('id');

        DB::transaction(function () use ($validated, $designIds) {
            foreach ($validated['prices'] ?? [] as $designId => $countryPrices) {
                if (!$designIds->contains((int) $designId)) {
                    continue;
                }

                foreach ($countryPrices as $country => $price) {
                    if (!in_array($country, Customer::COUNTRIES, true)) {
                        continue;
                    }

                    if ($price === null || $price === '') {
                        DesignCountryPrice::where('design_id', $designId)
                            ->where('country', $country)
                            ->delete();
                        continue;
                    }

                    DesignCountryPrice::updateOrCreate(
                        ['design_id' => $designId, 'country' => $country],
                        ['price' => $price]
                    );
                }
            }
        });

        return back()->with('success', 'Country prices saved for ' . $catalogue->name . '.');
    }
}
