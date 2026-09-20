<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'user_email', 'user_role', 'action_type', 'module',
        'subject_type', 'subject_table', 'subject_key', 'subject_id', 'record_label',
        'details', 'changes', 'view_url',
    ];

    protected $casts = ['changes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
