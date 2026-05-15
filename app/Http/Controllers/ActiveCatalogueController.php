<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActiveCatalogueController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['catalogue_id' => 'required|exists:catalogues,id']);
        session(['active_catalogue_id' => (int) $request->catalogue_id]);
        return back();
    }
}
