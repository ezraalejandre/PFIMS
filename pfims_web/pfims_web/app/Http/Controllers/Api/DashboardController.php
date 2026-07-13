<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\InventoryItem;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private const ACTIVE_STATUSES = ['Ongoing', 'In Progress', 'Active'];
    private const COMPLETED_STATUS = 'Completed';

    public function index()
    {
        return response()->json([
            'stat_cards'         => $this->statCards(),
            'completion_trend'   => $this->completionTrend(),
            'budget_vs_spending' => $this->budgetVsSpending(),
            'active_projects'    => $this->activeProjects(),
        ]);
    }

    private function statCards(): array
    {
        $now = Carbon::now();

        $activeProjects = Project::whereIn('status', self::ACTIVE_STATUSES)->get();
        $activeCount    = $activeProjects->count();

        // Adjust 'target_end_date' to your real deadline column name.
        $delayedCount = $activeProjects->filter(
            fn ($p) => !empty($p->target_end_date) && Carbon::parse($p->target_end_date)->isPast()
        )->count();

        $completedThisMonth = Project::where('status', self::COMPLETED_STATUS)
            ->whereBetween('actual_end_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $totalBudget = (float) Budget::sum('budget_amount');
        $totalSpent  = (float) Budget::sum('actual_amount');
        $remaining   = $totalBudget - $totalSpent;
        $utilization = $totalBudget > 0 ? (int) round(($totalSpent / $totalBudget) * 100) : 0;

        $equipmentCount = InventoryItem::count();
        $lowStockCount  = InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count();

        return [
            [
                'label'      => 'Active Projects',
                'value'      => (string) $activeCount,
                'subtitle'   => $delayedCount > 0
                    ? "{$delayedCount} delayed"
                    : "{$completedThisMonth} completed this month",
                'badge'      => $delayedCount > 0 ? "{$delayedCount} delayed" : 'On track',
                'badge_type' => $delayedCount > 0 ? 'warning' : 'positive',
            ],
            [
                'label'      => 'Total Budget',
                'value'      => $this->formatCurrency($totalBudget),
                'subtitle'   => $this->formatCurrency($remaining) . ' remaining',
                'badge'      => "{$utilization}% used",
                'badge_type' => $utilization >= 90 ? 'warning' : 'positive',
            ],
            [
                'label'      => 'Materials & Equipment',
                'value'      => (string) $equipmentCount,
                'subtitle'   => "{$lowStockCount} below reorder level",
                'badge'      => $lowStockCount > 0 ? "{$lowStockCount} low stock" : 'Stocked',
                'badge_type' => $lowStockCount > 0 ? 'warning' : 'positive',
            ],
        ];
    }

    private function completionTrend(): array
    {
        $months = [];
        $values = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $months[] = $month->format('M');

            $values[] = Project::where('status', self::COMPLETED_STATUS)
                ->whereBetween('actual_end_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();
        }

        return ['months' => $months, 'values' => $values];
    }

    private function budgetVsSpending(): array
    {
        $months   = [];
        $budget   = [];
        $spending = [];

        for ($i = 5; $i >= 0; $i--) {
            $month    = Carbon::now()->subMonths($i);
            $monthEnd = $month->copy()->endOfMonth();
            $months[] = $month->format('M');

            $budget[] = (float) Project::where('project_tbl.start_date', '<=', $monthEnd)
                ->join('budgets_tbl', 'project_tbl.project_id', '=', 'budgets_tbl.project_id')
                ->sum('budgets_tbl.budget_amount');

            $spending[] = (float) (Expense::where('expense_date', '<=', $monthEnd)
                ->selectRaw('COALESCE(SUM(labor_amount + material_amount + equipment_amount + other_amount), 0) as total')
                ->value('total') ?? 0);
        }

        return ['months' => $months, 'budget' => $budget, 'spending' => $spending];
    }

    private function activeProjects(): array
    {
        return Project::whereIn('status', self::ACTIVE_STATUSES)
            ->orderByDesc('completion_percentage')
            ->take(3)
            ->get()
            ->map(function (Project $project) {
                $budgetAmount = (float) Budget::where('project_id', $project->project_id)->sum('budget_amount');

                return [
                    'name'    => $project->project_name,
                    'budget'  => $this->formatCurrency($budgetAmount),
                    'percent' => round(($project->completion_percentage ?? 0) / 100, 2),
                ];
            })
            ->values()
            ->toArray();
    }

    private function formatCurrency(float $amount): string
    {
        if ($amount >= 1_000_000) {
            return '₱' . rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.') . 'M';
        }
        if ($amount >= 1_000) {
            return '₱' . rtrim(rtrim(number_format($amount / 1_000, 1), '0'), '.') . 'K';
        }
        return '₱' . number_format($amount);
    }
}