<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Project usage is direct spending plus valued stock withdrawals, never storage purchases. */
class ProjectCostLedger
{
    public function directExpenses(): Builder
    {
        $query = DB::table('fin_expense_tbl as cost_expense')->whereNotNull('cost_expense.project_id');
        if (Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
            $query->whereNull('cost_expense.inventory_transaction_id');
        }

        return $query;
    }

    public function directTotals(): Builder
    {
        return $this->directExpenses()
            ->select('cost_expense.project_id')
            ->selectRaw('COALESCE(SUM(cost_expense.amount), 0) as direct_cost')
            ->groupBy('cost_expense.project_id');
    }

    public function allocatedTotals(): ?Builder
    {
        if (! Schema::hasTable('inventory_cost_allocation_tbl')) {
            return null;
        }

        return DB::table('inventory_cost_allocation_tbl as cost_allocation')
            ->select('cost_allocation.project_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN cost_allocation.valuation_status = 'valued' THEN cost_allocation.allocated_amount ELSE 0 END), 0) as allocated_cost")
            ->selectRaw("SUM(CASE WHEN cost_allocation.valuation_status = 'unvalued' THEN 1 ELSE 0 END) as unvalued_count")
            ->groupBy('cost_allocation.project_id');
    }

    public function forProject(int $projectId): array
    {
        $direct = (float) $this->directExpenses()->where('cost_expense.project_id', $projectId)->sum('cost_expense.amount');
        $allocated = 0.0;
        $unvalued = 0;
        if (Schema::hasTable('inventory_cost_allocation_tbl')) {
            $rows = DB::table('inventory_cost_allocation_tbl')->where('project_id', $projectId);
            $allocated = (float) (clone $rows)->where('valuation_status', 'valued')->sum('allocated_amount');
            $unvalued = (clone $rows)->where('valuation_status', 'unvalued')->count();
        }
        if (Schema::hasTable('inventory_transaction_tbl')
            && Schema::hasColumn('inventory_transaction_tbl', 'project_id')) {
            $unallocated = DB::table('inventory_transaction_tbl as withdrawal')
                ->where('withdrawal.project_id', $projectId)->where('withdrawal.transaction_type', 'OUT');
            if (Schema::hasTable('inventory_cost_allocation_tbl')) {
                $unallocated->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('inventory_cost_allocation_tbl as allocation')
                        ->whereColumn('allocation.out_transaction_id', 'withdrawal.inventory_transaction_id');
                });
            }
            $unvalued += $unallocated->count();
            $unvalued += DB::table('inventory_transaction_tbl')->where('project_id', $projectId)
                ->where('transaction_type', 'IN')->count();
        }

        return ['direct' => $direct, 'allocated' => $allocated, 'total' => round($direct + $allocated, 2), 'unvalued_count' => $unvalued];
    }

    public function syncBudget(int $projectId): void
    {
        if (! Schema::hasTable('budgets_tbl')) {
            return;
        }
        DB::table('budgets_tbl')->where('project_id', $projectId)
            ->update(['actual_amount' => $this->forProject($projectId)['total']]);
    }
}
