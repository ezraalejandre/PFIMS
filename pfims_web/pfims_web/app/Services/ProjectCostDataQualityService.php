<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Read-only checks of final-cost labels; never invents missing costs or dates. */
class ProjectCostDataQualityService
{
    public function inspect(int $projectId): array
    {
        $errors = [];
        $warnings = [];
        $project = DB::table('project_tbl')->where('project_id', $projectId)->first();
        $budget = DB::table('budgets_tbl')->where('project_id', $projectId)->orderByDesc('budget_id')->first();
        if (! $project) {
            return ['project_id' => $projectId, 'eligible' => false, 'errors' => ['missing_project'], 'warnings' => []];
        }
        if (($project->status ?? '') !== 'Completed' || (float) ($project->completion_percentage ?? 0) !== 100.0) {
            $errors[] = 'not_completed';
        }
        $dates = [];
        foreach (['start_date', 'estimated_end_date', 'actual_end_date'] as $field) {
            try {
                $value = $project->{$field} ?? null;
                $date = $value ? Carbon::createFromFormat('!Y-m-d', substr((string) $value, 0, 10)) : null;
                if (! $date || $date->toDateString() !== substr((string) $value, 0, 10)) {
                    throw new \InvalidArgumentException;
                }
                $dates[$field] = $date;
            } catch (Throwable) {
                $errors[] = 'invalid_'.$field;
            }
        }
        foreach (['estimated_end_date', 'actual_end_date'] as $field) {
            if (isset($dates[$field], $dates['start_date']) && $dates[$field]->lt($dates['start_date'])) {
                $errors[] = $field.'_before_start';
            }
        }
        if (isset($dates['actual_end_date']) && $dates['actual_end_date']->gt(today())) {
            $errors[] = 'future_completion';
        }
        if (! is_numeric($project->worker_count ?? null) || (float) $project->worker_count < 1) {
            $errors[] = 'invalid_worker_count';
        }
        if (! $budget || (float) $budget->budget_amount <= 0) {
            $errors[] = 'missing_positive_budget';
        }
        if (DB::table('budgets_tbl')->where('project_id', $projectId)->count() > 1) {
            $errors[] = 'duplicate_active_budgets';
        }

        $cost = ['total' => (float) ($budget->actual_amount ?? 0), 'unvalued_count' => 0];
        if (Schema::hasTable('fin_expense_tbl')) {
            $cost = app(ProjectCostLedger::class)->forProject($projectId);
            if ($cost['unvalued_count'] > 0) {
                $errors[] = 'missing_inventory_valuation';
            }
            if ($budget && abs((float) $budget->actual_amount - $cost['total']) > 0.01) {
                $errors[] = 'budget_actual_does_not_match_ledger';
            }
            $expenses = DB::table('fin_expense_tbl')->where('fin_expense_tbl.project_id', $projectId);
            if ((clone $expenses)->where('amount', '<=', 0)->exists()) {
                $errors[] = 'nonpositive_expense';
            }
            if (Schema::hasColumn('fin_expense_tbl', 'expense_date')) {
                if ((clone $expenses)->whereNull('expense_date')->exists()
                    || (clone $expenses)->whereDate('expense_date', '>', today())->exists()) {
                    $errors[] = 'missing_or_future_expense_date';
                }
                if (isset($dates['actual_end_date']) && (clone $expenses)->whereDate('expense_date', '>', $dates['actual_end_date'])->exists()) {
                    // Late invoices can be legitimate final costs, but cannot be historical features.
                    $warnings[] = 'costs_recorded_after_completion';
                }
                if (isset($dates['start_date']) && (clone $expenses)->whereDate('expense_date', '<', $dates['start_date'])->exists()) {
                    $warnings[] = 'costs_recorded_before_start';
                }
            }
            if (Schema::hasColumn('fin_expense_tbl', 'remarks')
                && (clone $expenses)->where('remarks', 'like', 'Historical item-price estimate:%')->exists()) {
                $warnings[] = 'estimated_historical_prices';
            }
            if (Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
                $linked = (clone $expenses)->whereNotNull('fin_expense_tbl.inventory_transaction_id');
                if ((clone $linked)->select('inventory_transaction_id')->groupBy('inventory_transaction_id')->havingRaw('COUNT(*) > 1')->exists()) {
                    $errors[] = 'multiple_purchase_expenses_per_receipt';
                }
                if (Schema::hasTable('inventory_transaction_tbl')) {
                    if ((clone $linked)->leftJoin('inventory_transaction_tbl as receipt', 'receipt.inventory_transaction_id', '=', 'fin_expense_tbl.inventory_transaction_id')
                        ->where(function ($q) {
                            $q->whereNull('receipt.inventory_transaction_id')->orWhere('receipt.transaction_type', '<>', 'IN')
                                ->orWhereColumn('receipt.project_id', '<>', 'fin_expense_tbl.project_id')->orWhereNull('receipt.project_id');
                        })->exists()) {
                        $errors[] = 'invalid_project_purchase_link';
                    }
                }
            }
        } else {
            $warnings[] = 'ledger_unavailable';
        }
        if ($cost['total'] <= 0) {
            $errors[] = 'missing_positive_final_cost';
        }
        if (Schema::hasTable('inventory_cost_allocation_tbl') && Schema::hasColumns('inventory_cost_allocation_tbl',
            ['quantity', 'unit_cost', 'allocated_amount', 'in_transaction_id', 'out_transaction_id'])) {
            $allocations = DB::table('inventory_cost_allocation_tbl')->where('project_id', $projectId)->get();
            foreach ($allocations as $allocation) {
                $receipt = DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $allocation->in_transaction_id)->first();
                $withdrawal = DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $allocation->out_transaction_id)->first();
                if (! $receipt || ! $withdrawal || $receipt->transaction_type !== 'IN' || $withdrawal->transaction_type !== 'OUT'
                    || (int) $withdrawal->project_id !== $projectId || $receipt->item_id !== $withdrawal->item_id) {
                    $errors[] = 'invalid_inventory_allocation_link';
                }
                if ($receipt && $withdrawal && (! $receipt->transaction_date || ! $withdrawal->transaction_date
                    || $receipt->transaction_date > $withdrawal->transaction_date)) {
                    $errors[] = 'inventory_used_before_receipt_or_missing_date';
                }
                if ($receipt && (float) DB::table('inventory_cost_allocation_tbl')->where('in_transaction_id', $receipt->inventory_transaction_id)->sum('quantity') > (float) $receipt->quantity + 0.001) {
                    $errors[] = 'receipt_quantity_overallocated';
                }
                if ($receipt && Schema::hasColumns('fin_expense_tbl', ['inventory_transaction_id', 'entry_kind'])) {
                    $purchases = DB::table('fin_expense_tbl')->where('inventory_transaction_id', $receipt->inventory_transaction_id)->get();
                    $purchase = $purchases->first();
                    if ($purchases->count() !== 1 || ! $purchase || $purchase->entry_kind !== 'inventory_purchase'
                        || $purchase->amount <= 0 || $receipt->quantity <= 0
                        || $purchase->project_id != $receipt->project_id) {
                        $errors[] = 'unverified_inventory_purchase_source';
                    } elseif ($allocation->valuation_status === 'valued'
                        && abs((float) $allocation->unit_cost - (float) $purchase->amount / (float) $receipt->quantity) > 0.0000011) {
                        $errors[] = 'allocation_price_does_not_match_purchase';
                    }
                    if ($purchase && str_starts_with((string) ($purchase->remarks ?? ''), 'Historical item-price estimate:')) {
                        $warnings[] = 'estimated_historical_prices';
                    }
                }
                if ($allocation->valuation_status !== 'valued' || $allocation->quantity <= 0
                    || $allocation->unit_cost === null || $allocation->unit_cost <= 0 || $allocation->allocated_amount === null
                    || abs(round((float) $allocation->quantity * (float) $allocation->unit_cost, 2) - (float) $allocation->allocated_amount) > 0.01) {
                    $errors[] = 'invalid_inventory_allocation_value';
                }
            }
            $withdrawals = DB::table('inventory_transaction_tbl')->where('project_id', $projectId)->where('transaction_type', 'OUT')->get();
            foreach ($withdrawals as $withdrawal) {
                // Sum all allocations to detect both missing quantity and over-allocation.
                $quantity = (float) DB::table('inventory_cost_allocation_tbl')->where('out_transaction_id', $withdrawal->inventory_transaction_id)->sum('quantity');
                if ($withdrawal->quantity <= 0 || abs($quantity - (float) $withdrawal->quantity) > 0.001) {
                    $errors[] = 'inventory_quantity_not_fully_allocated';
                }
            }
        }
        $context = app(BudgetHistoryService::class)->context($projectId, $budget ? (float) $budget->budget_amount : null, $budget ? (int) $budget->budget_id : null);
        if ($context['original_budget_amount'] === null) {
            $warnings[] = 'original_budget_unknown';
        }

        return [
            'project_id' => $projectId, 'project_name' => $project->project_name,
            'eligible' => $errors === [], 'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)), 'final_cost' => $cost['total'],
            'budget_context' => $context,
            'overrun_outcomes' => app(ProjectOverrunPolicy::class)->outcomes($cost['total'], $context),
        ];
    }

    public function report(): array
    {
        $projects = DB::table('project_tbl')->orderBy('project_id')->pluck('project_id')
            ->map(fn ($id) => $this->inspect((int) $id))->all();
        $eligible = array_values(array_filter($projects, fn ($p) => $p['eligible']));
        $reasons = [];
        foreach ($projects as $project) {
            foreach ($project['errors'] as $reason) {
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }
        }
        ksort($reasons);

        return [
            'audited_at' => now()->toIso8601String(), 'read_only' => true,
            'cost_definition' => 'Direct expenses plus valued inventory withdrawals; linked purchases are not counted twice.',
            'scope' => 'Current database eligibility, not saved-model evaluation; one final label per project.',
            'project_count' => count($projects), 'eligible_project_count' => count($eligible),
            'excluded_project_count' => count($projects) - count($eligible),
            'eligible_any_overrun_count' => count(array_filter($eligible, fn ($p) => $p['overrun_outcomes']['current_budget']['any_overrun'] === true)),
            'eligible_material_overrun_count' => count(array_filter($eligible, fn ($p) => $p['overrun_outcomes']['current_budget']['material_overrun'] === true)),
            'eligible_estimated_price_count' => count(array_filter($eligible, fn ($p) => in_array('estimated_historical_prices', $p['warnings'], true))),
            'exclusion_reason_counts' => $reasons, 'projects' => $projects,
        ];
    }

    /** Preparation evidence only; never manufactures outcomes or changes eligibility. */
    public function trainingReadiness(): array
    {
        $audit = $this->report();
        $reports = (new MLService(loadModel: false))->getCandidateEvaluationReports()['reports'] ?? [];
        // Include both partitions: training projects are also no longer untouched evidence.
        $seen = collect($reports)->flatMap(fn ($report) => [
            ...($report['training_project_ids'] ?? []), ...($report['holdout_project_ids'] ?? []),
        ])->unique()->all();
        $eligible = collect($audit['projects'])->where('eligible', true);
        $sources = DB::table('project_tbl')->pluck('data_source', 'project_id')->all();
        $operational = $eligible->filter(fn ($project) => ($sources[$project['project_id']] ?? 'operational') === 'operational');
        $unseen = $operational->reject(fn ($project) => in_array($project['project_id'], $seen));
        $outcomes = static fn ($projects) => [
            'projects' => $projects->count(),
            'any_overrun' => $projects->filter(fn ($p) => $p['overrun_outcomes']['current_budget']['any_overrun'] === true)->count(),
            'within_budget' => $projects->filter(fn ($p) => $p['overrun_outcomes']['current_budget']['any_overrun'] === false)->count(),
        ];

        return ['read_only' => true, 'database_changed' => false, 'model_changed' => false,
            'eligible_completed' => $outcomes($eligible), 'operational_completed' => $outcomes($operational),
            'operational_not_in_saved_evaluations' => $outcomes($unseen),
            'candidate_project_ids_for_independent_review' => $unseen->pluck('project_id')->values()->all(),
            'excluded_projects' => $audit['excluded_project_count'], 'exclusion_reason_counts' => $audit['exclusion_reason_counts'],
            'snapshot_readiness' => (new MLService(loadModel: false))->getSnapshotReadiness(),
            'independent_holdout_reserved' => false,
            'note' => 'Absence from saved evaluations does not prove independence. Verify source records and prior use before reserving newly completed operational projects. Presentation records cannot establish operational performance.',
            'collection_requirements' => ['Reconciled completed costs and both outcome classes.',
                'Genuine earlier stage snapshots, with at least 10 distinct finalized projects in each stage.',
                'New test projects excluded from all model development and tuning.']];
    }
}
