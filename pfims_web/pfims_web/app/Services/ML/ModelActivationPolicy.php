<?php

namespace App\Services\ML;

/** Separate promotion requirements; evaluating this policy never writes model artifacts. */
class ModelActivationPolicy
{
    public function assessCost(array $metrics, array $evidence = []): array
    {
        $checks = $this->evidenceChecks($evidence);
        $checks['mape_at_most_10_percent'] = $this->atMost($metrics['mean_absolute_percentage_error'] ?? null, 10);
        $checks['r_squared_at_least_0_8'] = $this->atLeast($metrics['r_squared'] ?? null, 0.8);
        $checks['agreed_mae_tolerance'] = $this->finite($evidence['mae_tolerance'] ?? null)
            && $evidence['mae_tolerance'] > 0
            && $this->atMost($metrics['mean_absolute_error'] ?? null, $evidence['mae_tolerance']);
        $baseline = $evidence['budget_baseline_mape'] ?? null;
        $checks['budget_baseline_improvement_at_least_2_points'] = $this->finite($baseline)
            && $this->finite($metrics['mean_absolute_percentage_error'] ?? null)
            && $baseline - $metrics['mean_absolute_percentage_error'] >= 2;
        $active = $evidence['active_model_evaluation'] ?? [];
        $checks['no_worse_than_active_mape'] = $this->finite($active['mean_absolute_percentage_error'] ?? null)
            && $this->atMost($metrics['mean_absolute_percentage_error'] ?? null, $active['mean_absolute_percentage_error']);
        $checks['no_worse_than_active_mae'] = $this->finite($active['mean_absolute_error'] ?? null)
            && $this->atMost($metrics['mean_absolute_error'] ?? null, $active['mean_absolute_error']);

        return $this->result('cost_regression', $checks, [
            'minimum' => ['mape_max' => 10, 'r_squared_min' => 0.8, 'baseline_mape_improvement_points' => 2],
            'ideal' => ['mape_max' => 5, 'r_squared_min' => 0.9],
            'mae' => 'Requires an explicitly agreed currency tolerance; no universal peso threshold is assumed.',
        ]);
    }

    public function assessDetector(array $metrics, array $evidence = []): array
    {
        $checks = $this->evidenceChecks($evidence);
        $minimum = ['classification_accuracy' => 80, 'precision' => 75, 'recall' => 80,
            'f1_score' => 80, 'balanced_accuracy' => 80];
        foreach ($minimum as $name => $limit) {
            $checks[$name.'_minimum'] = $this->atLeast($metrics[$name] ?? null, $limit);
        }
        $checks['both_outcome_classes_present'] = ($metrics['actual_overruns'] ?? 0) > 0
            && ($metrics['actual_non_overruns'] ?? 0) > 0;
        $active = $evidence['active_model_evaluation'] ?? [];
        foreach (['f1_score', 'balanced_accuracy', 'recall'] as $name) {
            $checks['no_worse_than_active_'.$name] = $this->finite($active[$name] ?? null)
                && $this->atLeast($metrics[$name] ?? null, $active[$name]);
        }

        return $this->result('any_overrun_detector', $checks, [
            'minimum' => $minimum,
            'ideal' => ['classification_accuracy' => 90, 'precision' => 85, 'recall' => 90,
                'f1_score' => 85, 'balanced_accuracy' => 85],
        ]);
    }

    private function evidenceChecks(array $evidence): array
    {
        $training = $evidence['training_project_ids'] ?? [];
        $holdout = $evidence['holdout_project_ids'] ?? [];

        return [
            'verified_genuine_source_records' => ($evidence['verified_genuine_source_records'] ?? false) === true,
            'fresh_independent_holdout' => ($evidence['fresh_independent_holdout'] ?? false) === true,
            'training_and_holdout_disjoint' => $training !== [] && $holdout !== [] && array_intersect($training, $holdout) === [],
            'training_only_tuning' => ($evidence['training_only_tuning'] ?? false) === true,
            'same_holdout_active_comparison' => ($evidence['same_holdout_active_comparison'] ?? false) === true,
            'project_level_primary_evaluation' => ($evidence['project_level_primary_evaluation'] ?? false) === true,
        ];
    }

    private function result(string $kind, array $checks, array $targets): array
    {
        $failed = array_keys(array_filter($checks, fn ($passed) => ! $passed));

        return ['model_kind' => $kind, 'eligible' => $failed === [], 'checks' => $checks,
            'failed_requirements' => $failed, 'targets' => $targets,
            'policy_note' => 'Minimums are project activation requirements, not universal guarantees. Ideal targets do not override evidence checks.',
            'activation_performed' => false];
    }

    private function finite(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value);
    }

    private function atMost(mixed $value, float $limit): bool
    {
        return $this->finite($value) && (float) $value >= 0 && (float) $value <= $limit;
    }

    private function atLeast(mixed $value, float $limit): bool
    {
        return $this->finite($value) && (float) $value >= $limit;
    }
}
