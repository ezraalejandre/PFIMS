<?php

$directory = env('ML_AUGMENTATION_DATASET') ?: storage_path('app/ml-datasets/current');

return [
    // Installing a private dataset enables ingestion; an explicit false opts out.
    'augmentation_enabled' => (bool) env('ML_AUGMENTATION_ENABLED', env('APP_ENV', 'production') !== 'testing' && is_file($directory.'/manifest.json')),
    'augmentation_directory' => $directory,
];
