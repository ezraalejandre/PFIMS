<?php

namespace App\Providers;

use App\Models\Budget;
use App\Models\FinExpense;
use App\Models\InventoryItem;
use App\Models\Project;
use App\Observers\BudgetSnapshotObserver;
use App\Observers\FinExpenseSnapshotObserver;
use App\Observers\ItemObserver;
use App\Observers\ProjectCostSnapshotObserver;
use App\Observers\ProjectObserver;
use App\Services\MLService;
use Illuminate\Support\ServiceProvider;

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
        Project::observe(ProjectCostSnapshotObserver::class);
        Budget::observe(BudgetSnapshotObserver::class);
        FinExpense::observe(FinExpenseSnapshotObserver::class);
    }
}
