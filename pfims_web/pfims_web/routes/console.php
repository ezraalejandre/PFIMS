<?php

use App\Services\MLService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ml:retrain {--scheduled : Mark this run as scheduler-triggered}', function (MLService $ml) {
    $result = $ml->retrain();
    $this->info($result['message']);
    $this->line('Model source: '.$result['model_source']);
    $this->line('Evaluation method: '.($result['metrics']['evaluation_method'] ?? 'unavailable'));
    $this->line('Samples trained: '.($result['metrics']['samples_trained'] ?? 0));

    return 0;
})->purpose('Retrain the project cost model on the newest verified completed projects');

Schedule::command('ml:retrain --scheduled')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping();
