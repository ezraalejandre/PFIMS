<?php

namespace Tests\Unit;

use App\Services\ML\ModelActivationPolicy;
use PHPUnit\Framework\TestCase;

class ModelActivationPolicyTest extends TestCase
{
    public function test_mae_ceiling_is_fifteen_percent_of_median_and_cannot_be_relaxed_by_candidate_evidence(): void
    {
        $policy = new ModelActivationPolicy;
        $metrics = ['mean_absolute_percentage_error' => 5, 'r_squared' => 0.9, 'mean_absolute_error' => 450000, 'target_median' => 3000000];
        $evidence = $this->evidence() + ['budget_baseline_mape' => 10, 'active_model_evaluation' => $metrics];
        $this->assertTrue($policy->assessCost($metrics, $evidence)['eligible']);
        $this->assertFalse($policy->assessCost(array_replace($metrics, ['mean_absolute_error' => 450000.01]),
            $evidence + ['mae_tolerance' => 1000000])['checks']['agreed_mae_tolerance']);
        $target = $policy->maeTarget($metrics);
        $this->assertSame(15.0, $target['actual_percent']);
        $this->assertSame(300000.0, $target['stretch_pesos']);
        $this->assertFalse($policy->assessCost(array_replace($metrics, ['target_median' => null]), $evidence)['eligible']);
        $this->assertFalse($policy->assessCost(array_replace($metrics, ['target_median' => 0]), $evidence)['eligible']);
    }

    private function evidence(): array
    {
        return ['verified_genuine_source_records' => true, 'fresh_independent_holdout' => true,
            'training_project_ids' => [1, 2], 'holdout_project_ids' => [3, 4],
            'training_only_tuning' => true, 'same_holdout_active_comparison' => true,
            'project_level_primary_evaluation' => true];
    }

    public function test_ideal_metrics_without_verified_evidence_cannot_pass(): void
    {
        $result = (new ModelActivationPolicy)->assessDetector(['classification_accuracy' => 99,
            'precision' => 99, 'recall' => 99, 'f1_score' => 99, 'balanced_accuracy' => 99,
            'actual_overruns' => 5, 'actual_non_overruns' => 5]);
        $this->assertFalse($result['eligible']);
        $this->assertContains('fresh_independent_holdout', $result['failed_requirements']);
        $this->assertContains('verified_genuine_source_records', $result['failed_requirements']);
        $this->assertFalse($result['activation_performed']);
    }

    public function test_detector_requires_balanced_detection_and_non_regression_against_active(): void
    {
        $metrics = ['classification_accuracy' => 80, 'precision' => 75, 'recall' => 80,
            'f1_score' => 80, 'balanced_accuracy' => 80, 'actual_overruns' => 5, 'actual_non_overruns' => 5];
        $evidence = $this->evidence() + ['active_model_evaluation' => $metrics];
        $policy = new ModelActivationPolicy;
        $this->assertTrue($policy->assessDetector($metrics, $evidence)['eligible']);
        foreach (['classification_accuracy', 'precision', 'recall', 'f1_score', 'balanced_accuracy'] as $name) {
            $this->assertFalse($policy->assessDetector(array_replace($metrics, [$name => $metrics[$name] - 0.01]), $evidence)['eligible']);
        }
        $this->assertFalse($policy->assessDetector($metrics, array_replace($evidence, ['holdout_project_ids' => [2, 3]]))['eligible']);
        $this->assertFalse($policy->assessDetector(array_replace($metrics, ['actual_non_overruns' => 0]), $evidence)['eligible']);
    }

    public function test_cost_requires_agreed_mae_baseline_improvement_and_current_model_comparison(): void
    {
        $metrics = ['mean_absolute_percentage_error' => 10, 'r_squared' => 0.8, 'mean_absolute_error' => 1000, 'target_median' => 20000];
        $evidence = $this->evidence() + ['mae_tolerance' => 1000, 'budget_baseline_mape' => 12,
            'active_model_evaluation' => $metrics];
        $policy = new ModelActivationPolicy;
        $this->assertTrue($policy->assessCost($metrics, $evidence)['eligible']);
        foreach (['mae_tolerance' => null, 'budget_baseline_mape' => 11.99, 'active_model_evaluation' => []] as $name => $value) {
            $this->assertFalse($policy->assessCost($metrics, array_replace($evidence, [$name => $value]))['eligible']);
        }
        $this->assertFalse($policy->assessCost(array_replace($metrics, ['mean_absolute_error' => INF]), $evidence)['eligible']);
    }
}
