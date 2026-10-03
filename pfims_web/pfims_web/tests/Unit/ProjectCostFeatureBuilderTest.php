<?php

namespace Tests\Unit;

use App\Services\ProjectCostFeatureBuilder;
use PHPUnit\Framework\TestCase;

class ProjectCostFeatureBuilderTest extends TestCase
{
    public function test_remaining_work_features_preserve_overspending_and_gate_missing_costs(): void
    {
        $builder = new ProjectCostFeatureBuilder;
        $result = $builder->build($this->inputs());
        $this->assertSame(0.5, $result['values']['remaining_work_fraction']);
        $this->assertSame(0.4, $result['values']['remaining_budget_fraction']);
        $this->assertSame(1.25, $result['values']['required_cost_performance_index']);
        $this->assertSame(20.0, $result['indicators']['recent_burn_remaining_budget_days']);
        foreach ([1000, 1000.01] as $spent) {
            $over = $builder->build(array_replace($this->inputs(), ['fin_total_expense' => $spent]));
            $this->assertNull($over['indicators']['required_cost_performance_index']);
            $this->assertSame(0, $over['values']['required_cost_performance_available']);
            $this->assertSame(0.0, $over['indicators']['recent_burn_remaining_budget_days']);
            $this->assertLessThanOrEqual(0, $over['values']['remaining_budget_fraction']);
        }
        $missing = $builder->build(array_replace($this->inputs(), ['cost_coverage_complete' => false]));
        $this->assertNull($missing['indicators']['required_cost_performance_index']);
        $this->assertNull($missing['indicators']['recent_burn_remaining_budget_days']);
        $this->assertSame($result, $builder->build($this->inputs() + ['final_actual_cost' => 999999]));
    }

    private function inputs(): array
    {
        return ['budget' => 1000, 'fin_total_expense' => 600, 'completion_percentage' => 50,
            'fin_material_expense' => 300, 'fin_labor_expense' => 200, 'fin_equipment_expense' => 100,
            'planned_duration_days' => 60, 'elapsed_days' => 30, 'cost_coverage_complete' => true,
            'direct_expense_amount_30d' => 300, 'valued_stock_out_cost_30d' => 300,
            'direct_expense_amount_7d' => 140, 'valued_stock_out_cost_7d' => 70,
            'direct_expense_count_7d' => 2, 'stock_out_count_7d' => 3, 'valued_stock_out_cost' => 300];
    }

    public function test_completed_work_does_not_forecast_additional_spending_before_the_planned_end(): void
    {
        $inputs = array_replace($this->inputs(), ['completion_percentage' => 100]);
        $indicators = (new ProjectCostFeatureBuilder)->build($inputs)['indicators'];
        $this->assertSame(600.0, $indicators['progress_based_final_cost']);
        $this->assertSame(600.0, $indicators['time_based_final_cost']);
    }

    public function test_progress_and_time_indicators_can_exceed_budget_without_using_the_final_outcome(): void
    {
        $builder = new ProjectCostFeatureBuilder;
        $result = $builder->build($this->inputs());
        $this->assertSame(1200.0, $result['indicators']['progress_based_final_cost']);
        $this->assertSame(1200.0, $result['indicators']['time_based_final_cost']);
        $this->assertSame(20.0, $result['values']['cost_burn_rate_30d']);
        $this->assertSame(1.5, $result['values']['burn_rate_acceleration']);
        $this->assertEqualsWithDelta(500 / 600, $result['values']['cost_performance_index'], 0.000001);
        $this->assertSame(0.5, $result['values']['material_cost_share']);
        $this->assertSame(0.3, $result['values']['valued_inventory_cost_to_budget']);
        $this->assertSame(10.0, $result['values']['inventory_cost_burn_rate_30d']);
        $this->assertSame(2.0, $result['values']['expense_frequency_7d']);
        $this->assertSame($result, $builder->build($this->inputs() + [
            'actual_cost' => 900000, 'final_actual_cost' => 1, 'reconciled_final_cost' => 700000,
            'stock_out_quantity_30d' => 999999, // Mixed-unit quantities do not measure consumption cost.
        ]));
        $this->assertSame(ProjectCostFeatureBuilder::FEATURE_NAMES, array_keys($result['values']));
    }

    public function test_unknown_or_incomplete_inputs_keep_forecasts_unavailable_and_all_features_finite(): void
    {
        $builder = new ProjectCostFeatureBuilder;
        foreach ([[], ['budget' => 0.0000000001], ['cost_coverage_complete' => false],
            ['elapsed_days' => null], ['direct_expense_amount_30d' => null], ['fin_total_expense' => INF]] as $overrides) {
            $input = $overrides === [] ? [] : array_replace($this->inputs(), $overrides);
            $result = $builder->build($input);
            $this->assertNull($result['indicators']['time_based_final_cost']);
            foreach ($result['values'] as $value) {
                $this->assertTrue(is_finite((float) $value));
            }
        }
        $missing = $builder->build(['budget' => 1000, 'fin_total_expense' => 600, 'completion_percentage' => 50]);
        $this->assertNull($missing['indicators']['progress_based_final_cost']);
        $this->assertSame(0, $missing['values']['progress_forecast_available']);
        $this->assertSame(0, $missing['values']['recent_cost_available']);
    }

    public function test_startup_zero_activity_and_overdue_work_have_explicit_forecast_gates(): void
    {
        $builder = new ProjectCostFeatureBuilder;
        $this->assertNull($builder->build(array_replace($this->inputs(), ['completion_percentage' => 5]))['indicators']['progress_based_final_cost']);
        $this->assertSame(6000.0, $builder->build(array_replace($this->inputs(), ['completion_percentage' => 10]))['indicators']['progress_based_final_cost']);
        $short = $builder->build(array_replace($this->inputs(), ['elapsed_days' => 2]));
        $this->assertNull($short['indicators']['time_based_final_cost']);
        $this->assertSame(200.0, $short['values']['cost_burn_rate_30d']); // Three observed calendar days.
        $zero = $builder->build(array_replace($this->inputs(), ['direct_expense_amount_30d' => 0, 'valued_stock_out_cost_30d' => 0]));
        $this->assertNull($zero['indicators']['time_based_final_cost']);
        $overdue = $builder->build(array_replace($this->inputs(), ['elapsed_days' => 90]));
        $this->assertSame(30.0, $overdue['values']['days_past_planned_end']);
        $this->assertSame(2400.0, $overdue['indicators']['time_based_final_cost']); // Remaining work still has cost after the deadline.
    }

    public function test_cost_and_schedule_gaps_preserve_their_direction(): void
    {
        $values = (new ProjectCostFeatureBuilder)->build(array_replace($this->inputs(), [
            'fin_total_expense' => 400, 'elapsed_days' => 45,
        ]))['values'];
        $this->assertEqualsWithDelta(-0.1, $values['cost_progress_gap'], 0.000001);
        $this->assertSame(-0.25, $values['schedule_progress_gap']);
    }
}
