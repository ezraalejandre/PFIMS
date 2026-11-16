<?php

$directory = env('ML_AUGMENTATION_DATASET') ?: storage_path('app/ml-datasets/current');

// Set the ml options for the app.
return [
    'planning_spending_enabled' => (bool) env('ML_PLANNING_SPENDING_ENABLED', true),
    // Load the private data file when it exists, unless this setting is turned off.
    'augmentation_enabled' => (bool) env('ML_AUGMENTATION_ENABLED', env('APP_ENV', 'production') !== 'testing' && is_file($directory.'/manifest.json')),
    'augmentation_directory' => $directory,
    'optimization_enabled' => (bool) env('ML_OPTIMIZATION_ENABLED', env('APP_ENV', 'production') !== 'testing'),
];
