<?php

namespace App\Observers;

use App\Models\FinExpense;
use App\Services\AutomaticModelRetraining;

class FinExpenseSnapshotObserver
{
    public function __construct(private AutomaticModelRetraining $modelRetraining) {}

    public function created(FinExpense $expense): void
    {
        $this->capture($expense, 'expense_created');
    }

    public function updated(FinExpense $expense): void
    {
        $this->capture($expense, 'expense_updated');
    }

    public function deleted(FinExpense $expense): void
    {
        $this->capture($expense, 'expense_deleted');
    }

    private function capture(FinExpense $expense, string $reason): void
    {
        $this->modelRetraining->afterDataChange($expense->project_id === null ? null : (int) $expense->project_id);
    }
}
