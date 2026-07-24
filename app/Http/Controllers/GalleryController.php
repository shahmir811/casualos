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
     * Redirects straight to a short-lived presigned S3 URL instead of proxying
     * the file through PHP — the browser downloads directly from S3, same as
     * any other direct-link download, with no server-side timeout or
     * metadata-lookup delay in the way.
     */
    public function download(string $token, CatalogueHdImage $hdImage)
    {
        $catalogue = Catalogue::where('hd_gallery_token', $token)->firstOrFail();
        abort_unless($hdImage->catalogue_id === $catalogue->id, 404);

        $filename = str_replace('"', '', $hdImage->original_filename);

        $url = Storage::disk('s3')->temporaryUrl(
            $hdImage->s3_path,
            now()->addMinutes(5),
            ['ResponseContentDisposition' => 'attachment; filename="'.$filename.'"']
        );

        return redirect()->away($url);
    }
}
