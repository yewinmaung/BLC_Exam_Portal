<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);

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
}
