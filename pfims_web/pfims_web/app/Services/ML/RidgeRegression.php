<?php

namespace App\Services\ML;

use InvalidArgumentException;
use Phpml\Math\Matrix;
use Phpml\Regression\Regression;
use RuntimeException;

/** L2 regularization of feature coefficients; the intercept is never penalized. */
class RidgeRegression implements Regression
{
    private array $coefficients = [];

    public function __construct(private float $alpha)
    {
        if (! is_finite($alpha) || $alpha <= 0) {
            throw new InvalidArgumentException('Ridge alpha must be positive and finite.');
        }
    }

    public function train(array $samples, array $targets): void
    {
        if ($samples === [] || count($samples) !== count($targets)) {
            throw new InvalidArgumentException('Ridge requires matching samples and targets.');
        }
        $width = count($samples[0]);
        $normal = array_fill(0, $width + 1, array_fill(0, $width + 1, 0.0));
        $rhs = array_fill(0, $width + 1, [0.0]);
        foreach ($samples as $index => $sample) {
            if (count($sample) !== $width || ! is_numeric($targets[$index]) || ! is_finite((float) $targets[$index])) {
                throw new InvalidArgumentException('Ridge training data is invalid.');
            }
            $row = [1.0, ...$sample];
            foreach ($row as $j => $value) {
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    throw new InvalidArgumentException('Ridge features must be finite numbers.');
                }
                $rhs[$j][0] += $value * $targets[$index];
                foreach ($row as $k => $other) {
                    $normal[$j][$k] += $value * $other;
                }
            }
        }
        for ($j = 1; $j <= $width; $j++) {
            $normal[$j][$j] += $this->alpha;
        }
        $this->coefficients = (new Matrix($normal))->inverse()->multiply(new Matrix($rhs))->getColumnValues(0);
        foreach ($this->coefficients as $value) {
            if (! is_finite((float) $value)) {
                throw new RuntimeException('Ridge produced non-finite coefficients.');
            }
        }
    }

    public function predict(array $samples)
    {
        if (isset($samples[0]) && is_array($samples[0])) {
            return array_map(fn ($sample) => $this->predict($sample), $samples);
        }
        if ($this->coefficients === [] || count($samples) !== count($this->coefficients) - 1) {
            throw new InvalidArgumentException('Ridge prediction features do not match a trained model.');
        }
        $result = $this->coefficients[0];
        foreach ($samples as $j => $value) {
            $result += $value * $this->coefficients[$j + 1];
        }

        return $result;
    }
}
