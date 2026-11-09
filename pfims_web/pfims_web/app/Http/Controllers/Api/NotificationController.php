<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

// Group the notification actions.
class NotificationController extends Controller
{
    // Set up the values this class needs.
    public function __construct(private NotificationService $notifications)
    {
    }

    // List the notification records.
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

    // Get the unread count.
    public function unreadCount(Request $request)
    {
        $this->notifications->syncSystemAlerts();

        return response()->json([
            'unread_count' => $this->visibleTo($request)->where('is_read', false)->count(),
        ]);
    }

    // Get the mark read.
    public function markRead(Request $request, int $id)
    {
        $notification = $this->visibleTo($request)->where('notification_id', $id)->firstOrFail();

        // Run this step if the requires acknowledgement is set.
        if ($notification->requires_acknowledgement) {
            $notification->delete();
            return response()->json(['success' => true, 'acknowledged' => true, 'removed' => true]);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // Get the mark all read.
    public function markAllRead(Request $request)
    {
        $this->visibleTo($request)
            ->where('requires_acknowledgement', false)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    // Delete the selected notification record.
    public function destroy(Request $request, int $id)
    {
        $this->visibleTo($request)->where('notification_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    // Get the destroy all.
    public function destroyAll(Request $request)
    {
        $this->visibleTo($request)->where('requires_acknowledgement', false)->delete();

        return response()->json(['success' => true]);
    }

    // Get the visible to.
    private function visibleTo(Request $request)
    {
        $userId = $request->user()?->id;

        // Group these checks when finding matching records.
        return AppNotification::query()->where(function ($query) use ($userId) {
            $query->whereNull('user_id');
            // Run this step if the user id is set.
            if ($userId) {
                $query->orWhere('user_id', $userId);
            }
        });
    }

    // Get the settings url.
    private function settingsUrl(?string $role): string
    {
        return match (strtolower($role ?? '')) {
            'accounting' => url('/asettings?change_password=1'),
            'operations' => url('/osettings?change_password=1'),
            default => url('/settings?change_password=1'),
        };
    }
}
