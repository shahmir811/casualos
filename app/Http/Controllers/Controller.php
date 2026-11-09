<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function denyCreativeHead(): void
    {
        if (Auth::user()->role === 'creative_head') {
            abort(403, 'Read-only access.');
        }
    }

    /**
     * Blocks in-house production write actions (fabric/assignments/tarpai/press)
     * against a vendor-produced catalogue. The vendor handles that catalogue's
     * entire production off-system, so there is never anything legitimate to
     * log here — this guard covers both a typed URL and a direct POST, since
     * the UI already hides the corresponding button/link for the same reason.
     */
    protected function rejectIfVendorCatalogue(int $catalogueId, string $redirectRoute): ?RedirectResponse
    {
        $catalogue = Catalogue::find($catalogueId);

        if ($catalogue && $catalogue->isVendorProduced()) {
            return redirect()->route($redirectRoute)
                ->with('error', "This catalogue is produced by {$catalogue->vendor->name} — in-house production isn't tracked here.");
        }

        return null;
    }
}
