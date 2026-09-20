<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDefaultFilter extends Model
{
    protected $fillable = ['user_id', 'module', 'filters'];
    protected $casts = ['filters' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
