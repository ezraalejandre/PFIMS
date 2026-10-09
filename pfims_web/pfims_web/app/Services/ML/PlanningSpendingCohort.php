<?php

namespace App\Services\ML;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Record-date reconstruction. Never writes business tables or backdates physical progress. */
class PlanningSpendingCohort
{
    public static function tables(): array
    {
        return ['project_tbl', 'budgets_tbl', 'project_budget_history', 'fin_expense_tbl', 'fin_expense_category_tbl', 'inventory_item_tbl', 'inventory_transaction_tbl', 'inventory_cost_allocation_tbl'];
    }

    public function database(): array
    {
        $source = [];
        foreach (self::tables() as $table) {
            $source[$table] = Schema::hasTable($table) ? DB::table($table)->orderByRaw('1')->get()->map(fn ($r) => (array) $r)->all() : [];
        }

        return $this->build($source);
    }

    /** The same record-date accounting used in training, limited to today's information. */
    public function forecastInputs(int $projectId, Carbon $start, Carbon $plannedEnd): array
    {
        $at = today()->toDateString();
        $categories = DB::table('fin_expense_category_tbl')->get()->map(fn ($r) => (array) $r)->keyBy('fin_category_id')->all();
        $events = [];
        foreach (DB::table('fin_expense_tbl')->where('project_id', $projectId)->whereNull('inventory_transaction_id')
            ->whereDate('expense_date', '<=', $at)->get() as $expense) {
            $events[] = ['date' => substr($expense->expense_date, 0, 10), 'amount' => (float) $expense->amount,
                'component' => $this->component((array) $expense, $categories), 'inventory' => false];
        }
        $movements = DB::table('inventory_transaction_tbl')->where('project_id', $projectId)->where('transaction_type', 'OUT')
            ->whereDate('transaction_date', '<=', $at)->get();
        $allocations = DB::table('inventory_cost_allocation_tbl')->whereIn('out_transaction_id', $movements->pluck('inventory_transaction_id'))->get()->groupBy('out_transaction_id');
        $complete = true;
        foreach ($movements as $movement) {
            $valuation = $allocations->get($movement->inventory_transaction_id, collect());
            if ($valuation->isEmpty() || $valuation->contains(fn ($a) => $a->valuation_status !== 'valued')) {
                $complete = false;

                continue;
            }
            $events[] = ['date' => substr($movement->transaction_date, 0, 10), 'amount' => (float) $valuation->sum('allocated_amount'), 'component' => 'material', 'inventory' => true];
        }

        return self::summarize($events, $at) + ['elapsed_days' => max(0, (int) $start->diffInDays(today(), false)),
            'planned_duration_days' => max(1, (int) $start->diffInDays($plannedEnd)), 'cost_coverage_complete' => $complete,
            'finance_as_of_date' => collect($events)->max('date')];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse($value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function component(array $expense, array $categories): string
    {
        $component = $expense['project_cost_component'] ?? null;
        if (in_array($component, ['material', 'labor', 'equipment', 'other'], true)) {
            return $component;
        }
        $category = $categories[$expense['fin_category_id'] ?? 0] ?? [];
        $text = strtolower(($category['category_name'] ?? '').' '.($category['category_code'] ?? ''));
        foreach (['material' => ['material', 'supply'], 'labor' => ['labor', 'salary', 'wage', 'payroll'], 'equipment' => ['equipment', 'rental', 'fuel']] as $key => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    public function build(array $source): array
    {
        $records = [];
        $excluded = [];
        $budgetFallback = 0;
        $eligible = 0;
        $budgets = collect($source['budgets_tbl'] ?? [])->groupBy('project_id');
        $history = collect($source['project_budget_history'] ?? [])->groupBy('project_id');
        $expenses = collect($source['fin_expense_tbl'] ?? [])->whereNull('inventory_transaction_id')->groupBy('project_id');
        $movements = collect($source['inventory_transaction_tbl'] ?? [])->where('transaction_type', 'OUT')->groupBy('project_id');
        $allocations = collect($source['inventory_cost_allocation_tbl'] ?? [])->groupBy('out_transaction_id');
        $categories = array_column($source['fin_expense_category_tbl'] ?? [], null, 'fin_category_id');
        foreach ($source['project_tbl'] ?? [] as $project) {
            if (($project['status'] ?? '') !== 'Completed') {
                continue;
            }
            $id = $project['project_id'];
            $start = $this->date($project['start_date'] ?? null);
            $planned = $this->date($project['estimated_end_date'] ?? null);
            $end = $this->date($project['actual_end_date'] ?? null);
            $budgetRow = $budgets->get($id, collect())->sortByDesc('budget_id')->first();
            if (! $start || ! $planned || ! $end || $end->lte($start) || $planned->lte($start) || $end->gt(today()) || ! $budgetRow || (float) $budgetRow['budget_amount'] <= 0) {
                $excluded[$id] = 'invalid_dates_or_budget';

                continue;
            }
            $events = [];
            $invalid = false;
            foreach ($expenses->get($id, collect()) as $expense) {
                $date = $this->date($expense['expense_date'] ?? null);
                $amount = (float) $expense['amount'];
                if (! $date || $date->lt($start) || $date->gt($end) || ! is_finite($amount) || $amount < 0) {
                    $invalid = true;
                    break;
                }
                $events[] = ['date' => $date->toDateString(), 'amount' => $amount, 'component' => $this->component($expense, $categories), 'inventory' => false];
            }
            foreach ($movements->get($id, collect()) as $movement) {
                $date = $this->date($movement['transaction_date'] ?? null);
                $valuation = $allocations->get($movement['inventory_transaction_id'], collect());
                if (! $date || $date->lt($start) || $date->gt($end) || $valuation->isEmpty() || $valuation->contains(fn ($a) => ($a['valuation_status'] ?? '') !== 'valued')) {
                    $invalid = true;
                    break;
                }
                $amount = (float) $valuation->sum('allocated_amount');
                if (! is_finite($amount) || $amount < 0) {
                    $invalid = true;
                    break;
                }
                $events[] = ['date' => $date->toDateString(), 'amount' => $amount, 'component' => 'material', 'inventory' => true];
            }
            $final = round(array_sum(array_column($events, 'amount')), 2);
            if ($invalid || $final <= 0 || ! isset($budgetRow['actual_amount']) || abs((float) $budgetRow['actual_amount'] - $final) > .01) {
                $excluded[$id] = 'invalid_record_dates_unvalued_inventory_or_unreconciled_cost';

                continue;
            }
            $plannedDays = max(1, (int) $start->diffInDays($planned));
            $seen = [];
            $projectRows = [];
            // Observation dates follow planned elapsed time, never the eventual actual duration.
            foreach ([.25, .5, .75] as $fraction) {
                $at = $start->addDays(max(1, (int) floor($plannedDays * $fraction)));
                if ($at->gte($end) || isset($seen[$at->toDateString()])) {
                    continue;
                }
                $seen[$at->toDateString()] = true;
                $version = $history->get($id, collect())->filter(fn ($h) => ! empty($h['effective_at']) && substr($h['effective_at'], 0, 10) <= $at->toDateString())
                    ->sortBy([['effective_at', 'desc'], ['history_id', 'desc']])->first();
                $budget = $version ? (float) $version['budget_amount'] : (float) $budgetRow['budget_amount'];
                if ($budget <= 0) {
                    continue;
                }
                $input = self::summarize($events, $at->toDateString());
                $row = array_replace($input, ['project_id' => $id, 'snapshot_id' => 'record-date-'.$id.'-'.$at->toDateString(),
                    'captured_at' => $at->toDateString(), 'completed_at' => $end->toDateString(), 'planned_start_date' => $start->toDateString(),
                    'budget' => $budget, 'duration_months' => max(1, (int) $start->diffInMonths($planned)), 'worker_count' => (int) ($project['worker_count'] ?? 0),
                    'elapsed_days' => (int) $start->diffInDays($at), 'planned_duration_days' => $plannedDays, 'completion_percentage' => 0,
                    'reconciled_final_cost' => $final, 'actual_cost' => round($final - $input['fin_total_expense'], 2),
                    'data_source' => $project['data_source'] ?? 'operational', 'cost_coverage_complete' => true,
                    'budget_basis' => $version ? 'effective_record_date_budget' : 'latest_recorded_budget_no_effective_history',
                    'observation_basis' => 'record_dates', 'project_name' => $project['project_name']]);
                $row = array_replace($row, PlanningSpendingFeatures::build($row));
                $projectRows[] = (object) $row;
            }
            if (count($projectRows) === 3) {
                $records = [...$records, ...$projectRows];
                $budgetFallback += count(array_filter($projectRows, fn ($row) => $row->budget_basis === 'latest_recorded_budget_no_effective_history'));
                $eligible++;
            } else {
                $excluded[$id] = 'insufficient_pre_completion_time_checkpoints';
            }
        }

        return ['strategy' => PlanningSpendingFeatures::STRATEGY, 'feature_names' => PlanningSpendingFeatures::FEATURES, 'records' => collect($records),
            'snapshot_readiness' => ['eligible' => $eligible >= 10, 'eligible_projects' => $eligible, 'observations' => count($records), 'excluded_projects' => $excluded,
                'budget_fallback_observations' => $budgetFallback, 'observation_basis' => 'record_dates',
                'note' => 'Expenses and withdrawals use business record dates, not posting timestamps. Historical valuations use recorded allocations; backdated entries and latest-budget fallback may differ from what was known then. Physical progress is not used.']];
    }

    public static function summarize(array $events, string $at): array
    {
        $day = CarbonImmutable::parse($at);
        $from7 = $day->subDays(6)->toDateString();
        $from30 = $day->subDays(29)->toDateString();
        $v = ['fin_total_expense' => 0., 'fin_material_expense' => 0., 'fin_labor_expense' => 0., 'fin_equipment_expense' => 0., 'fin_other_expense' => 0.,
            'direct_expense_amount_7d' => 0., 'direct_expense_amount_30d' => 0., 'valued_stock_out_cost_7d' => 0., 'valued_stock_out_cost_30d' => 0.,
            'direct_expense_count_7d' => 0, 'stock_out_count_7d' => 0];
        foreach ($events as $event) {
            if ($event['date'] > $at) {
                continue;
            }
            $v['fin_total_expense'] += $event['amount'];
            $v['fin_'.$event['component'].'_expense'] += $event['amount'];
            $key = $event['inventory'] ? 'valued_stock_out_cost' : 'direct_expense_amount';
            if ($event['date'] >= $from30) {
                $v[$key.'_30d'] += $event['amount'];
            }
            if ($event['date'] >= $from7) {
                $v[$key.'_7d'] += $event['amount'];
                $v[$event['inventory'] ? 'stock_out_count_7d' : 'direct_expense_count_7d']++;
            }
        }
        foreach ($v as $key => $amount) {
            if (str_contains($key, 'expense') || str_contains($key, 'cost')) {
                $v[$key] = round($amount, 2);
            }
        }

        return $v;
    }
}
