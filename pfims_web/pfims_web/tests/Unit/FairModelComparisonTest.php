<?php

namespace Tests\Unit;

use App\Services\MLService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FairModelComparisonTest extends TestCase
{
    public function test_comparison_preserves_shared_projects_costs_and_nonactivation(): void
    {
        $service = new class extends MLService
        {
            public function __construct() {}

            public function run($train, $test)
            {
                return $this->fairComparison($train, $test, ['budget', 'duration_months']);
            }

            public function getCandidateEvaluationReports(): ?array
            {
                return ['reports' => [['holdout_project_ids' => [20]]]];
            }

            protected function compareRegressionModels(Collection $trainingData, Collection $testData, array $featureNames): array
            {
                return ['models' => ['least_squares_linear_regression' => ['evaluation' => ['mean_absolute_percentage_error' => 2]], 'support_vector_regression_rbf' => ['status' => 'unavailable', 'evaluation' => null]]];
            }
        };
        $train = collect(range(1, 12))->map(fn ($id) => (object) ['project_id' => $id, 'budget' => 1000 + $id * 100, 'duration_months' => 1 + $id % 4, 'actual_cost' => 900 + $id * 110]);
        $test = collect([(object) ['project_id' => 20, 'budget' => 3000, 'duration_months' => 3, 'actual_cost' => 3300], (object) ['project_id' => 21, 'budget' => 4000, 'duration_months' => 4, 'actual_cost' => 3800]]);
        $before = serialize($test);
        $report = $service->run($train, $test);
        $this->assertSame($before, serialize($test));
        $this->assertSame([20, 21], $report['holdout_project_ids']);
        $this->assertSame([20], $report['previously_evaluated_holdout_project_ids']);
        $this->assertFalse($report['activation_evidence']);
        $this->assertSame('any_overrun', $report['primary_overrun_definition']);
        $this->assertSame(2, $report['models']['refitted_planning']['evaluation']['evaluation_observations']);
        $this->assertSame(2, $report['models']['recorded_budget']['evaluation']['evaluation_observations']);
    }
}
