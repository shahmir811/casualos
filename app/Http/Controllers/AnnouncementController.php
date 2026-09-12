<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Admin-only compose screen for the mobile app's Announcements/Timeline
 * feed. Route is fully inside a role:admin group (routes/web.php), so no
 * inline role guard here — same trust-the-middleware precedent as
 * CronLogController/StitchingUnitController.
 */
class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('sentBy')->latest('sent_at')->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function store(Request $request, AnnouncementService $announcements)
    {
        $validated = $request->validate([
            'title'                    => 'required|string|max:255',
            'body'                     => 'required|string',
            'images'                   => 'nullable|array',
            'images.*'                 => 'image|max:10240',
            // Populated by the compose form's JS after the audio file has
            // already been PUT directly to S3 via presign() below — this
            // request never carries the raw audio bytes.
            'audio_key'                => 'nullable|string',
            'audio_original_filename'  => 'nullable|string|max:255',
        ]);

        // Each image is a separate sequential S3 PUT (rule 5.31's "PHP's
        // execution-time cap is a real constraint" applies here too) — two
        // or three images can add up past the default 30-60s limit even
        // though each individual upload is fast. This request is admin-only
        // and already validated (max 10MB per image), so it's safe to give
        // it more room rather than fail a legitimate multi-image post.
        set_time_limit(120);

        $imagePaths = array_map(
            fn ($file) => $file->store('announcements', 's3'),
            $request->file('images', []),
        );

        [$audioPath, $audioFileSize] = $this->resolveUploadedAudio($validated['audio_key'] ?? null);

        if (($validated['audio_key'] ?? null) && $audioPath === null) {
            return back()->withInput()->withErrors(['audio' => 'Voice note upload did not reach storage — please retry.']);
        }

        $announcements->send(
            $validated['title'], $validated['body'], $imagePaths, Auth::user(),
            $audioPath, $audioPath ? ($validated['audio_original_filename'] ?? null) : null, $audioFileSize,
        );

        return redirect()->route('announcements.index')->with('success', 'Announcement sent.');
    }

    /**
     * Issues a presigned S3 PUT URL for a voice note, mirroring
     * CatalogueBookController::presign() — the browser uploads the audio
     * file directly to S3 before the compose form is ever submitted, since
     * a voice note (deliberately uncapped in size, same reasoning as the
     * Catalog Book PDF) could exceed this app's shared-hosting PHP limits.
     *
     * No format allow-list — any audio the browser reports a real
     * `audio/*` MIME type for is accepted, matching the "no cap, just like
     * a WhatsApp voice note" requirement.
     */
    public function presignAudio(Request $request)
    {
        $validated = $request->validate([
            'uuid'         => 'required|uuid',
            'extension'    => 'required|string|max:10',
            'content_type' => 'required|string|regex:/^audio\//',
        ]);

        $key = "announcements/{$validated['uuid']}.{$validated['extension']}";

        $upload = Storage::disk('s3')->temporaryUploadUrl(
            $key,
            now()->addMinutes(30),
            ['ContentType' => $validated['content_type']]
        );

        return response()->json([
            'url'     => $upload['url'],
            'headers' => $upload['headers'],
            'key'     => $key,
        ]);
    }

    /**
     * Re-verifies the presigned-uploaded audio against S3 itself rather than
     * trusting the client — same convention as CatalogueBookController::store().
     * Returns [null, null] for both "no audio attached" and "upload never
     * actually reached S3"; the caller distinguishes those via $audio_key.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveUploadedAudio(?string $audioKey): array
    {
        if (! $audioKey || ! str_starts_with($audioKey, 'announcements/') || ! Storage::disk('s3')->exists($audioKey)) {
            return [null, null];
        }

        return [$audioKey, Storage::disk('s3')->size($audioKey)];
    }
}
