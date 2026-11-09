<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Link records to the user default filter table.
class UserDefaultFilter extends Model
{
    protected $fillable = ['user_id', 'module', 'filters'];
    protected $casts = ['filters' => 'array'];

    // Link this record to its user.
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
