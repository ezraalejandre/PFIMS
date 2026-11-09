<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Link records to the system setting table.
class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['setting_key', 'setting_value'];

    // Get the value.
    public static function value(string $key, mixed $default = null): mixed
    {
        // Try this action and handle any errors below.
        try {
            return static::query()->where('setting_key', $key)->value('setting_value') ?? $default;
        } catch (\Throwable) {
            // Feature tests and older installations may not have run the optional
            // settings migration yet; module defaults keep those requests valid.
            return $default;
        }
    }
}
