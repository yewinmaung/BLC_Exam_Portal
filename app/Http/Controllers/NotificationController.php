<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);

        // Mark 'general' category notifications as read when user opens Notifications page.
        // All other categories are only cleared by visiting their own nav section.
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'general');

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(UserNotification $notification)
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    public function markAllRead()
    {
        auth()->user()->notifications()->where('is_read', false)->update(['is_read' => true]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function unreadCount()
    {
        $count = auth()->user()->notifications()->where('is_read', false)->count();
        $recent = auth()->user()->notifications()
            ->latest()
            ->take(6)
            ->get()
            ->map(fn($n) => [
                'id'        => $n->id,
                'title'     => $n->title,
                'message'   => $n->message,
                'link'      => $n->link,
                'is_read'   => $n->is_read,
                'time'      => $n->created_at->diffForHumans(),
                'type'      => $n->type,
            ]);

        return response()->json(['count' => $count, 'notifications' => $recent]);
    }

    /**
     * Return unread counts broken down by nav category.
     * Used by the sidebar to render per-item badges.
     *
     * Response: { "exam": 3, "result": 1, "course": 2, "general": 0 }
     */
    public function unreadCountsByCategory()
    {
        return response()->json(
            UserNotification::unreadCountsByCategory(auth()->id())
        );
    }
}
