<?php

namespace Tests\Unit;

use App\Services\ProjectOverrunPolicy;
use PHPUnit\Framework\TestCase;

class ProjectOverrunPolicyTest extends TestCase
{
    public function test_exact_budget_and_five_percent_boundaries_use_currency_precision(): void
    {
        $policy = new ProjectOverrunPolicy;
        $this->assertFalse($policy->classify(1000, 1000)['any_overrun']);
        $this->assertTrue($policy->classify(1000.01, 1000)['any_overrun']);
        $this->assertFalse($policy->classify(1050, 1000)['material_overrun']);
        $this->assertTrue($policy->classify(1050.01, 1000)['material_overrun']);
        $this->assertFalse($policy->classify(1050.004, 1000)['material_overrun']);
    }

    public function test_missing_invalid_or_zero_budgets_do_not_produce_an_on_track_claim(): void
    {
        foreach ([null, 0, -1, INF, NAN] as $budget) {
            $this->assertNull((new ProjectOverrunPolicy)->classify(1000, $budget)['any_overrun']);
        }
        $this->assertNull((new ProjectOverrunPolicy)->classify(INF, 1000)['material_overrun']);
        $this->assertNull((new ProjectOverrunPolicy)->classify(-1, 1000)['material_overrun']);
    }
}
