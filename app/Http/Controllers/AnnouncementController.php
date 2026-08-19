<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'title'     => 'required|string|max:255',
            'body'      => 'required|string',
            'images'    => 'nullable|array',
            'images.*'  => 'image|max:10240',
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

        $announcements->send($validated['title'], $validated['body'], $imagePaths, Auth::user());

        return redirect()->route('announcements.index')->with('success', 'Announcement sent.');
    }
}
