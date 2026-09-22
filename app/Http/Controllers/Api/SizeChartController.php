<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SizeChart;
use Illuminate\Support\Facades\Storage;

/**
 * Customer-facing read of the singleton size-chart image managed by the
 * admin-only App\Http\Controllers\SizeChartController. Fresh short-lived
 * presigned URL per request, same pattern as CatalogueController::book() —
 * never cache the URL client-side, fetch on tap.
 */
class SizeChartController extends Controller
{
    public function show()
    {
        $sizeChart = SizeChart::current();

        if (! $sizeChart->image_path) {
            return response()->json([
                'message' => 'No size chart has been uploaded yet.',
                'reason'  => 'not_uploaded',
            ], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl(
            $sizeChart->image_path,
            now()->addMinutes(10)
        );

        return response()->json(['url' => $url]);
    }
}
