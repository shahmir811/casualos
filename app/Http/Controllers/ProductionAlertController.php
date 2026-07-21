<?php

namespace App\Http\Controllers;

use App\Models\ProductionAlert;
use Illuminate\Support\Facades\Auth;

class ProductionAlertController extends Controller
{
    public function resolve(ProductionAlert $alert)
    {
        $this->denyCreativeHead();

        $alert->update([
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Alert marked as resolved.');
    }
}
