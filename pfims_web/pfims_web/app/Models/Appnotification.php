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
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}