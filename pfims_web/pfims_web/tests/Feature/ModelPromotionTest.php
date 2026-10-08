<?php

namespace Tests\Feature;

use App\Services\ML\BudgetOverrunClassifier;
use App\Services\ML\CostModelStore;
use App\Services\ML\ModelPromotionService;
use App\Services\MLService;
use Illuminate\Support\Facades\File;
use Phpml\Regression\LeastSquares;
use Tests\TestCase;

class ModelPromotionTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/promotion-'.uniqid().'/model.phpml');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->path));
        parent::tearDown();
    }

    private function evidence(): array
    {
        return ['verified_genuine_source_records' => true, 'fresh_independent_holdout' => true,
            'training_project_ids' => [1, 2], 'holdout_project_ids' => [3, 4],
            'training_only_tuning' => true, 'same_holdout_active_comparison' => true,
            'project_level_primary_evaluation' => true];
    }

    private function cost(): LeastSquares
    {
        $model = new LeastSquares;
        $model->train([[0], [1], [2]], [10, 20, 30]);

        return $model;
    }

    public function test_rejected_promotion_and_routine_retraining_preserve_both_active_files(): void
    {
        (new CostModelStore($this->path, 10))->save($this->cost(), ['model_source' => 'real_trained_model']);
        $before = [hash_file('sha256', $this->path), hash_file('sha256', $this->path.'.meta.json')];
        $result = (new ModelPromotionService)->promoteCost($this->path, $this->cost(), [], []);
        $this->assertFalse($result['activated']);
        $service = new MLService($this->path);
        $this->assertFalse($service->train());
        $this->assertFalse($service->retrain()['candidate_activated']);
        $this->assertSame($before, [hash_file('sha256', $this->path), hash_file('sha256', $this->path.'.meta.json')]);
    }

    public function test_qualified_cost_promotion_preserves_the_previous_estimator(): void
    {
        (new CostModelStore($this->path, 10))->save($this->cost(), ['version' => 'previous']);
        $metrics = ['mean_absolute_percentage_error' => 5, 'mean_absolute_error' => 500, 'r_squared' => 0.9, 'target_median' => 20000];
        $result = (new ModelPromotionService)->promoteCost($this->path, $this->cost(), ['evaluation' => $metrics],
            $this->evidence() + ['mae_tolerance' => 1000, 'budget_baseline_mape' => 10, 'active_model_evaluation' => $metrics]);
        $this->assertTrue($result['activated']);
        $this->assertFileExists($this->path.'.previous');
        $this->assertTrue((new CostModelStore($this->path, 10))->load()['metadata']['activation_policy']['eligible']);
    }

    public function test_detector_is_independent_rejects_missing_evidence_and_recovers_previous_artifact(): void
    {
        $model = new BudgetOverrunClassifier;
        $model->train([[0], [0.1], [0.9], [1]], [false, false, true, true], [1, 2, 3, 4]);
        $metrics = ['definition' => 'any_overrun', 'classification_accuracy' => 90, 'precision' => 90,
            'recall' => 90, 'f1_score' => 90, 'balanced_accuracy' => 90, 'actual_overruns' => 5, 'actual_non_overruns' => 5];
        $metadata = ['evaluation' => $metrics, 'feature_names' => ['budget'], 'threshold' => 0.4];
        $service = new ModelPromotionService;
        $path = $this->path.'.detector.json';
        $this->assertFalse($service->promoteDetector($path, $model, $metadata, [])['activated']);
        $this->assertFileDoesNotExist($path);
        $evidence = $this->evidence() + ['active_model_evaluation' => $metrics];
        $this->assertTrue($service->promoteDetector($path, $model, $metadata, $evidence)['activated']);
        $loaded = $service->loadDetector($path);
        $this->assertEqualsWithDelta($model->score([0.8]), $loaded['model']->score([0.8]), 1e-12);
        $this->assertSame(0.4, $loaded['metadata']['threshold']);
        $this->assertFileDoesNotExist($this->path);
        $service->promoteDetector($path, $model, $metadata, $evidence);
        File::put($path, 'invalid');
        $this->assertSame('previous', $service->loadDetector($path)['recovery_source']);
        $this->assertSame('invalid', File::get($path));
    }

    public function test_served_detector_metrics_and_confirmed_spend_override_use_only_the_active_detector(): void
    {
        (new CostModelStore($this->path, 10))->save($this->cost(), ['model_source' => 'real_trained_model']);
        $service = new MLService($this->path);
        $this->assertNull($service->getLastDetectorPrediction());
        $model = new BudgetOverrunClassifier;
        $model->train([[0], [0.1], [0.9], [1]], [false, false, true, true], [1, 2, 3, 4]);
        $metrics = ['definition' => 'any_overrun', 'classification_accuracy' => 90, 'precision' => 90,
            'recall' => 90, 'f1_score' => 90, 'balanced_accuracy' => 90, 'actual_overruns' => 5, 'actual_non_overruns' => 5];
        (new ModelPromotionService)->promoteDetector($this->path.'.detector.json', $model,
            ['evaluation' => $metrics, 'feature_names' => ['budget'], 'threshold' => 0.5],
            $this->evidence() + ['active_model_evaluation' => $metrics]);
        $this->assertSame($metrics, $service->getModelMetrics()['overrun_detection']['any_overrun']);
        $this->assertSame('independent_classifier', $service->getModelMetrics()['overrun_detector_source']);
        $inputs = new \ReflectionProperty($service, 'lastDetectorInputs');
        $inputs->setValue($service, ['budget' => 0.05, 'fin_total_expense' => 0]);
        $this->assertFalse($service->getLastDetectorPrediction()['any_overrun']);
        $inputs->setValue($service, ['budget' => 0.05, 'fin_total_expense' => 0.06]);
        $this->assertTrue($service->getLastDetectorPrediction()['confirmed_spend_overrun']);
        $this->assertTrue($service->getLastDetectorPrediction()['any_overrun']);
    }
}
