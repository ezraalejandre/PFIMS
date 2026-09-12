<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LoginHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ip_address',
        'location',
        'device',
        'browser',
        'user_agent',
        'logged_in_at',
    ];

    protected function casts(): array
    {
        return ['logged_in_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function record(User $user, Request $request): self
    {
        $userAgent = (string) $request->userAgent();
        $ipAddress = $request->ip();

        return static::create([
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'location' => static::resolveLocation($ipAddress),
            'device' => static::detectDevice($userAgent),
            'browser' => static::detectBrowser($userAgent),
            'user_agent' => $userAgent ?: null,
            'logged_in_at' => now(),
        ]);
    }

    private static function resolveLocation(?string $ipAddress): string
    {
        if (!$ipAddress || !filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return 'Local or private network';
        }

        try {
            $response = Http::acceptJson()
                ->timeout(2)
                ->get(rtrim(config('services.ip_geolocation.url'), '/').'/'.$ipAddress, [
                    'fields' => 'success,city,region,country',
                ]);

            if (!$response->successful() || !$response->json('success')) {
                return 'Location unavailable';
            }

            $parts = array_values(array_unique(array_filter([
                $response->json('city'),
                $response->json('region'),
                $response->json('country'),
            ], fn ($value) => is_string($value) && trim($value) !== '')));

            return $parts ? implode(', ', $parts) : 'Location unavailable';
        } catch (\Throwable) {
            return 'Location unavailable';
        }
    }

    private static function detectDevice(string $userAgent): string
    {
        return match (true) {
            preg_match('/iPad|Tablet/i', $userAgent) === 1 => 'Tablet',
            preg_match('/Android|iPhone|Mobile/i', $userAgent) === 1 => 'Mobile device',
            preg_match('/Windows/i', $userAgent) === 1 => 'Windows PC',
            preg_match('/Macintosh|Mac OS/i', $userAgent) === 1 => 'Mac',
            preg_match('/Linux/i', $userAgent) === 1 => 'Linux PC',
            default => 'Unknown device',
        };
    }

    private static function detectBrowser(string $userAgent): string
    {
        return match (true) {
            preg_match('/Edg\//i', $userAgent) === 1 => 'Microsoft Edge',
            preg_match('/OPR\//i', $userAgent) === 1 => 'Opera',
            preg_match('/Chrome\//i', $userAgent) === 1 => 'Google Chrome',
            preg_match('/Firefox\//i', $userAgent) === 1 => 'Mozilla Firefox',
            preg_match('/Safari\//i', $userAgent) === 1 => 'Safari',
            default => 'Unknown browser',
        };
    }
}
