<?php

namespace App\Providers;

use App\Models\InventoryItem; 
use App\Models\Project;
use App\Observers\ItemObserver;
use App\Observers\ProjectObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        InventoryItem::observe(ItemObserver::class);
        Project::observe(ProjectObserver::class);
    }
}