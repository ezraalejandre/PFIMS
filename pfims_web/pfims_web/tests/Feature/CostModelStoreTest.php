<?php

namespace Tests\Feature;

use App\Services\ML\CostModelStore;
use App\Services\ML\PortableRbfSvr;
use App\Services\ML\RidgeRegression;
use App\Services\MLService;
use Illuminate\Support\Facades\File;
use Phpml\ModelManager;
use Phpml\Regression\LeastSquares;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class CostModelStoreTest extends TestCase
{
    public function test_saved_final_cost_comparison_does_not_add_spending_twice_for_snapshot_observations(): void
    {
        $model = new LeastSquares;
        $model->train([[0], [1], [2]], [200, 200, 200]);
        $metadata = ['prediction_target' => 'final_cost', 'evaluation_training_project_ids' => [7],
            'transformer' => ['feature_names' => ['budget'], 'selected_feature_indexes' => [0],
                'ranges' => [['min' => 0, 'max' => 1000]]]];
        $store = new CostModelStore($this->path, 10);
        $store->save($model, $metadata);
        $service = new MLService($this->path, loadModel: false);
        $test = collect([(object) ['project_id' => 7, 'snapshot_id' => 1, 'budget' => 300,
            'actual_cost' => 100, 'fin_total_expense' => 50, 'captured_at' => '2025-01-01']]);
        $method = new ReflectionMethod($service, 'evaluateSavedActiveEstimator');
        $first = $method->invoke($service, $test);
        $this->assertSame('evaluated', $first['status']);
        $this->assertSame(50.0, $first['evaluation']['mean_absolute_error']);
        $this->assertSame([7], $first['known_previously_used_project_ids']);
        $store->save($model, array_replace($metadata, ['prediction_target' => 'remaining_cost_then_add_recorded_spend']));
        $second = $method->invoke($service, $test);
        $this->assertSame(100.0, $second['evaluation']['mean_absolute_error']);
    }

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/cost-store-'.uniqid().'/model.phpml');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->path));
        parent::tearDown();
    }

    private function linear(float $multiplier = 1): LeastSquares
    {
        $model = new LeastSquares;
        $model->train([[0], [1], [2]], [10 * $multiplier, 20 * $multiplier, 30 * $multiplier]);

        return $model;
    }

    public function test_model_store_round_trips_every_supported_algorithm_with_matching_metadata(): void
    {
        $ridge = new RidgeRegression(0.1);
        $ridge->train([[0], [1], [2]], [10, 20, 30]);
        $svr = PortableRbfSvr::fromLibsvm("svm_type epsilon_svr\nkernel_type rbf\ngamma 1\nnr_class 2\ntotal_sv 1\nrho 0\nSV\n1 1:0.5\n", 1);
        $store = new CostModelStore($this->path, 10);
        foreach ([$this->linear(), $ridge, $svr] as $model) {
            $metadata = $store->save($model, ['schema_version' => 10, 'test_identity' => $model::class]);
            $loaded = $store->load();
            $this->assertSame('active', $loaded['recovery_source']);
            $this->assertSame(CostModelStore::algorithm($model), $loaded['metadata']['model_type']);
            $this->assertSame($metadata['model_sha256'], hash_file('sha256', $this->path));
            $this->assertEqualsWithDelta($model->predict([0.75]), $loaded['model']->predict([0.75]), 0.00000001);
        }
        $this->assertFileExists($this->path.'.previous');
        $this->assertSame([], glob($this->path.'.candidate.*'));
        $this->assertSame([], glob($this->path.'.backup.*'));
    }

    public function test_corrupt_or_mismatched_active_pair_recovers_the_previous_pair_without_writing(): void
    {
        $store = new CostModelStore($this->path, 10);
        $store->save($this->linear(), ['version_name' => 'first']);
        $store->save($this->linear(2), ['version_name' => 'second']);
        $metadata = json_decode(File::get($this->path.'.meta.json'), true);
        $metadata['model_type'] = 'support_vector_regression_rbf';
        File::put($this->path.'.meta.json', json_encode($metadata));
        $before = hash_file('sha256', $this->path.'.meta.json');
        $recovered = $store->load();
        $this->assertSame('previous', $recovered['recovery_source']);
        $this->assertSame('first', $recovered['metadata']['version_name']);
        $this->assertEqualsWithDelta(20, $recovered['model']->predict([1]), 0.000001);
        $this->assertSame($before, hash_file('sha256', $this->path.'.meta.json'));
        File::put($this->path, 'corrupt');
        $this->assertSame('first', $store->load()['metadata']['version_name']);
        // Saving over corruption must preserve the last valid previous pair.
        $store->save($this->linear(3), ['version_name' => 'third']);
        File::put($this->path, 'corrupt again');
        $this->assertSame('first', $store->load()['metadata']['version_name']);
    }

    public function test_existing_linear_artifact_loads_without_retraining_or_rewriting_it(): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        (new ModelManager)->saveToFile($this->linear(), $this->path);
        File::put($this->path.'.meta.json', json_encode(['schema_version' => 10, 'model_type' => 'least_squares_linear_regression']));
        $before = hash_file('sha256', $this->path);
        $service = new MLService($this->path);
        $this->assertSame('least_squares_linear_regression', $service->getModelMetadata()['model_type']);
        $this->assertSame($before, hash_file('sha256', $this->path));
        $this->assertArrayNotHasKey('trained_at', $service->getModelMetadata());
    }

    public function test_svr_currency_scaling_and_remaining_cost_are_identical_after_save_and_reload(): void
    {
        $service = new MLService($this->path, loadModel: false);
        $records = collect(range(1, 12))->map(fn ($i) => (object) [
            'project_id' => $i, 'snapshot_id' => $i, 'budget' => 1000000 + $i * 300000,
            'duration_months' => 4 + ($i % 3), 'actual_cost' => (1000000 + $i * 300000) * (0.4 + ($i % 4) * 0.01),
            'fin_total_expense' => (1000000 + $i * 300000) * 0.2,
        ]);
        [$model, $transformer] = (new ReflectionMethod($service, 'buildServableRegressionModel'))->invoke($service,
            'support_vector_regression_rbf', ['cost' => 10.0, 'gamma' => 0.1, 'epsilon' => 0.01], $records, ['budget', 'duration_months']);
        $this->assertInstanceOf(PortableRbfSvr::class, $model);
        (new ReflectionProperty($service, 'model'))->setValue($service, $model);
        (new ReflectionProperty($service, 'metadata'))->setValue($service, [
            'model_source' => 'sample_trained_model', 'prediction_strategy' => 'progress_snapshot_model',
            'prediction_target' => 'remaining_cost_then_add_recorded_spend', 'transformer' => $transformer,
        ]);
        (new ReflectionMethod($service, 'saveModelAndMetadata'))->invoke($service);
        $restored = new MLService($this->path);
        $features = [5000000, 6, 8, 20, 600000, 300000, 1000000];
        $prediction = $restored->predict($features, ['cost_coverage_complete' => true]);
        $this->assertEqualsWithDelta($service->predict($features, ['cost_coverage_complete' => true]), $prediction, 0.00001);
        $this->assertGreaterThanOrEqual(1000000, $prediction);
        $evaluation = (new ReflectionMethod($restored, 'evaluateModel'))->invoke($restored, $model, $transformer, collect([
            (object) ['project_id' => 20, 'snapshot_id' => 20, 'budget' => 5000000, 'duration_months' => 6,
                'actual_cost' => $prediction - 1000000, 'fin_total_expense' => 1000000],
        ]));
        $this->assertSame(0.0, $evaluation['mean_absolute_error']);
        $this->assertSame('support_vector_regression_rbf', $restored->getModelMetrics()['model_type']);
        $this->assertSame('active', $restored->getModelMetrics()['model_recovery_source']);
    }
}
