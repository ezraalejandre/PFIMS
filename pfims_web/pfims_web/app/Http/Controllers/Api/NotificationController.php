<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $this->notifications->syncSystemAlerts();

        $notifications = $this->visibleTo($request)->orderByDesc('created_at')->get();

        return response()->json([
            'notifications' => $notifications->map(fn ($n) => [
                'notification_id' => $n->notification_id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'kind' => $n->kind,
                'filter' => $n->filter,
                'is_read' => (bool) $n->is_read,
                'requires_acknowledgement' => (bool) $n->requires_acknowledgement,
                'action_url' => $n->type === 'password_change_reminder'
                    ? $this->settingsUrl($request->user()?->role)
                    : null,
                'created_at' => $n->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $this->notifications->syncSystemAlerts();

        return response()->json([
            'unread_count' => $this->visibleTo($request)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $notification = $this->visibleTo($request)->where('notification_id', $id)->firstOrFail();

        if ($notification->requires_acknowledgement) {
            $notification->delete();
            return response()->json(['success' => true, 'acknowledged' => true, 'removed' => true]);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $this->visibleTo($request)
            ->where('requires_acknowledgement', false)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->visibleTo($request)->where('notification_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    public function destroyAll(Request $request)
    {
        $this->visibleTo($request)->where('requires_acknowledgement', false)->delete();

        return response()->json(['success' => true]);
    }

    private function visibleTo(Request $request)
    {
        $userId = $request->user()?->id;

        return AppNotification::query()->where(function ($query) use ($userId) {
            $query->whereNull('user_id');
            if ($userId) {
                $query->orWhere('user_id', $userId);
            }
        });
    }

    private function settingsUrl(?string $role): string
    {
        return match (strtolower($role ?? '')) {
            'accounting' => url('/asettings?change_password=1'),
            'operations' => url('/osettings?change_password=1'),
            default => url('/settings?change_password=1'),
        };
    }
}
