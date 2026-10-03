<?php

namespace App\Services;

/** Identical formulas for observed training snapshots and current prediction inputs. */
class ProjectCostFeatureBuilder
{
    public const FEATURE_NAMES = [
        'progress_fraction', 'budget_used_fraction', 'cost_progress_gap', 'earned_value',
        'cost_performance_index', 'progress_eac_to_budget',
        'material_cost_share', 'labor_cost_share', 'equipment_cost_share',
        'cost_burn_rate_30d', 'burn_rate_acceleration',
        'valued_inventory_cost_to_budget', 'inventory_cost_burn_rate_30d',
        'expense_frequency_7d', 'stock_out_frequency_7d',
        'elapsed_time_fraction', 'schedule_progress_gap', 'days_past_planned_end',
        'time_eac_to_budget', 'progress_forecast_available', 'time_forecast_available',
        'recent_cost_available', 'snapshot_cost_coverage_complete',
        'remaining_work_fraction', 'remaining_budget_fraction',
        'required_cost_performance_index', 'required_cost_performance_available',
        'recent_burn_remaining_budget_days', 'budget_runway_available',
    ];

    public static function catalog(): array
    {
        return [
            'formula_version' => 2, 'candidate_feature_names' => self::FEATURE_NAMES,
            'minimum_progress_for_extrapolation_percent' => 10,
            'minimum_elapsed_days_for_time_forecast' => 7,
            'groups' => [
                'Progress and budget' => ['progress_fraction', 'budget_used_fraction', 'cost_progress_gap',
                    'earned_value', 'cost_performance_index', 'progress_eac_to_budget'],
                'Cost mix' => ['material_cost_share', 'labor_cost_share', 'equipment_cost_share'],
                'Recent cost burn' => ['cost_burn_rate_30d', 'burn_rate_acceleration'],
                'Inventory consumption cost' => ['valued_inventory_cost_to_budget', 'inventory_cost_burn_rate_30d'],
                'Transaction frequency' => ['expense_frequency_7d', 'stock_out_frequency_7d'],
                'Schedule and time forecast' => ['elapsed_time_fraction', 'schedule_progress_gap',
                    'days_past_planned_end', 'time_eac_to_budget'],
                'Input availability' => ['progress_forecast_available', 'time_forecast_available',
                    'recent_cost_available', 'snapshot_cost_coverage_complete'],
                'Remaining work and budget' => ['remaining_work_fraction', 'remaining_budget_fraction',
                    'required_cost_performance_index', 'required_cost_performance_available',
                    'recent_burn_remaining_budget_days', 'budget_runway_available'],
            ],
            'additional_source_requirements' => [
                'unpaid_commitments' => 'Dated purchase orders or contracts, remaining unpaid amounts, cancellation status, and links preventing double counting with recorded expenses.',
                'approved_variations' => 'Approval dates, approved scope and cost changes, and the budget version effective at each observation.',
                'worker_productivity' => 'Dated completed work quantities and labor hours for matching work types; worker count alone is insufficient.',
                'material_price_changes' => 'Historical invoice unit prices for matching item and unit, dated before the observation; current catalog prices alone are insufficient.',
            ],
            'missing_input_policy' => 'Unavailable model features use zero with explicit availability flags. Forecast indicators remain null when their required inputs are unavailable.',
            'inventory_units_policy' => 'Consumption uses valued withdrawal cost; quantities from different item units are never added as a model consumption measure.',
        ];
    }

