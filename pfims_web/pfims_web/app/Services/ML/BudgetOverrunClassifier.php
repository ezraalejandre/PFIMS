<?php

namespace App\Services\ML;

use InvalidArgumentException;
use RuntimeException;

/** Predict budget overrun risk. Balance the training data by project and result. */
class BudgetOverrunClassifier
{
    // Get the from model data.
    public static function fromModelData(array $data): self
    {
        // Reject invalid stored detector dimensions or algorithm.
        if (($data['algorithm'] ?? null) !== 'project_balanced_logistic_regression'
            || empty($data['ranges']) || count($data['coefficients'] ?? []) !== count($data['ranges']) + 1) {
            throw new InvalidArgumentException('Invalid stored detector dimensions or algorithm.');
        }
        $model = new self;
        $model->validateFeatures($data['coefficients']);
        // Process each range.
        foreach ($data['ranges'] as $range) {
            $model->validateFeatures([$range['min'] ?? null, $range['max'] ?? null]);
            // Reject invalid detector feature range.
            if ($range['min'] > $range['max']) {
                throw new InvalidArgumentException('Invalid detector feature range.');
            }
        }
        $model->ranges = $data['ranges'];
        $model->coefficients = $data['coefficients'];

        return $model;
    }

    private array $ranges = [];

    private array $coefficients = [];

    // Build the prediction model from the training data.
    public function train(array $samples, array $labels, array $projects): void
    {
        // Stop if the samples and labels checks match.
        if (count($samples) !== count($labels) || count($labels) !== count($projects) || count($samples) < 4) {
            throw new InvalidArgumentException('Classifier training requires aligned samples, outcomes, and projects.');
        }
        $width = count($samples[0]);
        $counts = [0, 0];
        // Process each sample.
        foreach ($samples as $i => $sample) {
            // Stop if the i and sample checks match.
            if (! is_bool($labels[$i]) || count($sample) !== $width || $width < 1) {
                throw new InvalidArgumentException('Classifier outcomes must be Boolean and features must align.');
            }
            $this->validateFeatures($sample);
            $counts[(int) $labels[$i]]++;
        }
        // Stop if the counts is below 2.
        if (min($counts) < 2) {
            throw new RuntimeException('Classifier training needs at least two observations of each outcome class.');
        }
        $this->ranges = [];
        // Repeat for each step in this range.
        for ($j = 0; $j < $width; $j++) {
            $values = array_column($samples, $j);
            $this->ranges[] = ['min' => (float) min($values), 'max' => (float) max($values)];
        }
        $projectCounts = array_count_values(array_map('strval', $projects));
        $classWeights = [0.0, 0.0];
        $weights = [];
        // Process each project.
        foreach ($projects as $i => $project) {
            $weights[$i] = 1 / $projectCounts[(string) $project];
            $classWeights[(int) $labels[$i]] += $weights[$i];
        }
        // Process each weight.
        foreach ($weights as $i => $weight) {
            $weights[$i] = $weight / (2 * $classWeights[(int) $labels[$i]]);
        }
        $vectors = array_map(fn ($sample) => [1.0, ...$this->transform($sample)], $samples);
        $this->coefficients = array_fill(0, $width + 1, 0.0);
        // Fixed settings declared before evaluation; threshold tuning is a later step.
        for ($epoch = 0; $epoch < 1000; $epoch++) {
            $gradient = array_fill(0, $width + 1, 0.0);
            // Process each vector.
            foreach ($vectors as $i => $vector) {
                $error = ($this->sigmoid($this->dot($vector)) - (int) $labels[$i]) * $weights[$i];
                // Process each value.
                foreach ($vector as $j => $value) {
                    $gradient[$j] += $error * $value;
                }
            }
            // Process each value.
            foreach ($this->coefficients as $j => $value) {
                $this->coefficients[$j] -= 0.2 * ($gradient[$j] + ($j === 0 ? 0 : 0.01 * $value));
            }
        }
    }

    // Get the score.
    public function score(array $features): float
    {
        // Stop if the features checks match.
        if ($this->coefficients === [] || count($features) !== count($this->ranges)) {
            throw new InvalidArgumentException('Classifier features do not match a trained model.');
        }
        $this->validateFeatures($features);

        return $this->sigmoid($this->dot([1.0, ...$this->transform($features)]));
    }

    // Get the model data.
    public function modelData(): array
    {
        return ['algorithm' => 'project_balanced_logistic_regression', 'ranges' => $this->ranges,
            'coefficients' => $this->coefficients, 'threshold' => 0.5,
            'scaling' => 'training_only_min_max', 'l2_penalty' => 0.01, 'epochs' => 1000,
            'score_note' => 'Class-balanced model score; not a calibrated probability.'];
    }

    // Get the transform.
    private function transform(array $features): array
    {
        // Build a result for each item.
        return array_map(function ($j) use ($features) {
            $range = $this->ranges[$j];
            $span = $range['max'] - $range['min'];

            return $span > 0 ? ($features[$j] - $range['min']) / $span : 0.0;
        }, array_keys($this->ranges));
    }

    // Get the dot.
    private function dot(array $vector): float
    {
        $result = 0.0;
        // Process each value.
        foreach ($vector as $j => $value) {
            $result += $value * $this->coefficients[$j];
        }

        return $result;
    }

    // Get the sigmoid.
    private function sigmoid(float $value): float
    {
        return $value >= 0 ? 1 / (1 + exp(-$value)) : exp($value) / (1 + exp($value));
    }

    // Check features.
    private function validateFeatures(array $features): void
    {
        // Process each value.
        foreach ($features as $value) {
            // Stop if the value checks match.
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('Classifier features must be finite numbers.');
            }
        }
    }
}
