<?php

namespace App\Services\ML;

use InvalidArgumentException;
use Phpml\Math\Matrix;
use Phpml\Regression\Regression;
use RuntimeException;

/** Limit the model weights to reduce overfitting. Leave the starting value unchanged. */
class RidgeRegression implements Regression
{
    private array $coefficients = [];

    // Set up the values this class needs.
    public function __construct(private float $alpha)
    {
        // Stop if the alpha checks match.
        if (! is_finite($alpha) || $alpha <= 0) {
            throw new InvalidArgumentException('Ridge alpha must be positive and finite.');
        }
    }

    // Build the prediction model from the training data.
    public function train(array $samples, array $targets): void
    {
        // Stop if the samples and targets checks match.
        if ($samples === [] || count($samples) !== count($targets)) {
            throw new InvalidArgumentException('Ridge requires matching samples and targets.');
        }
        $width = count($samples[0]);
        $normal = array_fill(0, $width + 1, array_fill(0, $width + 1, 0.0));
        $rhs = array_fill(0, $width + 1, [0.0]);
        // Process each sample.
        foreach ($samples as $index => $sample) {
            // Stop if the sample and width checks match.
            if (count($sample) !== $width || ! is_numeric($targets[$index]) || ! is_finite((float) $targets[$index])) {
                throw new InvalidArgumentException('Ridge training data is invalid.');
            }
            $row = [1.0, ...$sample];
            // Process each value.
            foreach ($row as $j => $value) {
                // Stop if the value checks match.
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    throw new InvalidArgumentException('Ridge features must be finite numbers.');
                }
                $rhs[$j][0] += $value * $targets[$index];
                // Process each other.
                foreach ($row as $k => $other) {
                    $normal[$j][$k] += $value * $other;
                }
            }
        }
        // Repeat for each step in this range.
        for ($j = 1; $j <= $width; $j++) {
            $normal[$j][$j] += $this->alpha;
        }
        $this->coefficients = (new Matrix($normal))->inverse()->multiply(new Matrix($rhs))->getColumnValues(0);
        // Process each value.
        foreach ($this->coefficients as $value) {
            // Stop if the value is not a finite number.
            if (! is_finite((float) $value)) {
                throw new RuntimeException('Ridge produced non-finite coefficients.');
            }
        }
    }

    // Calculate a prediction from the supplied values.
    public function predict(array $samples)
    {
        // Return early if the samples checks match.
        if (isset($samples[0]) && is_array($samples[0])) {
            return array_map(fn ($sample) => $this->predict($sample), $samples);
        }
        // Stop if the samples checks match.
        if ($this->coefficients === [] || count($samples) !== count($this->coefficients) - 1) {
            throw new InvalidArgumentException('Ridge prediction features do not match a trained model.');
        }
        $result = $this->coefficients[0];
        // Process each value.
        foreach ($samples as $j => $value) {
            $result += $value * $this->coefficients[$j + 1];
        }

        return $result;
    }
}
