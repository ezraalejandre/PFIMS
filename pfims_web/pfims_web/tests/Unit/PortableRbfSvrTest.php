<?php

namespace Tests\Unit;

use App\Services\ML\PortableRbfSvr;
use InvalidArgumentException;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use PHPUnit\Framework\TestCase;

class PortableRbfSvrTest extends TestCase
{
    public function test_portable_predictions_match_libsvm_for_seen_and_unseen_vectors(): void
    {
        $samples = [[0, 0], [0.2, 0.7], [0.4, 0.1], [0.6, 0.9], [0.8, 0.2], [1, 1]];
        $native = new SVR(Kernel::RBF, epsilon: 0.01, cost: 10, gamma: 0.7);
        $native->train($samples, [0.1, 0.35, 0.27, 0.6, 0.45, 0.9]);
        $portable = PortableRbfSvr::fromLibsvm($native->getModel(), 2);
        foreach ([...$samples, [0.3, 0.55], [1.2, -0.2]] as $sample) {
            $this->assertEqualsWithDelta((float) $native->predict($sample), $portable->predict($sample), 0.000001);
        }
        $restored = unserialize(serialize($portable));
        $this->assertSame($portable->predict([0.3, 0.55]), $restored->predict([0.3, 0.55]));
    }

    public function test_sparse_vectors_and_rho_use_the_libsvm_regression_formula(): void
    {
        $model = PortableRbfSvr::fromLibsvm("svm_type epsilon_svr\nkernel_type rbf\ngamma 0.5\nnr_class 2\ntotal_sv 2\nrho 0.25\nSV\n2 1:1\n-1 2:2\n", 2);
        $this->assertEqualsWithDelta(2 - exp(-2.5) - 0.25, $model->predict([1, 0]), 0.000000001);
    }

    public function test_unsupported_model_format_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PortableRbfSvr::fromLibsvm("svm_type c_svc\nkernel_type rbf\nSV\n", 2);
    }
}
