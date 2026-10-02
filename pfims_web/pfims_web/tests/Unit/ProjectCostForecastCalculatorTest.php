<?php

namespace Tests\Unit;

use App\Services\ProjectCostForecastCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProjectCostForecastCalculatorTest extends TestCase
{
    public function test_zero_remaining_cost_is_valid_and_negative_remaining_cost_is_disclosed(): void
    {
        $calculator = new ProjectCostForecastCalculator;
        $zero = $calculator->calculate(0, 1200, true);
        $this->assertSame(1200.0, $zero['final_cost']);
        $this->assertFalse($zero['spend_floor_applied']);
        $negative = $calculator->calculate(-300, 1200, true);
        $this->assertSame(900.0, $negative['raw_final_cost']);
        $this->assertSame(1200.0, $negative['final_cost']);
        $this->assertTrue($negative['spend_floor_applied']);
    }

    public function test_final_and_remaining_cost_targets_have_the_same_spend_floor_and_no_budget_ceiling(): void
    {
        $calculator = new ProjectCostForecastCalculator;
        $remaining = $calculator->calculate(500, 1200, true);
        $final = $calculator->calculate(1700, 1200, false);
        $this->assertSame($final['final_cost'], $remaining['final_cost']);
        $this->assertSame(1700.0, $remaining['final_cost']);
        $this->assertSame(1200.0, $calculator->calculate(400, 1200, false)['final_cost']);
    }

    public function test_non_finite_estimates_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        (new ProjectCostForecastCalculator)->calculate(INF, 1200, true);
    }
}
