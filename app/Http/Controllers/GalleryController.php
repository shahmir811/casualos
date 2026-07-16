<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\CatalogueHdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Public, no-auth HD image gallery — the link shared with customers who want
 * the full-resolution photos for their own use.
 */
class GalleryController extends Controller
{
    public function show(string $token)
    {
        $catalogue = Catalogue::where('hd_gallery_token', $token)->firstOrFail();
        $catalogue->load('hdImages');

        return view('public.gallery', compact('catalogue'));
    }

    /**
     * Streamed download (not a direct S3 link) so the browser can track byte-level
     * download progress on a same-origin request — a cross-origin fetch() against the
     * S3 URL directly would need bucket CORS just to read the response body for progress.
     */
    public function download(string $token, CatalogueHdImage $hdImage)
    {
        $catalogue = Catalogue::where('hd_gallery_token', $token)->firstOrFail();
        abort_unless($hdImage->catalogue_id === $catalogue->id, 404);

        return Storage::disk('s3')->download($hdImage->s3_path, $hdImage->original_filename);
    }
}
