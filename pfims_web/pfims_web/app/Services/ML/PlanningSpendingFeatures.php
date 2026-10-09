<?php

namespace App\Services\ML;

use RuntimeException;

/** Planning and recorded spending only; physical completion is never an input. */
class PlanningSpendingFeatures
{
    public const STRATEGY = 'planning_spending_model';

    public const FEATURES = ['budget', 'duration_months', 'worker_count', 'elapsed_days',
        'remaining_planned_days', 'elapsed_time_fraction', 'fin_total_expense',
        'budget_used_fraction', 'material_cost_share', 'labor_cost_share', 'equipment_cost_share',
        'cost_burn_rate_30d', 'burn_rate_acceleration', 'inventory_cost_burn_rate_30d',
        'expense_frequency_7d', 'stock_out_frequency_7d'];

    public static function validate(array $cohort): array
    {
        if (($cohort['strategy'] ?? null) !== self::STRATEGY || array_diff(self::FEATURES, $cohort['feature_names'] ?? []) !== []) {
            throw new RuntimeException('Planning and spending evaluation requires dated spending observations; planning-only fallback is disabled.');
        }
        foreach ($cohort['records'] as $row) {
            foreach (self::FEATURES as $name) {
                if (! isset($row->{$name}) || ! is_numeric($row->{$name}) || ! is_finite((float) $row->{$name})) {
                    throw new RuntimeException('Missing or invalid planning/spending input: '.$name);
                }
            }
            if (! isset($row->snapshot_id)) {
                throw new RuntimeException('Historical spending observation identity is required.');
            }
        }

        return self::FEATURES;
    }

    public static function build(array $input): array
    {
        $n = static fn ($key) => (float) ($input[$key] ?? 0);
        $budget = $n('budget');
        $spent = $n('fin_total_expense');
        $elapsed = max(0, $n('elapsed_days'));
        $planned = max(1, $n('planned_duration_days'));
        $window30 = max(1, min(30, $elapsed + 1));
        $window7 = max(1, min(7, $elapsed + 1));
        $burn = ($n('direct_expense_amount_30d') + $n('valued_stock_out_cost_30d')) / $window30;
        $burn7 = ($n('direct_expense_amount_7d') + $n('valued_stock_out_cost_7d')) / $window7;

        return ['budget' => $budget, 'duration_months' => $n('duration_months'), 'worker_count' => $n('worker_count'),
            'elapsed_days' => $elapsed, 'remaining_planned_days' => max(0, $planned - $elapsed), 'elapsed_time_fraction' => $elapsed / $planned,
            'fin_total_expense' => $spent, 'budget_used_fraction' => $budget > 0 ? $spent / $budget : 0,
            'material_cost_share' => $spent > 0 ? $n('fin_material_expense') / $spent : 0, 'labor_cost_share' => $spent > 0 ? $n('fin_labor_expense') / $spent : 0,
            'equipment_cost_share' => $spent > 0 ? $n('fin_equipment_expense') / $spent : 0, 'cost_burn_rate_30d' => $burn,
            'burn_rate_acceleration' => $burn > 0 ? $burn7 / $burn : 0, 'inventory_cost_burn_rate_30d' => $n('valued_stock_out_cost_30d') / $window30,
            'expense_frequency_7d' => $n('direct_expense_count_7d') / $window7 * 7, 'stock_out_frequency_7d' => $n('stock_out_count_7d') / $window7 * 7];
    }
}
