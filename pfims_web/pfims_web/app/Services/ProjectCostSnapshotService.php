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
            $isCompleted = strcasecmp((string) ($project->status ?? ''), 'Completed') === 0
                && $completion >= 100
                && ! empty($project->actual_end_date)
                && Carbon::parse($project->actual_end_date)->startOfDay()->betweenIncluded($start, $capturedAt->copy()->startOfDay());
            // The finance ledger is authoritative when it contains rows. The
            // budget actual is only a compatibility fallback for older data.
            $finalCost = $isCompleted
                ? ($finance['has_ledger_rows'] ? (float) $finance['total'] : (float) ($budget->actual_amount ?? 0))
                : null;
            if ($finalCost !== null && $finalCost <= 0) {
                return false;
            }

            DB::table('ml_project_cost_snapshots')->insert([
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
            ]);

            // A final cost becomes a valid label only when completion is
            // recorded. Attach it to that project's earlier genuine snapshots.
            if ($finalCost !== null) {
                DB::table('ml_project_cost_snapshots')
                    ->where('project_id', $projectId)
                    ->update(['final_actual_cost' => $finalCost, 'finalized_at' => $capturedAt]);
            }

            return true;
        }, 3);
    }

    private function financeTotals(int $projectId, Carbon $capturedAt): array
    {
        $result = ['total' => 0.0, 'material' => 0.0, 'labor' => 0.0, 'equipment' => 0.0, 'other' => 0.0, 'as_of_date' => null, 'has_ledger_rows' => false];
        if (! Schema::hasTable('fin_expense_tbl')) {
            return $result;
        }

        $rows = DB::table('fin_expense_tbl as expense')
            ->leftJoin('fin_expense_category_tbl as category', 'category.fin_category_id', '=', 'expense.fin_category_id')
            ->where('expense.project_id', $projectId)
            ->whereDate('expense.expense_date', '<=', $capturedAt->toDateString())
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

        return $result;
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
