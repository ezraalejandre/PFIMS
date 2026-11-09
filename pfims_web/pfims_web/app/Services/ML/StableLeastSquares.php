<?php

namespace App\Services\ML;

use InvalidArgumentException;
use Phpml\Regression\LeastSquares;

/** Fit a line to the training data. Use QR math to handle inputs that are nearly the same. */
class StableLeastSquares extends LeastSquares
{
    private array $weights = [];

    private float $bias = 0.0;

    // Build the prediction model from the training data.
    public function train(array $samples, array $targets): void
    {
        $count = count($samples);
        $width = count($samples[0] ?? []);
        // Stop if the count and targets checks match.
        if ($count === 0 || $count !== count($targets)) {
            throw new InvalidArgumentException('Least squares requires matching samples and targets.');
        }
        $means = array_fill(0, $width, 0.0);
        // Process each sample.
        foreach ($samples as $i => $sample) {
            // Reject invalid least-squares training data.
            if (count($sample) !== $width || ! is_numeric($targets[$i]) || ! is_finite((float) $targets[$i])) {
                throw new InvalidArgumentException('Invalid least-squares training data.');
            }
            // Process each value.
            foreach ($sample as $j => $value) {
                // Stop if the value checks match.
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    throw new InvalidArgumentException('Least-squares inputs must be finite.');
                }
                $means[$j] += $value / $count;
            }
        }
        $targetMean = array_sum($targets) / $count;
        $centeredTargets = array_map(fn ($y) => $y - $targetMean, $targets);
        $columns = [];
        // Process each mean.
        foreach ($means as $j => $mean) {
            $columns[] = array_map(fn ($sample) => $sample[$j] - $mean, $samples);
        }
        $order = $width ? range(0, $width - 1) : [];
        $dot = static function (array $a, array $b): float {
            $sum = 0.0;
            // Process each value.
            foreach ($a as $i => $value) {
                $sum += $value * $b[$i];
            }

            return $sum;
        };
        $scale = 0.0;
        // Process each column.
        foreach ($columns as $column) {
            $scale = max($scale, sqrt($dot($column, $column)));
        }
        $tolerance = max(1.0, $scale) * 1e-10;
        $r = $qty = [];
        $rank = 0;
        // Repeat for each step in this range.
        for ($k = 0; $k < $width; $k++) {
            $pivot = $k;
            $largest = -1.0;
            // Repeat for each step in this range.
            for ($j = $k; $j < $width; $j++) {
                $norm = $dot($columns[$j], $columns[$j]);
                // Run this step if the norm is above largest.
                if ($norm > $largest) {
                    $largest = $norm;
                    $pivot = $j;
                }
            }
            $length = sqrt(max(0.0, $largest));
            // Run this step if the length is at most tolerance.
            if ($length <= $tolerance) {
                break;
            }
            [$columns[$k], $columns[$pivot]] = [$columns[$pivot], $columns[$k]];
            [$order[$k], $order[$pivot]] = [$order[$pivot], $order[$k]];
            // Repeat for each step in this range.
            for ($i = 0; $i < $k; $i++) {
                [$r[$i][$k], $r[$i][$pivot]] = [$r[$i][$pivot], $r[$i][$k]];
            }
            $q = array_map(fn ($value) => $value / $length, $columns[$k]);
            $r[$k] = array_fill(0, $width, 0.0);
            $r[$k][$k] = $length;
            $qty[$k] = $dot($q, $centeredTargets);
            // Repeat for each step in this range.
            for ($j = $k + 1; $j < $width; $j++) {
                // Repeat the math step to keep similar input columns from causing errors.
                for ($pass = 0; $pass < 2; $pass++) {
                    $projection = $dot($q, $columns[$j]);
                    $r[$k][$j] += $projection;
                    // Process each value.
                    foreach ($q as $i => $value) {
                        $columns[$j][$i] -= $projection * $value;
                    }
                }
            }
            $rank++;
        }
        $solution = array_fill(0, $width, 0.0);
        // Repeat for each step in this range.
        for ($i = $rank - 1; $i >= 0; $i--) {
            $remaining = $qty[$i];
            // Repeat for each step in this range.
            for ($j = $i + 1; $j < $rank; $j++) {
                $remaining -= $r[$i][$j] * $solution[$j];
            }
            $solution[$i] = $remaining / $r[$i][$i];
        }
        $this->weights = array_fill(0, $width, 0.0);
        // Process each original.
        foreach ($order as $j => $original) {
            $this->weights[$original] = $solution[$j];
        }
        $this->bias = $targetMean - $dot($means, $this->weights);
    }

    // Get the predict sample.
    public function predictSample(array $sample)
    {
        // Stop if the number of sample does not equal weights.
        if (count($sample) !== count($this->weights)) {
            throw new InvalidArgumentException('Least-squares prediction inputs do not match training.');
        }
        $prediction = $this->bias;
        // Process each weight.
        foreach ($this->weights as $j => $weight) {
            $prediction += $weight * $sample[$j];
        }

        return $prediction;
    }

    // Get the weights used by the model.
    public function getCoefficients(): array
    {
        return $this->weights;
    }

    // Get the starting value used by the model.
    public function getIntercept(): float
    {
        return $this->bias;
    }
}
