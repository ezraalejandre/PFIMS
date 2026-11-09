<?php

namespace App\Services;

use InvalidArgumentException;

// Group the project overrun evaluation actions.
class ProjectOverrunEvaluation
{
    // Get the evaluate.
    public function evaluate(array $predictions, array $actuals, array $budgets, string $definition): array
    {
        // Stop if the definition and predictions checks match.
        if (! in_array($definition, ['any_overrun', 'material_overrun'], true)
            || count($predictions) !== count($actuals) || count($actuals) !== count($budgets)) {
            throw new InvalidArgumentException('Overrun evaluation requires aligned observations and a supported definition.');
        }
        $tp = $fp = $tn = $fn = $excluded = 0;
        $policy = new ProjectOverrunPolicy;
        $actuals = array_values($actuals);
        $budgets = array_values($budgets);
        // Process each prediction.
        foreach (array_values($predictions) as $index => $prediction) {
            $actual = $policy->classify((float) $actuals[$index], $budgets[$index])[$definition];
            $predicted = $policy->classify((float) $prediction, $budgets[$index])[$definition];
            // Run this step if the actual and predicted checks match.
            if ($actual === null || $predicted === null) {
                $excluded++;
            } elseif ($actual && $predicted) {
                $tp++;
            } elseif ($predicted) {
                $fp++;
            } elseif ($actual) {
                $fn++;
            } else {
                $tn++;
            }
        }
        $count = $tp + $fp + $tn + $fn;

        return $this->fromCounts($tp, $fp, $tn, $fn, $excluded, $definition);
    }

    // Check classes.
    public function evaluateClasses(array $predictions, array $actuals): array
    {
        // Stop if the number of predictions does not equal actuals.
        if (count($predictions) !== count($actuals)) {
            throw new InvalidArgumentException('Classifier evaluation requires aligned outcomes.');
        }
        $tp = $fp = $tn = $fn = $excluded = 0;
        $actuals = array_values($actuals);
        // Process each prediction.
        foreach (array_values($predictions) as $i => $prediction) {
            $actual = $actuals[$i];
            // Stop if the prediction and actual checks match.
            if (($prediction !== null && ! is_bool($prediction)) || ($actual !== null && ! is_bool($actual))) {
                throw new InvalidArgumentException('Classifier outcomes must be Boolean or unavailable.');
            }
            // Run this step if the prediction and actual checks match.
            if ($prediction === null || $actual === null) {
                $excluded++;
            } elseif ($prediction && $actual) {
                $tp++;
            } elseif ($prediction) {
                $fp++;
            } elseif ($actual) {
                $fn++;
            } else {
                $tn++;
            }
        }

        return $this->fromCounts($tp, $fp, $tn, $fn, $excluded, 'any_overrun');
    }

    // Get the from counts.
    private function fromCounts(int $tp, int $fp, int $tn, int $fn, int $excluded, string $definition): array
    {
        $count = $tp + $fp + $tn + $fn;
        $positives = $tp + $fn;
        $negatives = $tn + $fp;
        $percent = fn (int $numerator, int $denominator) => $denominator > 0 ? round(100 * $numerator / $denominator, 2) : null;

        return [
            'definition' => $definition,
            'threshold_percent' => $definition === 'any_overrun' ? 0 : 5,
            'strictly_exceeds_threshold' => true,
            'evaluated_observations' => $count,
            'excluded_observations' => $excluded,
            'actual_overruns' => $positives,
            'actual_non_overruns' => $negatives,
            'predicted_overruns' => $tp + $fp,
            'precision' => $percent($tp, $tp + $fp),
            'recall' => $percent($tp, $positives),
            // All missed overruns yield F1 = 0 even when precision has no denominator.
            'f1_score' => $positives > 0 ? $percent(2 * $tp, 2 * $tp + $fp + $fn) : null,
            'classification_accuracy' => $percent($tp + $tn, $count),
            'balanced_accuracy' => $positives > 0 && $negatives > 0
                ? round(50 * ($tp / $positives + $tn / $negatives), 2) : null,
            'classification_counts' => compact('tp', 'fp', 'tn', 'fn'),
            'evidence_status' => $count === 0 ? 'no_valid_observations'
                : ($positives === 0 ? 'no_actual_overruns' : ($negatives === 0 ? 'no_non_overruns' : 'both_classes_present')),
            'score_units' => 'percent',
            'note' => 'Recall and F1 need actual overruns. Precision needs predicted overruns. Accuracy alone can hide missed overruns; small counts remain weak evidence.',
        ];
    }
}
