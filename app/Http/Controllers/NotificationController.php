<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);

        // ── True total unread count (across all pages, not just current page) ──
        // Computed BEFORE marking the current page as read so the header badge
        // reflects the accurate pre-clear count on first render.
        $totalUnread = auth()->user()->notifications()->where('is_read', false)->count();

        // ── Mark only the notifications visible on the current page as read ──
        // This respects the approved spec: only notifications the user actually
        // sees on screen are cleared. Historical notifications on other pages
        // remain unread until the user navigates to them.
        $pageIds = $notifications->pluck('id');
        if ($pageIds->isNotEmpty()) {
            \App\Models\UserNotification::whereIn('id', $pageIds)->update(['is_read' => true]);
        }

        // Belt-and-braces: also clear any 'general' category notifications that
        // may not appear on the current page (e.g. if paginated past page 1).
        \App\Models\UserNotification::markCategoryRead(auth()->id(), 'general');

        return view('notifications.index', compact('notifications', 'totalUnread'));
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
        // If a specific category is supplied (e.g. from the bell dropdown),
        // mark only that category as read. Allowed values: exam, result, course, general.
        // The index page "Mark all as read" form sends no category → marks all (original behavior).
        $category = request()->input('category');
        $allowed  = ['exam', 'result', 'course', 'general'];

        if ($category !== null && in_array($category, $allowed, true)) {
            \App\Models\UserNotification::markCategoryRead(auth()->id(), $category);
        } else {
            auth()->user()->notifications()->where('is_read', false)->update(['is_read' => true]);
        }

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
