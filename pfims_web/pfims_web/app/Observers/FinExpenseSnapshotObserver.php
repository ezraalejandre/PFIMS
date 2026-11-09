<?php

namespace App\Observers;

use App\Models\FinExpense;
use App\Services\AutomaticModelRetraining;

// Group the fin expense snapshot actions.
class FinExpenseSnapshotObserver
{
    // Set up the values this class needs.
    public function __construct(private AutomaticModelRetraining $modelRetraining) {}

    // Get the created.
    public function created(FinExpense $expense): void
    {
        $this->capture($expense, 'expense_created');
    }

    // Get the updated.
    public function updated(FinExpense $expense): void
    {
        $this->capture($expense, 'expense_updated');
    }

    // Get the deleted.
    public function deleted(FinExpense $expense): void
    {
        $this->capture($expense, 'expense_deleted');
    }

    // Get the capture.
    private function capture(FinExpense $expense, string $reason): void
    {
        $this->modelRetraining->afterDataChange($expense->project_id === null ? null : (int) $expense->project_id);
    }
}
