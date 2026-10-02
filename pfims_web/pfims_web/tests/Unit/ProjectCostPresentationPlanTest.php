<?php

namespace Tests\Unit;

use App\Services\ProjectCostPresentationPlan;
use App\Services\ProjectOverrunPolicy;
use PHPUnit\Framework\TestCase;

class ProjectCostPresentationPlanTest extends TestCase
{
    public static function inputs(): array
    {
        return [[['project_name' => 'Residential Building - Lemery, 2023-001', 'client_name' => 'Site Client',
            'project_manager' => 'Project Manager', 'start_date' => '2023-01-01', 'estimated_end_date' => '2023-06-01',
            'worker_count' => 20, 'budget_amount' => 3000000]],
            [['item_id' => 1, 'item_name' => 'Cement', 'unit_price' => 285],
                ['item_id' => 2, 'item_name' => 'Steel', 'unit_price' => 410]]];
    }

    public function test_plans_have_balanced_outcomes_realistic_chronology_and_exact_cost_labels(): void
    {
        [$references, $items] = self::inputs();
        $plan = (new ProjectCostPresentationPlan)->build($references, $items);
        $this->assertCount(48, $plan['projects']);
        $counts = ['material' => 0, 'small' => 0, 'within' => 0];
        foreach ($plan['projects'] as $scenario) {
            $counts[$scenario['expected_outcome']]++;
            $outcome = (new ProjectOverrunPolicy)->classify($scenario['final_cost'], $scenario['budget_amount']);
            $this->assertSame($scenario['expected_outcome'] === 'material', $outcome['material_overrun']);
            $this->assertSame($scenario['expected_outcome'] !== 'within', $outcome['any_overrun']);
            $sum = 0;
            $lastDate = $scenario['project']['start_date'];
            foreach ($scenario['waves'] as $wave) {
                $this->assertGreaterThanOrEqual($lastDate, $wave['date']);
                $this->assertLessThanOrEqual($wave['date'], $wave['receipt_date']);
                $this->assertGreaterThanOrEqual($scenario['project']['start_date'], $wave['receipt_date']);
                $this->assertEqualsWithDelta($wave['quantity'] * $wave['unit_cost'], $wave['inventory_cost'], 0.01);
                $this->assertGreaterThan(0, min($wave['direct']));
                $sum += array_sum($wave['direct']) + $wave['inventory_cost'];
                $lastDate = $wave['date'];
            }
            $this->assertEqualsWithDelta($scenario['final_cost'], $sum, 0.01);
            foreach ($scenario['snapshots'] as $snapshot) {
                $observed = array_filter($scenario['waves'], fn ($wave) => $wave['date'] <= $snapshot['finance_as_of_date']);
                $expected = array_sum(array_map(fn ($wave) => array_sum($wave['direct']) + $wave['inventory_cost'], $observed));
                $this->assertEqualsWithDelta($expected, $snapshot['cumulative_total_expense'], 0.01);
                $this->assertLessThan($scenario['final_cost'], $snapshot['cumulative_total_expense']);
                $this->assertSame('company_inspired_sample', $snapshot['data_source']);
            }
        }
        $this->assertSame(['material' => 24, 'small' => 8, 'within' => 16], $counts);
        $this->assertCount(48, array_unique(array_column(array_column($plan['projects'], 'project'), 'project_name')));
        $this->assertSame($plan, (new ProjectCostPresentationPlan)->build($references, $items));
    }
}
