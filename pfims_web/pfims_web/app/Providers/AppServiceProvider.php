<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MLService;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(MLService::class, function ($app) {
            return new MLService();
        });
    }

    public function boot()
    {
        //
    }
}