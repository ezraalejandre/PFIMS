<?php

// Set the app options for the app.
return [

    // Set the application name.

    'name' => env('APP_NAME', 'Laravel'),

    // Set the application environment.

    'env' => env('APP_ENV', 'production'),

    // Set the application debug mode.

    'debug' => (bool) env('APP_DEBUG', false),

    // Set the application url.

    'url' => env('APP_URL', 'http://localhost'),

    // Set the application timezone.

    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),

    // Set the application locale configuration.

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    // Set the encryption key.

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    // Set the maintenance mode driver.

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
