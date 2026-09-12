<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Named AppNotification (not "Notification") to avoid clashing with
// Laravel's built-in Illuminate\Notifications\Notification class.
class AppNotification extends Model
{
    protected $table = 'notifications_tbl';
    protected $primaryKey = 'notification_id';

    protected $fillable = [
        'title',
        'message',
        'type',
        'kind',
        'filter',
        'reference_type',
        'reference_id',
        'user_id',
        'requires_acknowledgement',
        'acknowledged_at',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];
}
