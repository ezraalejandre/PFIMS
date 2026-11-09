<?php

namespace App\Observers;

use App\Models\Budget;
use App\Services\AutomaticModelRetraining;

// Group the budget snapshot actions.
class BudgetSnapshotObserver
{
    // Set up the values this class needs.
    public function __construct(private AutomaticModelRetraining $modelRetraining) {}

    // Get the created.
    public function created(Budget $budget): void
    {
        $this->capture($budget, 'budget_created');
    }

    // Get the updated.
    public function updated(Budget $budget): void
    {
        $this->capture($budget, 'budget_updated');
    }

    // Get the deleted.
    public function deleted(Budget $budget): void
    {
        $this->capture($budget, 'budget_deleted');
    }

    // Get the capture.
    private function capture(Budget $budget, string $reason): void
    {
        $this->modelRetraining->afterDataChange((int) $budget->project_id);
    }
}
