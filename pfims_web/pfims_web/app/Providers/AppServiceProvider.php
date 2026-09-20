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
use App\Observers\AuditLogObserver;
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

        // One observer owns the audit format and field-diff behavior for every
        // user-managed record. System-only rows (notifications, login history,
        // OTPs, settings internals) are deliberately excluded.
        foreach ([
            \App\Models\Project::class,
            \App\Models\Budget::class,
            \App\Models\FinExpense::class,
            \App\Models\InventoryItem::class,
            \App\Models\InventoryTransaction::class,
            \App\Models\Supplier::class,
            \App\Models\CompanyAsset::class,
            \App\Models\CompanyBankAccount::class,
            \App\Models\FinCashPosition::class,
            \App\Models\FinConstructionBond::class,
            \App\Models\FinEquipmentExpense::class,
            \App\Models\FinEquipmentRentalIncome::class,
            \App\Models\FinProjectContract::class,
            \App\Models\FinReceivablePayable::class,
            \App\Models\FinExpenseCategory::class,
            \App\Models\InventoryCategory::class,
            \App\Models\FinanceComponent::class,
            \App\Models\ProjectPhase::class,
            \App\Models\Unit::class,
            \App\Models\Report::class,
            \App\Models\User::class,
            \App\Models\UserDefaultFilter::class,
            \App\Models\SystemSetting::class,
            \App\Models\ExpenseCategory::class,
        ] as $auditableModel) {
            $auditableModel::observe(AuditLogObserver::class);
        }
    }
}
