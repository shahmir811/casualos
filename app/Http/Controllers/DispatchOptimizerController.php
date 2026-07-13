<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Services\DispatchOptimizerService;

class DispatchOptimizerController extends Controller
{
    public function index(DispatchOptimizerService $optimizer)
    {
        $selectedId = session('active_catalogue_id');
        $catalogue  = $selectedId ? Catalogue::find($selectedId) : Catalogue::orderByDesc('created_at')->first();

        if (! $catalogue) {
            return view('production.dispatch-optimizer.index', [
                'catalogue'   => null,
                'designs'     => collect(),
                'sizes'       => ['xs', 's', 'm', 'l', 'xl'],
                'result'      => null,
            ]);
        }

        $result = $optimizer->optimize($catalogue->id);

        $designs = $catalogue->designs()->orderBy('sort_order')->get()->keyBy('id');

        return view('production.dispatch-optimizer.index', [
            'catalogue' => $catalogue,
            'designs'   => $designs,
            'sizes'     => ['xs', 's', 'm', 'l', 'xl'],
            'result'    => $result,
        ]);
    }
}
