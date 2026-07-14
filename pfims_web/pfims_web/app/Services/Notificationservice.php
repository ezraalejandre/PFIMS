<?php

namespace App\Services;

use App\Models\AppNotification;

class NotificationService
{
    /**
     * Create the in-app notification row. Call this from observers
     * whenever something notification-worthy happens.
     *
     * $referenceType/$referenceId let us avoid duplicate spam — e.g. we
     * don't want to re-notify "low stock" on every single save once an
     * item is already below threshold, only the moment it crosses it.
     */
    public function notify(
        string $title,
        string $message,
        string $type,
        string $kind,
        string $filter = 'alerts',
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): AppNotification {
        return AppNotification::create([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'kind' => $kind,
            'filter' => $filter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * True if a notification of this type for this exact record already
     * exists and is unread — prevents re-notifying every time an already-
     * delayed project or already-low-stock item is saved again.
     */
    public function alreadyNotified(string $type, string $referenceType, int $referenceId): bool
    {
        return AppNotification::where('type', $type)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('is_read', false)
            ->exists();
    }
}