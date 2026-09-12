<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['setting_key', 'setting_value'];

    public static function value(string $key, mixed $default = null): mixed
    {
        try {
            return static::query()->where('setting_key', $key)->value('setting_value') ?? $default;
        } catch (\Throwable) {
            // Feature tests and older installations may not have run the optional
            // settings migration yet; module defaults keep those requests valid.
            return $default;
        }
    }
}
