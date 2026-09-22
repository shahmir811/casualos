<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AnnouncementResource;
use App\Models\AnnouncementRead;
use App\Notifications\AnnouncementNotification;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * $request->user()->notifications() (Notifiable trait) is already scoped
     * to the authenticated customer and ordered newest-first. Filtered to
     * AnnouncementNotification rows specifically, so any future notification
     * class that also writes to the `database` channel doesn't leak into
     * this feed.
     */
    public function index(Request $request)
    {
        $announcements = $request->user()->notifications()
            ->where('type', AnnouncementNotification::class)
            ->get();

        return response()->json([
            'announcements' => AnnouncementResource::collection($announcements),
        ]);
    }

    /**
     * Scoped to the authenticated customer's own notifications via the
     * relation query — findOrFail() 404s rather than exposing whether a
     * given id belongs to someone else.
     */
    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        // broadcast_id links back to the Announcement row this notification
        // came from (see AnnouncementNotification::toDatabase()) — absent
        // for notifications sent before this field existed, in which case
        // there's no AnnouncementRead row to update either; the read-stats
        // page shows those broadcasts as "not tracked".
        $broadcastId = $notification->data['broadcast_id'] ?? null;

        if ($broadcastId !== null) {
            // whereNull('read_at') keeps the first-read timestamp on repeat
            // calls rather than pushing it forward every time the app opens.
            AnnouncementRead::where('announcement_id', $broadcastId)
                ->where('customer_id', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json(['status' => 'read']);
    }
}
