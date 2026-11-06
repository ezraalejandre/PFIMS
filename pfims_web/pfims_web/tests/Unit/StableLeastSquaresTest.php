<?php

namespace Tests\Unit;

use App\Services\ML\StableLeastSquares;
use Phpml\Regression\LeastSquares;
use PHPUnit\Framework\TestCase;

class StableLeastSquaresTest extends TestCase
{
    public function test_full_rank_noisy_fit_matches_ordinary_least_squares(): void
    {
        $samples = [[0, 0], [1, 0], [0, 1], [2, 3], [4, 1], [3, 5]];
        $targets = [3, 5.1, 2.2, 4.3, 10.2, 4.1];
        $reference = new LeastSquares;
        $reference->train($samples, $targets);
        $model = new StableLeastSquares;
        $model->train($samples, $targets);
        foreach ([[1, 2], [7, 9], [-1, 4]] as $sample) {
            $this->assertEqualsWithDelta($reference->predict($sample), $model->predict($sample), 1e-9);
        }
    }

    public function test_overlapping_and_constant_inputs_keep_the_full_prediction_vector(): void
    {
        $model = new StableLeastSquares;
        $model->train([[0, 0, 5], [1, 2, 5], [2, 4, 5], [3, 6, 5]], [7, 10, 13, 16]);
        $this->assertCount(3, $model->getCoefficients());
        $this->assertEqualsWithDelta(19, $model->predict([4, 8, 5]), 1e-9);
        $restored = unserialize(serialize($model));
        $this->assertEqualsWithDelta(22, $restored->predict([5, 10, 5]), 1e-9);
    }

    public function test_constant_only_features_fit_an_intercept_without_a_penalty(): void
    {
        $model = new StableLeastSquares;
        $model->train([[4, 4], [4, 4], [4, 4]], [10, 20, 30]);
        $this->assertEqualsWithDelta(20, $model->predict([4, 4]), 1e-9);
    }
}
