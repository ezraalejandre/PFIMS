<?php

namespace App\Services\ML;

use InvalidArgumentException;
use RuntimeException;

/** Deterministic L2 logistic classifier, balanced by project and outcome class. */
class BudgetOverrunClassifier
{
    private array $ranges = [];

    private array $coefficients = [];

    public function train(array $samples, array $labels, array $projects): void
    {
        if (count($samples) !== count($labels) || count($labels) !== count($projects) || count($samples) < 4) {
            throw new InvalidArgumentException('Classifier training requires aligned samples, outcomes, and projects.');
        }
        $width = count($samples[0]);
        $counts = [0, 0];
        foreach ($samples as $i => $sample) {
            if (! is_bool($labels[$i]) || count($sample) !== $width || $width < 1) {
                throw new InvalidArgumentException('Classifier outcomes must be Boolean and features must align.');
            }
            $this->validateFeatures($sample);
            $counts[(int) $labels[$i]]++;
        }
        if (min($counts) < 2) {
            throw new RuntimeException('Classifier training needs at least two observations of each outcome class.');
        }
        $this->ranges = [];
        for ($j = 0; $j < $width; $j++) {
            $values = array_column($samples, $j);
            $this->ranges[] = ['min' => (float) min($values), 'max' => (float) max($values)];
        }
        $projectCounts = array_count_values(array_map('strval', $projects));
        $classWeights = [0.0, 0.0];
        $weights = [];
        foreach ($projects as $i => $project) {
            $weights[$i] = 1 / $projectCounts[(string) $project];
            $classWeights[(int) $labels[$i]] += $weights[$i];
        }
        foreach ($weights as $i => $weight) {
            $weights[$i] = $weight / (2 * $classWeights[(int) $labels[$i]]);
        }
        $vectors = array_map(fn ($sample) => [1.0, ...$this->transform($sample)], $samples);
        $this->coefficients = array_fill(0, $width + 1, 0.0);
        // Fixed settings declared before evaluation; threshold tuning is a later step.
        for ($epoch = 0; $epoch < 1000; $epoch++) {
            $gradient = array_fill(0, $width + 1, 0.0);
            foreach ($vectors as $i => $vector) {
                $error = ($this->sigmoid($this->dot($vector)) - (int) $labels[$i]) * $weights[$i];
                foreach ($vector as $j => $value) {
                    $gradient[$j] += $error * $value;
                }
            }
            foreach ($this->coefficients as $j => $value) {
                $this->coefficients[$j] -= 0.2 * ($gradient[$j] + ($j === 0 ? 0 : 0.01 * $value));
            }
        }
    }

    public function score(array $features): float
    {
        if ($this->coefficients === [] || count($features) !== count($this->ranges)) {
            throw new InvalidArgumentException('Classifier features do not match a trained model.');
        }
        $this->validateFeatures($features);

        return $this->sigmoid($this->dot([1.0, ...$this->transform($features)]));
    }

    public function modelData(): array
    {
        return ['algorithm' => 'project_balanced_logistic_regression', 'ranges' => $this->ranges,
            'coefficients' => $this->coefficients, 'threshold' => 0.5,
            'scaling' => 'training_only_min_max', 'l2_penalty' => 0.01, 'epochs' => 1000,
            'score_note' => 'Class-balanced model score; not a calibrated probability.'];
    }

    private function transform(array $features): array
    {
        return array_map(function ($j) use ($features) {
            $range = $this->ranges[$j];
            $span = $range['max'] - $range['min'];

            return $span > 0 ? ($features[$j] - $range['min']) / $span : 0.0;
        }, array_keys($this->ranges));
    }

    private function dot(array $vector): float
    {
        $result = 0.0;
        foreach ($vector as $j => $value) {
            $result += $value * $this->coefficients[$j];
        }

        return $result;
    }

    private function sigmoid(float $value): float
    {
        return $value >= 0 ? 1 / (1 + exp(-$value)) : exp($value) / (1 + exp($value));
    }

    private function validateFeatures(array $features): void
    {
        foreach ($features as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Classifier features must be finite numbers.');
            }
        }
    }
}
