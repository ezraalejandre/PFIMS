<?php

namespace App\Http\Controllers\Api;

use App\Services\ProjectCostLedger;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const INACTIVE_STATUSES = ['Completed', 'Pending'];

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter($request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50|exists:project_tbl,status',
            'stock_status' => 'nullable|in:In stock,Low stock,Out of stock',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]), fn ($value) => $value !== null && $value !== '');

        return response()->json([
            'filters' => $filters,
            'filter_options' => [
                'statuses' => DB::table('project_tbl')->whereNotNull('status')->distinct()->pluck('status')
                    ->sortBy(fn (string $status) => $status === 'Ongoing' ? '0' : '1'.$status)->values(),
                'stock_statuses' => ['In stock', 'Low stock', 'Out of stock'],
            ],
            'stat_cards' => $this->statCards($filters),
            'completion_trend' => $this->completionTrend($filters),
            'budget_vs_expense' => $this->budgetVsExpense($filters),
            'project_status' => $this->projectStatus($filters),
            'stock_status' => $this->stockStatus($filters['stock_status'] ?? null),
            'project_total' => $this->projectQuery($filters)->count(),
            'projects' => $this->projects($filters),
        ]);
    }

    private function statCards(array $filters): array
    {
        $allProjects = $this->projectQuery($filters)->get();
        $activeProjects = $allProjects->whereNotIn('status', self::INACTIVE_STATUSES);
        $delayedCount = $activeProjects->filter(fn ($project) => $project->status === 'Delayed'
            || (! empty($project->estimated_end_date) && Carbon::parse($project->estimated_end_date)->isPast()))->count();
        $projectIds = $allProjects->pluck('project_id');
        $totalBudget = (float) DB::table('budgets_tbl')->when($projectIds->isNotEmpty(), fn ($query) => $query->whereIn('project_id', $projectIds))
            ->when($projectIds->isEmpty(), fn ($query) => $query->whereRaw('1 = 0'))->sum('budget_amount');
        $totalSpent = $this->totalExpenses($projectIds->all());
        $remaining = $totalBudget - $totalSpent;
        $utilization = $totalBudget > 0 ? round($totalSpent / $totalBudget * 100, 1) : 0;
        $stockGroups = $this->inventoryStockGroups();
        $selectedStockStatus = $filters['stock_status'] ?? null;
        $inventoryCount = $selectedStockStatus
            ? (int) ($stockGroups[$selectedStockStatus] ?? 0)
            : (int) (($stockGroups['Low stock'] ?? 0) + ($stockGroups['Out of stock'] ?? 0));
        $averageCompletion = round((float) ($allProjects->avg('completion_percentage') ?? 0), 1);
        $atRiskCount = $activeProjects->where('status', 'At Risk')->count();
        $stockNeedsAction = $inventoryCount > 0 && $selectedStockStatus !== 'In stock';
        $stockIsUrgent = $stockNeedsAction && ($stockGroups['Out of stock'] ?? 0) > 0 && $selectedStockStatus !== 'Low stock';

        $cards = [
            ['label' => 'Matching Projects', 'value' => (string) $allProjects->count(), 'subtitle' => $activeProjects->count().' active', 'badge' => $delayedCount.' delayed', 'badge_type' => $delayedCount ? 'warning' : 'positive'],
            ['label' => 'Average Completion', 'value' => $averageCompletion.'%', 'subtitle' => 'Across matching projects', 'badge' => null, 'badge_type' => 'positive'],
            [
                'label' => $selectedStockStatus ? 'Inventory Items' : 'Inventory Alerts',
                'value' => (string) $inventoryCount,
                'subtitle' => $selectedStockStatus ? $selectedStockStatus.' items' : 'Low-stock and out-of-stock items',
                'badge' => $inventoryCount ? ($selectedStockStatus ?: 'Action needed') : 'None',
                'badge_type' => $inventoryCount && $selectedStockStatus !== 'In stock' ? 'warning' : 'positive',
            ],
        ];
        $cards[0]['subtitle'] .= ' · '.$delayedCount.' delayed · '.$atRiskCount.' marked at risk';
        $cards[0]['badge'] = $delayedCount ? 'Prioritize delays' : ($atRiskCount ? 'Review risks' : 'Monitor schedules');
        $cards[0]['badge_type'] = $delayedCount || $atRiskCount ? 'warning' : 'positive';
        $cards[0]['action'] = $delayedCount ? 'Review overdue work with project managers. Confirm blockers and agree on a recovery schedule.'
            : ($atRiskCount ? 'Review at-risk projects and confirm materials, staffing, and upcoming deadlines.'
                : ($allProjects->isEmpty() ? 'No matching projects. Clear filters to review the portfolio.' : 'No delays or at-risk statuses in this selection. Check upcoming deadlines during the next review.'));
        $cards[0]['action_module'] = 'projects';
        $cards[0]['action_label'] = 'Review project records';
        $cards[1]['badge'] = $allProjects->isEmpty() ? 'No matching data' : 'Review progress';
        $cards[1]['badge_type'] = 'neutral';
        $cards[1]['action'] = $allProjects->isEmpty() ? 'Clear filters to see project progress.' : 'Compare active projects with planned milestones before changing staffing or deadlines. This average includes completed and pending projects; it does not measure schedule health.';
        $cards[1]['action_module'] = 'projects';
        $cards[1]['action_label'] = 'Check project progress';
        $cards[2]['badge'] = $stockIsUrgent ? 'Restock first' : ($stockNeedsAction ? 'Plan replenishment' : 'Monitor stock');
        $cards[2]['badge_type'] = $stockIsUrgent ? 'danger' : ($stockNeedsAction ? 'warning' : 'positive');
        $cards[2]['action'] = $stockIsUrgent ? 'Prioritize out-of-stock materials. Confirm project needs and supplier lead times before ordering.'
            : ($stockNeedsAction ? 'Review low-stock materials. Plan replenishment against upcoming usage and supplier lead times.'
                : ($selectedStockStatus === 'In stock' ? 'These items are in stock. Check Low stock and Out of stock before deciding no purchase is needed.' : 'No low-stock or out-of-stock items recorded. Check quantities before scheduled work.'));
        $cards[2]['action_module'] = 'inventory';
        $cards[2]['action_label'] = 'Review inventory';

        return $cards;
    }

    private function completionTrend(array $filters): array
    {
        $months = collect();
        $values = collect();
        $counts = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $months->push($month->format('M Y'));
            $query = $this->projectQuery($filters)->whereBetween('start_date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
            $counts->push((clone $query)->count());
            $values->push(round((float) ((clone $query)->avg('completion_percentage') ?? 0), 1));
        }

        return ['months' => $months, 'values' => $values, 'project_counts' => $counts];
    }

    private function budgetVsExpense(array $filters): array
    {
        $projects = $this->projectQuery($filters)->get(['project_id', 'start_date']);
        $months = collect();
        $budgets = collect();
        $expenses = collect();
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthEnd = $month->copy()->endOfMonth();
            $months->push($month->format('M Y'));
            $eligibleIds = $projects->filter(fn ($project) => ! empty($project->start_date) && Carbon::parse($project->start_date)->lte($monthEnd))->pluck('project_id');
            $budgets->push($eligibleIds->isEmpty() ? 0 : (float) DB::table('budgets_tbl')->whereIn('project_id', $eligibleIds)->sum('budget_amount'));
            $expenses->push($this->totalExpenses($eligibleIds->all(), $monthEnd->toDateString()));
        }

        return ['months' => $months, 'allocated_budget' => $budgets, 'expenses' => $expenses];
    }

    private function projectStatus(array $filters): array
    {
        $groups = $this->projectQuery($filters)->get()->groupBy(fn ($project) => $project->status ?: 'Unspecified')->map->count();

        return ['labels' => $groups->keys()->values(), 'values' => $groups->values()];
    }

    private function stockStatus(?string $selectedStatus = null): array
    {
        $groups = $this->inventoryStockGroups();
        if ($selectedStatus !== null) {
            $groups = [$selectedStatus => $groups[$selectedStatus] ?? 0];
        }

        return ['labels' => array_keys($groups), 'values' => array_values($groups)];
    }

    private function inventoryStockGroups(): array
    {
        $minimumThreshold = (float) SystemSetting::value('inventory_reorder_threshold', 5);
        $items = DB::table('inventory_item_tbl')->get(['current_stock', 'reorder_level']);
        $groups = ['In stock' => 0, 'Low stock' => 0, 'Out of stock' => 0];
        foreach ($items as $item) {
            $stock = (float) $item->current_stock;
            $reorder = max((float) ($item->reorder_level ?? 0), $minimumThreshold);
            $groups[$stock <= 0 ? 'Out of stock' : ($stock <= $reorder ? 'Low stock' : 'In stock')]++;
        }

        return $groups;
    }

    private function projects(array $filters): array
    {
        $ledger = app(ProjectCostLedger::class);
        $direct = $ledger->directTotals()->pluck('direct_cost', 'project_id');
        $allocated = $ledger->allocatedTotals()?->pluck('allocated_cost', 'project_id') ?? collect();
        return $this->projectQuery($filters)->leftJoin('budgets_tbl as b', 'b.project_id', '=', 'project_tbl.project_id')
            ->select(['project_tbl.project_id', 'project_tbl.project_name as name', 'project_tbl.client_name', 'project_tbl.project_manager',
                'project_tbl.start_date', 'project_tbl.estimated_end_date', 'project_tbl.phase', 'project_tbl.status',
                'project_tbl.actual_end_date',
                'project_tbl.completion_percentage', 'project_tbl.worker_count', DB::raw('COALESCE(b.budget_amount, 0) as budget_amount'),
                DB::raw('COALESCE(b.actual_amount, 0) as actual_amount')])
            ->orderByDesc('project_tbl.start_date')->limit(500)->get()->map(function ($project) use ($direct, $allocated) {
                $project->actual_amount = round((float) ($direct[$project->project_id] ?? 0) + (float) ($allocated[$project->project_id] ?? 0), 2);
                $project->budget = $this->currency((float) $project->budget_amount);

                return $project;
            })->all();
    }

    private function projectQuery(array $filters): Builder
    {
        $query = Project::query();
        if (! empty($filters['status'])) {
            $query->where('project_tbl.status', $filters['status']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('project_tbl.start_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('project_tbl.start_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['search'])) {
            $search = addcslashes(trim($filters['search']), '%_\\');
            $query->where(fn ($inner) => $inner->where('project_tbl.project_name', 'like', "%{$search}%")
                ->orWhere('project_tbl.client_name', 'like', "%{$search}%")
                ->orWhere('project_tbl.project_manager', 'like', "%{$search}%")
                ->orWhere('project_tbl.phase', 'like', "%{$search}%"));
        }

        return $query;
    }

    private function totalExpenses(array $projectIds, ?string $throughDate = null): float
    {
        if ($projectIds === []) {
            return 0;
        }

        return (float) DB::table('fin_expense_tbl')->whereIn('project_id', $projectIds)
            ->when($throughDate, fn ($query) => $query->whereDate('expense_date', '<=', $throughDate))->sum('amount');
    }

    private function currency(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }
}
