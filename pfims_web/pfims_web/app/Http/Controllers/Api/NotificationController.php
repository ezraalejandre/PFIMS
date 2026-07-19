<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index()
    {
        $this->notifications->syncSystemAlerts();

        $notifications = AppNotification::orderByDesc('created_at')->get();

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => [
                'notification_id' => $n->notification_id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'kind' => $n->kind,
                'filter' => $n->filter,
                'is_read' => (bool) $n->is_read,
                'created_at' => $n->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function unreadCount()
    {
        $this->notifications->syncSystemAlerts();

        return response()->json([
            'unread_count' => AppNotification::where('is_read', false)->count(),
        ]);
    }

    public function markRead(int $id)
    {
        AppNotification::where('notification_id', $id)->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        AppNotification::where('is_read', false)->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function destroy(int $id)
    {
        AppNotification::where('notification_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function destroyAll()
    {
        AppNotification::query()->delete();

        return response()->json(['success' => true]);
    }
}
