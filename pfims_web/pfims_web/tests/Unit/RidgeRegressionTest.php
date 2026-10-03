<?php

namespace Tests\Unit;

use App\Services\ML\RidgeRegression;
use PHPUnit\Framework\TestCase;

class RidgeRegressionTest extends TestCase
{
    public function test_ridge_handles_collinear_features_and_does_not_shrink_the_intercept(): void
    {
        $model = new RidgeRegression(0.1);
        $model->train([[0, 0], [1, 2], [2, 4], [3, 6]], [100, 110, 120, 130]);
        $this->assertEqualsWithDelta(115, $model->predict([1.5, 3]), 0.000001);
        $this->assertEqualsWithDelta(120, $model->predict([2, 4]), 0.1);
        $constant = new RidgeRegression(1000);
        $constant->train([[0], [1], [2]], [150, 150, 150]);
        $this->assertEqualsWithDelta(150, $constant->predict([10]), 0.000001);
    }

    public function test_regularization_reduces_extreme_extrapolation(): void
    {
        $weak = new RidgeRegression(0.01);
        $strong = new RidgeRegression(10);
        foreach ([$weak, $strong] as $model) {
            $model->train([[0], [0.5], [1]], [0, 100, 200]);
        }
        $this->assertLessThan($weak->predict([2]), $strong->predict([2]));
    }
}
