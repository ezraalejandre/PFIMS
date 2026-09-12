<?php

namespace App\Observers;

use App\Models\Budget;
use App\Services\AutomaticModelRetraining;

class BudgetSnapshotObserver
{
    public function __construct(private AutomaticModelRetraining $modelRetraining) {}

    public function created(Budget $budget): void
    {
        $this->capture($budget, 'budget_created');
    }

    public function updated(Budget $budget): void
    {
        $this->capture($budget, 'budget_updated');
    }

    public function deleted(Budget $budget): void
    {
        $this->capture($budget, 'budget_deleted');
    }

    private function capture(Budget $budget, string $reason): void
    {
        $this->modelRetraining->afterDataChange((int) $budget->project_id);
    }
}
