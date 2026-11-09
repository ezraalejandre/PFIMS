<?php

use Illuminate\Support\Str;

// Set the session options for the app.
return [

    // Set the default session driver.

    'driver' => env('SESSION_DRIVER', 'database'),

    // Set the session lifetime.

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => true,
    'account_lease_seconds' => 60,
    'close_grace_seconds' => 5,
    'strict_navigation' => env('SESSION_STRICT_NAVIGATION', false),

    // Set the session encryption.

    'encrypt' => env('SESSION_ENCRYPT', false),

    // Set the session file location.

    'files' => storage_path('framework/sessions'),

    // Set the session database connection.

    'connection' => env('SESSION_CONNECTION'),

    // Set the session database table.

    'table' => env('SESSION_TABLE', 'sessions'),

    // Set the session cache store.

    'store' => env('SESSION_STORE'),

    // Set the session sweeping lottery.

    'lottery' => [2, 100],

    // Set the session cookie name.

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    // Set the session cookie path.

    'path' => env('SESSION_PATH', '/'),

    // Set the session cookie domain.

    'domain' => env('SESSION_DOMAIN'),

    // Set the https only cookies.

    'secure' => env('SESSION_SECURE_COOKIE'),

    // Set the http access only.

    'http_only' => env('SESSION_HTTP_ONLY', true),

    // Set the same site cookies.

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    // Set the partitioned cookies.

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
