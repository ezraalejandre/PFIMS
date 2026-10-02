<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProjectCostSnapshotService
{
    /**
     * Capture only the state known now. This method deliberately never creates
     * backdated snapshots from reconstructed or sample progress.
     */
    public function captureAfterCommit(?int $projectId, string $reason): void
    {
        if (! $projectId) {
            return;
        }

        DB::afterCommit(function () use ($projectId, $reason): void {
            try {
                $this->capture($projectId, $reason);
            } catch (Throwable $exception) {
                Log::error('Project cost snapshot capture failed.', [
                    'project_id' => $projectId,
                    'reason' => $reason,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function capture(int $projectId, string $reason = 'manual'): bool
    {
        if (! Schema::hasTable('ml_project_cost_snapshots')) {
            return false;
        }

        return DB::transaction(function () use ($projectId, $reason): bool {
            // Serialize captures for one project so a snapshot cannot combine
            // project, budget, and ledger states from different commits.
            $project = DB::table('project_tbl')->where('project_id', $projectId)->lockForUpdate()->first();
            if (! $project || empty($project->start_date) || empty($project->estimated_end_date)) {
                return false;
            }

            $start = Carbon::parse($project->start_date)->startOfDay();
            $plannedEnd = Carbon::parse($project->estimated_end_date)->startOfDay();
            if ($plannedEnd->lt($start)) {
                return false;
            }
            $workerCount = filter_var($project->worker_count ?? null, FILTER_VALIDATE_INT);
            $completion = is_numeric($project->completion_percentage ?? null)
                ? (float) $project->completion_percentage
                : null;
            if ($workerCount === false || $workerCount < 1 || $completion === null || $completion < 0 || $completion > 100) {
                return false;
            }

            $budget = DB::table('budgets_tbl')->where('project_id', $projectId)
                ->orderByDesc('budget_id')->first();
            if (! $budget || (float) $budget->budget_amount <= 0) {
                return false;
            }

            $capturedAt = now();
            $finance = $this->financeTotals($projectId, $capturedAt);
            $activity = $this->activityForProject($projectId, $capturedAt, $finance);
            $isCompleted = strcasecmp((string) ($project->status ?? ''), 'Completed') === 0
                && $completion >= 100
                && ! empty($project->actual_end_date)
                && Carbon::parse($project->actual_end_date)->startOfDay()->betweenIncluded($start, $capturedAt->copy()->startOfDay());
            // An unvalued withdrawal is missing cost, not a zero-cost material.
            $reconciled = $finance['unvalued_count'] === 0
                && abs((float) $budget->actual_amount - (float) $finance['total']) <= 0.01;
            $finalCost = $isCompleted && (! Schema::hasTable('inventory_cost_allocation_tbl') || $reconciled)
                ? ($finance['has_ledger_rows'] || Schema::hasTable('inventory_cost_allocation_tbl')
                    ? (float) $finance['total'] : (float) ($budget->actual_amount ?? 0))
                : null;
            if ($finalCost !== null && $finalCost <= 0) {
                $finalCost = null;
            }

            $snapshot = [
                'project_id' => $projectId,
                'captured_at' => $capturedAt,
                'capture_reason' => mb_substr($reason, 0, 32),
                'planned_budget' => $budget->budget_amount,
                'planned_duration_months' => max(1, (int) $start->diffInMonths($plannedEnd)),
                'worker_count' => $workerCount,
                'completion_percentage' => $completion,
                'phase' => blank($project->phase ?? null) ? null : mb_substr((string) $project->phase, 0, 100),
                'elapsed_duration_months' => round(max(0, $start->floatDiffInMonths($capturedAt)), 2),
                'finance_as_of_date' => $finance['as_of_date'],
                'cumulative_total_expense' => $finance['total'],
                'cumulative_material_expense' => $finance['material'],
                'cumulative_labor_expense' => $finance['labor'],
                'cumulative_equipment_expense' => $finance['equipment'],
                'cumulative_other_expense' => $finance['other'],
                'final_actual_cost' => $finalCost,
                'finalized_at' => $finalCost === null ? null : $capturedAt,
                'data_source' => Schema::hasColumn('project_tbl', 'data_source')
                    ? ($project->data_source ?: 'operational')
                    : 'operational',
            ];
            if (Schema::hasColumn('ml_project_cost_snapshots', 'budget_history_id')) {
                $budgetContext = app(BudgetHistoryService::class)->context($projectId, (float) $budget->budget_amount, (int) $budget->budget_id);
                $snapshot['budget_history_id'] = $budgetContext['current_budget_version_id'];
                $snapshot['original_budget_amount'] = $budgetContext['original_budget_amount'];
                $snapshot['budget_basis'] = $budgetContext['budget_basis'];
            }
            foreach ($activity as $column => $value) {
                if (Schema::hasColumn('ml_project_cost_snapshots', $column)) {
                    $snapshot[$column] = $value;
                }
            }
            DB::table('ml_project_cost_snapshots')->insert($snapshot);

            // A final cost becomes a valid label only when completion is
            // recorded. Attach it to that project's earlier genuine snapshots.
            if ($finalCost !== null) {
                DB::table('ml_project_cost_snapshots')
                    ->where('project_id', $projectId)
                    ->update(['final_actual_cost' => $finalCost, 'finalized_at' => $capturedAt]);
            } elseif ($isCompleted && Schema::hasTable('inventory_cost_allocation_tbl')) {
                // Corrections can invalidate a previously reconciled outcome.
                DB::table('ml_project_cost_snapshots')
                    ->where('project_id', $projectId)
                    ->update(['final_actual_cost' => null, 'finalized_at' => null]);
            }

            return true;
        }, 3);
    }

    private function financeTotals(int $projectId, Carbon $capturedAt): array
    {
        $result = ['total' => 0.0, 'material' => 0.0, 'labor' => 0.0, 'equipment' => 0.0, 'other' => 0.0, 'as_of_date' => null, 'has_ledger_rows' => false, 'unvalued_count' => 0, 'allocated_material' => 0.0];
        if (! Schema::hasTable('fin_expense_tbl')) {
            return $result;
        }

        $query = DB::table('fin_expense_tbl as expense')
            ->leftJoin('fin_expense_category_tbl as category', 'category.fin_category_id', '=', 'expense.fin_category_id')
            ->where('expense.project_id', $projectId)
            ->whereDate('expense.expense_date', '<=', $capturedAt->toDateString());
        if (Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
            $query->whereNull('expense.inventory_transaction_id');
        }
        $rows = $query
            ->select('expense.amount', 'expense.expense_date', 'expense.project_cost_component', 'category.category_code', 'category.category_name')
            ->get();

        foreach ($rows as $row) {
            $result['has_ledger_rows'] = true;
            $amount = max(0, (float) $row->amount);
            $component = $this->normalizeComponent($row);
            $result['total'] += $amount;
            $result[$component] += $amount;
            if ($row->expense_date && ($result['as_of_date'] === null || $row->expense_date > $result['as_of_date'])) {
                $result['as_of_date'] = $row->expense_date;
            }
        }

        if (Schema::hasTable('inventory_cost_allocation_tbl')) {
            $allocations = DB::table('inventory_cost_allocation_tbl as allocation')
                ->join('inventory_transaction_tbl as movement', 'movement.inventory_transaction_id', '=', 'allocation.out_transaction_id')
                ->where('allocation.project_id', $projectId)
                ->whereDate('movement.transaction_date', '<=', $capturedAt->toDateString())
                ->get(['allocation.valuation_status', 'allocation.allocated_amount', 'movement.transaction_date']);
            foreach ($allocations as $allocation) {
                if ($allocation->valuation_status !== 'valued') {
                    $result['unvalued_count']++;
                    continue;
                }
                $amount = (float) $allocation->allocated_amount;
                $result['has_ledger_rows'] = true;
                $result['total'] += $amount;
                $result['material'] += $amount;
                $result['allocated_material'] += $amount;
                if ($result['as_of_date'] === null || $allocation->transaction_date > $result['as_of_date']) {
                    $result['as_of_date'] = $allocation->transaction_date;
                }
            }
        }
        if (Schema::hasTable('inventory_transaction_tbl')
            && Schema::hasColumn('inventory_transaction_tbl', 'project_id')) {
            $unallocated = DB::table('inventory_transaction_tbl as withdrawal')
                ->where('withdrawal.project_id', $projectId)
                ->where('withdrawal.transaction_type', 'OUT')
                ->whereDate('withdrawal.transaction_date', '<=', $capturedAt->toDateString());
            if (Schema::hasTable('inventory_cost_allocation_tbl')) {
                $unallocated->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('inventory_cost_allocation_tbl as allocation')
                        ->whereColumn('allocation.out_transaction_id', 'withdrawal.inventory_transaction_id');
                });
            }
            $result['unvalued_count'] += $unallocated->count();
            $projectReceipts = DB::table('inventory_transaction_tbl as receipt')
                ->where('receipt.project_id', $projectId)->where('receipt.transaction_type', 'IN')
                ->whereDate('receipt.transaction_date', '<=', $capturedAt->toDateString());
            if (Schema::hasColumns('fin_expense_tbl',
                ['inventory_transaction_id', 'project_id', 'entry_kind', 'amount'])) {
                $projectReceipts->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('fin_expense_tbl as purchase')
                        ->whereColumn('purchase.inventory_transaction_id', 'receipt.inventory_transaction_id')
                        ->whereColumn('purchase.project_id', 'receipt.project_id')
                        ->where('purchase.entry_kind', 'inventory_purchase')->where('purchase.amount', '>', 0);
                });
            }
            $result['unvalued_count'] += $projectReceipts->count();
        }

        return $result;
    }

    public function activityForProject(int $projectId, ?Carbon $capturedAt = null, ?array $finance = null): array
    {
        $capturedAt ??= now();
        $finance ??= $this->financeTotals($projectId, $capturedAt);
        $features = [
            'direct_expense_count_7d' => 0, 'direct_expense_count_30d' => 0,
            'direct_expense_amount_30d' => 0, 'stock_out_count_7d' => 0,
            'stock_out_count_30d' => 0, 'stock_out_quantity_30d' => 0,
            'valued_stock_out_cost' => $finance['allocated_material'],
            'unvalued_stock_out_count' => $finance['unvalued_count'],
        ];
        $asOf = $capturedAt->toDateString();
        $sevenDaysAgo = $capturedAt->copy()->subDays(6)->toDateString();
        $thirtyDaysAgo = $capturedAt->copy()->subDays(29)->toDateString();

        if (Schema::hasTable('fin_expense_tbl')) {
            $direct = DB::table('fin_expense_tbl')->where('project_id', $projectId);
            if (Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
                $direct->whereNull('inventory_transaction_id');
            }
            $features['direct_expense_count_7d'] = (clone $direct)->whereBetween('expense_date', [$sevenDaysAgo, $asOf])->count();
            $month = (clone $direct)->whereBetween('expense_date', [$thirtyDaysAgo, $asOf]);
            $features['direct_expense_count_30d'] = (clone $month)->count();
            $features['direct_expense_amount_30d'] = (float) $month->sum('amount');
        }
        if (Schema::hasTable('inventory_transaction_tbl')) {
            $withdrawals = DB::table('inventory_transaction_tbl')
                ->where('project_id', $projectId)->where('transaction_type', 'OUT');
            $features['stock_out_count_7d'] = (clone $withdrawals)->whereBetween('transaction_date', [$sevenDaysAgo, $asOf])->count();
            $month = (clone $withdrawals)->whereBetween('transaction_date', [$thirtyDaysAgo, $asOf]);
            $features['stock_out_count_30d'] = (clone $month)->count();
            $features['stock_out_quantity_30d'] = (float) $month->sum('quantity');
        }

        return $features;
    }

    private function normalizeComponent(object $row): string
    {
        $category = strtolower(trim(($row->category_code ?? '').' '.($row->category_name ?? '')));
        foreach ([
            'material' => ['material', 'supply', 'cement', 'steel', 'sand', 'gravel', 'lumber', 'hardware'],
            'labor' => ['labor', 'labour', 'salary', 'wage', 'payroll', 'worker', 'manpower'],
            'equipment' => ['equipment', 'machine', 'backhoe', 'rental', 'repair', 'maintenance', 'fuel', 'diesel', 'gasoline'],
        ] as $component => $terms) {
            foreach ($terms as $term) {
                if (str_contains($category, $term)) {
                    return $component;
                }
            }
        }

        $explicit = strtolower(trim((string) ($row->project_cost_component ?? '')));
        if (in_array($explicit, ['material', 'labor', 'equipment', 'other'], true)) {
            return $explicit;
        }

        return 'other';
    }
}
