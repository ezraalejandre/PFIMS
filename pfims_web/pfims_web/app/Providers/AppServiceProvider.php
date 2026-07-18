<?php

namespace App\Providers;

use App\Models\InventoryItem; 
use App\Models\Project;
use App\Observers\ItemObserver;
use App\Observers\ProjectObserver;
use Illuminate\Support\ServiceProvider;
use App\Services\MLService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MLService::class, function ($app) {
            return new MLService();
        });
    }

    public function boot(): void
    {
        InventoryItem::observe(ItemObserver::class);
        Project::observe(ProjectObserver::class);
    }
}