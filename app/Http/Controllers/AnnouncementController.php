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
            'title' => 'required|string|max:255',
            'body'  => 'required|string',
            'image' => 'nullable|image|max:10240',
        ]);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('announcements', 's3')
            : null;

        $announcements->send($validated['title'], $validated['body'], $imagePath, Auth::user());

        return redirect()->route('announcements.index')->with('success', 'Announcement sent.');
    }
}
