<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AnnouncementResource;
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

        return response()->json(['status' => 'read']);
    }
}
