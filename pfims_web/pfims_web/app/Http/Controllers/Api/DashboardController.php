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
    private const INACTIVE_STATUSES = ['Completed', 'Pending'];
    private const COMPLETED_STATUS = 'Completed';

    public function index()
    {
        return response()->json([
            'stat_cards'         => $this->statCards(),
            'completion_trend'   => $this->completionTrend(),
            'budget_vs_expense'  => $this->budgetVsExpense(),
            'active_projects'    => $this->activeProjects(),
        ]);
    }

    private function statCards(): array
    {
        $now = Carbon::now();

        $activeProjects = Project::whereNotIn('status', self::INACTIVE_STATUSES)->get();
        $activeCount    = $activeProjects->count();

        $delayedCount = $activeProjects->filter(
            fn ($p) => !empty($p->estimated_end_date) && Carbon::parse($p->estimated_end_date)->isPast()
        )->count();

        $completedThisMonth = Project::where('status', self::COMPLETED_STATUS)
            ->whereBetween('actual_end_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
            ->count();

        $totalBudget = (float) Budget::sum('budget_amount');
        $totalSpent  = $this->totalExpenses();
        $remaining   = $totalBudget - $totalSpent;
        $utilization = $totalBudget > 0 ? (int) round(($totalSpent / $totalBudget) * 100) : 0;

        $equipmentCount = InventoryItem::count();
        $lowStockCount  = InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count();
        $workforce = (int) $activeProjects->sum('worker_count');
        $netVariance = $totalBudget - $totalSpent;

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
                'label'      => 'Equipment Units',
                'value'      => (string) $equipmentCount,
                'subtitle'   => "{$lowStockCount} below reorder level",
                'badge'      => $lowStockCount > 0 ? "{$lowStockCount} low stock" : 'Stocked',
                'badge_type' => $lowStockCount > 0 ? 'warning' : 'positive',
            ],
            [
                'label'      => 'Workforce',
                'value'      => (string) $workforce,
                'subtitle'   => 'Assigned across active projects',
                'badge'      => null,
                'badge_type' => 'positive',
            ],
            [
                'label'      => 'Net Variance',
                'value'      => $this->formatCurrency($netVariance),
                'subtitle'   => 'vs. planned budget',
                'badge'      => $netVariance < 0 ? 'Over budget' : 'Remaining',
                'badge_type' => $netVariance < 0 ? 'negative' : 'positive',
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

            $average = Project::whereMonth('start_date', $month->month)
                ->whereYear('start_date', $month->year)
                ->avg('completion_percentage') ?? 0;

            $values[] = round($average * 0.9 + 10, 0);
        }

        return ['months' => $months, 'values' => $values];
    }

    private function budgetVsExpense(): array
    {
        $months          = [];
        $allocatedBudget = [];
        $expenses        = [];

        for ($i = 5; $i >= 0; $i--) {
            $month    = Carbon::now()->subMonths($i);
            $monthEnd = $month->copy()->endOfMonth();
            $months[] = $month->format('M');

            // A project budget becomes allocated when the project starts.
            // Expense totals are based on the actual recorded expense date.
            $allocatedBudget[] = (float) Project::whereDate('project_tbl.start_date', '<=', $monthEnd->toDateString())
                ->join('budgets_tbl', 'project_tbl.project_id', '=', 'budgets_tbl.project_id')
                ->sum('budgets_tbl.budget_amount');

            $expenses[] = $this->totalExpenses($monthEnd->toDateString());
        }

        return [
            'months'           => $months,
            'allocated_budget' => $allocatedBudget,
            'expenses'         => $expenses,
        ];
    }

    private function totalExpenses(?string $throughDate = null): float
    {
        $query = Expense::query();

        if ($throughDate !== null) {
            $query->whereDate('expense_date', '<=', $throughDate);
        }

        return (float) $query
            ->selectRaw('COALESCE(SUM(COALESCE(labor_amount, 0) + COALESCE(material_amount, 0) + COALESCE(equipment_amount, 0) + COALESCE(other_amount, 0)), 0) AS total')
            ->value('total');
    }

    private function activeProjects(): array
    {
        return Project::whereNotIn('status', self::INACTIVE_STATUSES)
            ->orderByDesc('completion_percentage')
            ->take(10)
            ->get()
            ->map(function (Project $project) {
                $budgetAmount = (float) Budget::where('project_id', $project->project_id)->sum('budget_amount');

                return [
                    'project_id'             => $project->project_id,
                    'name'                   => $project->project_name,
                    'client_name'            => $project->client_name,
                    'budget'                 => $this->formatCurrency($budgetAmount),
                    'start_date'             => $this->formatDate($project->start_date),
                    'estimated_end_date'     => $this->formatDate($project->estimated_end_date),
                    'actual_end_date'        => $this->formatDate($project->actual_end_date),
                    'phase'                  => $project->phase,
                    'status'                 => $project->status,
                    'completion_percentage'  => (float) ($project->completion_percentage ?? 0),
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

    private function formatDate(mixed $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        return Carbon::parse($date)->toDateString();
    }
}
