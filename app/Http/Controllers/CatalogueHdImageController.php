<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\CatalogueHdImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Admin/creative_head management of the per-catalogue HD image gallery.
 *
 * Uploads go straight from the browser to S3 via a presigned PUT URL (see presign()) —
 * routing multi-GB batches through this app's shared-hosting PHP process is not viable
 * (upload_max_filesize / post_max_size / execution time). Thumbnails are generated
 * client-side (canvas, resized before upload) for the same reason: decoding a 100MB
 * original with GD on this server for every upload risks exhausting shared-hosting
 * memory limits. store() only registers metadata for files already confirmed on S3.
 */
class CatalogueHdImageController extends Controller
{
    private const MAX_BYTES = 1073741824; // 1GB — no single file exceeds this (confirmed with client)
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png'];

    /**
     * Sidebar entry point — no catalogue in the URL. Resolves whichever catalogue is
     * currently selected in the sidebar's Active Catalogue dropdown (same
     * session('active_catalogue_id') pattern as Country Pricing) and forwards to the
     * catalogue-scoped index below, so uploads always land against that catalogue.
     */
    public function active()
    {
        $catalogueId = (int) session('active_catalogue_id', 0) ?: null;
        $catalogue   = $catalogueId
            ? Catalogue::find($catalogueId)
            : Catalogue::orderByDesc('created_at')->first();

        if (! $catalogue) {
            return redirect()->route('catalogues.index')->with('error', 'No catalogue found. Create one first.');
        }

        return redirect()->route('catalogues.hd-images.index', $catalogue);
    }

    public function index(Catalogue $catalogue)
    {
        $catalogue->load('hdImages.uploadedBy');

        $shareUrl = route('gallery.show', $catalogue->hd_gallery_token);

        return view('catalogues.hd-images.index', compact('catalogue', 'shareUrl'));
    }

    /**
     * Issue a presigned S3 PUT URL for either the original file or its client-generated
     * thumbnail. Both share the same client-picked UUID so store() can pair them back up.
     */
    public function presign(Request $request, Catalogue $catalogue)
    {
        $validated = $request->validate([
            'uuid'         => 'required|uuid',
            'kind'         => 'required|in:original,thumbnail',
            'content_type' => 'required|in:' . implode(',', self::ALLOWED_MIMES),
        ]);

        if ($validated['kind'] === 'thumbnail') {
            $key = "catalogue-hd-images/{$catalogue->id}/thumbnails/{$validated['uuid']}.jpg";
            $contentType = 'image/jpeg'; // thumbnails are always re-encoded to JPEG client-side
        } else {
            $ext = $validated['content_type'] === 'image/png' ? 'png' : 'jpg';
            $key = "catalogue-hd-images/{$catalogue->id}/{$validated['uuid']}.{$ext}";
            $contentType = $validated['content_type'];
        }

        $upload = Storage::disk('s3')->temporaryUploadUrl(
            $key,
            now()->addMinutes(30),
            ['ContentType' => $contentType]
        );

        return response()->json([
            'url'     => $upload['url'],
            'headers' => $upload['headers'],
            'key'     => $key,
        ]);
    }

    /**
     * Register a file already uploaded directly to S3. Size/existence are read back from
     * S3 itself rather than trusted from the client.
     */
    public function store(Request $request, Catalogue $catalogue)
    {
        $validated = $request->validate([
            'uuid'              => 'required|uuid',
            'original_filename' => 'required|string|max:255',
            'content_type'      => 'required|in:' . implode(',', self::ALLOWED_MIMES),
        ]);

        $ext = $validated['content_type'] === 'image/png' ? 'png' : 'jpg';
        $originalKey  = "catalogue-hd-images/{$catalogue->id}/{$validated['uuid']}.{$ext}";
        $thumbnailKey = "catalogue-hd-images/{$catalogue->id}/thumbnails/{$validated['uuid']}.jpg";

        if (! Storage::disk('s3')->exists($originalKey)) {
            return response()->json(['message' => 'Upload did not reach storage — please retry.'], 422);
        }

        $fileSize = Storage::disk('s3')->size($originalKey);

        if ($fileSize > self::MAX_BYTES) {
            Storage::disk('s3')->delete($originalKey);
            return response()->json(['message' => 'File exceeds the 1GB limit.'], 422);
        }

        $hasThumbnail = Storage::disk('s3')->exists($thumbnailKey);

        $hdImage = CatalogueHdImage::create([
            'catalogue_id'       => $catalogue->id,
            's3_path'            => $originalKey,
            'thumbnail_path'     => $hasThumbnail ? $thumbnailKey : null,
            'original_filename'  => $validated['original_filename'],
            'file_size'          => $fileSize,
            'mime_type'          => $validated['content_type'],
            'uploaded_by'        => Auth::id(),
        ]);

        return response()->json([
            'id'            => $hdImage->id,
            'thumbnail_url' => Storage::url($hdImage->thumbnail_path ?? $hdImage->s3_path),
        ]);
    }

    public function destroy(Catalogue $catalogue, CatalogueHdImage $hdImage)
    {
        abort_unless($hdImage->catalogue_id === $catalogue->id, 404);

        Storage::disk('s3')->delete($hdImage->s3_path);
        if ($hdImage->thumbnail_path) {
            Storage::disk('s3')->delete($hdImage->thumbnail_path);
        }

        $hdImage->delete();

        return back()->with('success', 'Image deleted.');
    }
}