    public function build(array $input): array
    {
        $number = static fn ($name) => isset($input[$name]) && is_numeric($input[$name])
            && is_finite((float) $input[$name]) && (float) $input[$name] >= 0 && (float) $input[$name] <= 999999999999.99
                ? (float) $input[$name] : null;
        $budget = round($number('budget') ?? 0, 2);
        $spent = round($number('fin_total_expense') ?? 0, 2);
        $progress = min(100, $number('completion_percentage') ?? 0) / 100;
        $elapsed = $number('elapsed_days');
        $planned = $number('planned_duration_days');
        $timing = $elapsed !== null && $planned !== null && $planned > 0;
        $complete = in_array($input['cost_coverage_complete'] ?? null, [true, 1, '1'], true);
        $window30 = $elapsed === null ? null : max(1, min(30, $elapsed + 1));
        $window7 = $elapsed === null ? null : max(1, min(7, $elapsed + 1));
        $recent = $window30 !== null && $number('direct_expense_amount_30d') !== null
            && $number('valued_stock_out_cost_30d') !== null;
        $rate30 = $recent ? ($number('direct_expense_amount_30d') + $number('valued_stock_out_cost_30d')) / $window30 : null;
        $rate7 = $window7 !== null && $number('direct_expense_amount_7d') !== null && $number('valued_stock_out_cost_7d') !== null
            ? ($number('direct_expense_amount_7d') + $number('valued_stock_out_cost_7d')) / $window7 : null;
        // Progress below 10% is too sensitive to startup costs for an extrapolated forecast.
        $progressAvailable = $complete && $budget > 0 && $progress >= 0.1 && $spent > 0;
        $progressEac = $progressAvailable ? $spent / $progress : null;
        $timeAvailable = $progressAvailable && $timing && $elapsed >= 7 && $rate30 !== null && $rate30 > 0;
        $remainingDays = $timeAvailable ? ($progress >= 1 ? 0 : max(max(0, $planned - $elapsed), $elapsed * (1 - $progress) / $progress)) : null;
        $timeEac = $timeAvailable ? $spent + $rate30 * $remainingDays : null;
        $elapsedFraction = $timing ? $elapsed / $planned : null;
        $remainingBudget = $budget - $spent;
        $requiredPerformanceAvailable = $complete && $budget > 0 && $remainingBudget > 0;
        $runwayAvailable = $complete && $budget > 0 && $rate30 !== null && $rate30 > 0;
        $values = [
            'progress_fraction' => $progress,
            'budget_used_fraction' => $budget > 0 ? $spent / $budget : 0,
            'cost_progress_gap' => $budget > 0 ? $spent / $budget - $progress : 0,
            'earned_value' => $budget * $progress,
            'cost_performance_index' => $progressAvailable ? $budget * $progress / $spent : 0,
            'progress_eac_to_budget' => $progressEac === null ? 0 : $progressEac / $budget,
            'material_cost_share' => $spent > 0 ? ($number('fin_material_expense') ?? 0) / $spent : 0,
            'labor_cost_share' => $spent > 0 ? ($number('fin_labor_expense') ?? 0) / $spent : 0,
            'equipment_cost_share' => $spent > 0 ? ($number('fin_equipment_expense') ?? 0) / $spent : 0,
            'cost_burn_rate_30d' => $rate30 ?? 0,
            'burn_rate_acceleration' => $rate30 !== null && $rate30 > 0 && $rate7 !== null ? $rate7 / $rate30 : 0,
            'valued_inventory_cost_to_budget' => $budget > 0 ? ($number('valued_stock_out_cost') ?? 0) / $budget : 0,
            'inventory_cost_burn_rate_30d' => $recent ? $number('valued_stock_out_cost_30d') / $window30 : 0,
            'expense_frequency_7d' => $window7 === null ? 0 : ($number('direct_expense_count_7d') ?? 0) / $window7 * 7,
            'stock_out_frequency_7d' => $window7 === null ? 0 : ($number('stock_out_count_7d') ?? 0) / $window7 * 7,
            'elapsed_time_fraction' => $elapsedFraction ?? 0,
            'schedule_progress_gap' => $elapsedFraction === null ? 0 : $progress - min(1, $elapsedFraction),
            'days_past_planned_end' => $timing ? max(0, $elapsed - $planned) : 0,
            'time_eac_to_budget' => $timeEac === null ? 0 : $timeEac / $budget,
            'progress_forecast_available' => (int) $progressAvailable,
            'time_forecast_available' => (int) $timeAvailable,
            'recent_cost_available' => (int) $recent,
            'snapshot_cost_coverage_complete' => (int) $complete,
            'remaining_work_fraction' => 1 - $progress,
            // Preserve negative headroom: overspending is useful evidence, not zero remaining budget.
            'remaining_budget_fraction' => $budget > 0 ? $remainingBudget / $budget : 0,
            'required_cost_performance_index' => $requiredPerformanceAvailable ? $budget * (1 - $progress) / $remainingBudget : 0,
            'required_cost_performance_available' => (int) $requiredPerformanceAvailable,
            'recent_burn_remaining_budget_days' => $runwayAvailable ? max(0, $remainingBudget) / $rate30 : 0,
            'budget_runway_available' => (int) $runwayAvailable,
        ];

        return [
            'values' => $values,
            'indicators' => [
                'progress_based_final_cost' => $progressEac === null ? null : round($progressEac, 2),
                'time_based_final_cost' => $timeEac === null ? null : round($timeEac, 2),
                'cost_burn_rate_30d' => $rate30 === null ? null : round($rate30, 2),
                'cost_burn_rate_7d' => $rate7 === null ? null : round($rate7, 2),
                'cost_coverage_complete' => $complete,
                'timing_available' => $timing,
                'required_cost_performance_index' => $requiredPerformanceAvailable ? $budget * (1 - $progress) / $remainingBudget : null,
                'remaining_budget' => $budget > 0 ? round($remainingBudget, 2) : null,
                'recent_burn_remaining_budget_days' => $runwayAvailable ? round(max(0, $remainingBudget) / $rate30, 2) : null,
                'note' => 'Formula indicators are candidate inputs, not a newly validated model prediction. Earned value assumes recorded completion represents the share of budgeted work achieved.',
            ],
        ];
    }
}
