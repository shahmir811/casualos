<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Admin/production_manager/creative_head management of a catalogue's "Catalog Book" —
 * a single lookbook PDF per catalogue, replacing the old one on re-upload.
 *
 * Catalog books have been seen up to ~200MB, so — same reasoning as the HD Gallery
 * (CatalogueHdImageController) — uploads go straight from the browser to S3 via a
 * presigned PUT URL rather than through this app's shared-hosting PHP process.
 * store() only registers metadata for a file already confirmed to exist on S3.
 */
class CatalogueBookController extends Controller
{
    // 300MB — headroom above the ~200MB largest catalog book confirmed with the client.
    private const MAX_BYTES = 314572800;
    private const CONTENT_TYPE = 'application/pdf';

    public function presign(Request $request, Catalogue $catalogue)
    {
        $validated = $request->validate([
            'uuid' => 'required|uuid',
        ]);

        $key = "catalogue-books/{$catalogue->id}/{$validated['uuid']}.pdf";

        $upload = Storage::disk('s3')->temporaryUploadUrl(
            $key,
            now()->addMinutes(60),
            ['ContentType' => self::CONTENT_TYPE]
        );

        return response()->json([
            'url'     => $upload['url'],
            'headers' => $upload['headers'],
            'key'     => $key,
        ]);
    }

    /**
     * Register a file already uploaded directly to S3. Size/existence are read back
     * from S3 itself rather than trusted from the client.
     */
    public function store(Request $request, Catalogue $catalogue)
    {
        $validated = $request->validate([
            'uuid'              => 'required|uuid',
            'original_filename' => 'required|string|max:255',
        ]);

        $key = "catalogue-books/{$catalogue->id}/{$validated['uuid']}.pdf";

        if (! Storage::disk('s3')->exists($key)) {
            return response()->json(['message' => 'Upload did not reach storage — please retry.'], 422);
        }

        $fileSize = Storage::disk('s3')->size($key);

        if ($fileSize > self::MAX_BYTES) {
            Storage::disk('s3')->delete($key);
            return response()->json(['message' => 'File exceeds the 300MB limit.'], 422);
        }

        // Replacing an existing book — remove the old S3 object once the new one is confirmed.
        $oldPath = $catalogue->catalogue_book_path;

        $catalogue->update([
            'catalogue_book_path'              => $key,
            'catalogue_book_original_filename' => $validated['original_filename'],
            'catalogue_book_file_size'         => $fileSize,
            'catalogue_book_uploaded_by'       => Auth::id(),
            'catalogue_book_uploaded_at'       => now(),
        ]);

        if ($oldPath && $oldPath !== $key) {
            Storage::disk('s3')->delete($oldPath);
        }

        return response()->json(['message' => 'Catalog book uploaded.']);
    }

    public function destroy(Catalogue $catalogue)
    {
        abort_unless($catalogue->catalogue_book_path, 404);

        Storage::disk('s3')->delete($catalogue->catalogue_book_path);

        $catalogue->update([
            'catalogue_book_path'              => null,
            'catalogue_book_original_filename' => null,
            'catalogue_book_file_size'         => null,
            'catalogue_book_uploaded_by'       => null,
            'catalogue_book_uploaded_at'       => null,
        ]);

        return back()->with('success', 'Catalog book deleted.');
    }

    /**
     * Staff "View" — redirects to a short-lived presigned S3 URL with inline
     * disposition so the browser renders the PDF instead of downloading it,
     * same mechanic as GalleryController::download() but inline, not attachment.
     */
    public function view(Catalogue $catalogue)
    {
        abort_unless($catalogue->catalogue_book_path, 404);

        $filename = str_replace('"', '', $catalogue->catalogue_book_original_filename ?? 'catalogue-book.pdf');

        $url = Storage::disk('s3')->temporaryUrl(
            $catalogue->catalogue_book_path,
            now()->addMinutes(10),
            ['ResponseContentDisposition' => 'inline; filename="'.$filename.'"']
        );

        return redirect()->away($url);
    }
}
