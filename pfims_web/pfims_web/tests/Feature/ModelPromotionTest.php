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

    public function test_reviewed_development_activation_preserves_failed_evidence_and_cannot_waive_bad_scores(): void
    {
        $service = new ModelPromotionService;
        $metrics = ['mean_absolute_percentage_error' => 5, 'mean_absolute_error' => 500, 'r_squared' => .95, 'target_median' => 20000];
        $evidence = array_replace($this->evidence(), ['fresh_independent_holdout' => false, 'same_holdout_active_comparison' => false,
            'active_model_evaluation' => [], 'budget_baseline_mape' => 10]);
        (new CostModelStore($this->path, 10))->save($this->cost(), ['version' => 'before']);
        $this->assertFalse($service->promoteCost($this->path, $this->cost(), ['evaluation' => $metrics], $evidence)['activated']);
        $this->assertFalse($service->promoteCost($this->path, $this->cost(), ['evaluation' => array_replace($metrics, ['mean_absolute_error' => 4000])], $evidence, true)['activated']);
        $result = $service->promoteCost($this->path, $this->cost(), ['evaluation' => $metrics], $evidence, true);
        $this->assertTrue($result['activated']);
        $this->assertFalse($result['metadata']['activation_policy']['eligible']);
        $this->assertFalse($result['metadata']['activation_evidence']['fresh_independent_holdout']);
        $this->assertSame('explicit_user_requested_development', $result['metadata']['development_activation']['mode']);
        $this->assertFileExists($this->path.'.previous');
    }

    public function test_reviewed_detector_restores_but_rejects_bad_scores_and_wrong_cost_pair(): void
    {
        $cost = (new CostModelStore($this->path, 10))->save($this->cost(), []);
        $model = new BudgetOverrunClassifier;
        $model->train([[0], [.1], [.9], [1]], [false, false, true, true], [1, 2, 3, 4]);
        $metrics = ['definition' => 'any_overrun', 'classification_accuracy' => 100, 'precision' => 100,
            'recall' => 100, 'f1_score' => 100, 'balanced_accuracy' => 100, 'actual_overruns' => 2, 'actual_non_overruns' => 15];
        $evidence = array_replace($this->evidence(), ['fresh_independent_holdout' => false,
            'same_holdout_active_comparison' => false, 'active_model_evaluation' => []]);
        $meta = ['evaluation' => $metrics, 'threshold' => .6, 'feature_names' => ['budget'], 'cost_model_sha256' => $cost['model_sha256']];
        $service = new ModelPromotionService;
        $path = $this->path.'.detector.json';
        $this->assertTrue($service->promoteDetector($path, $model, $meta, $evidence, true)['activated']);
        $this->assertNotNull($service->loadDetector($path, $cost['model_sha256']));
        $this->assertNull($service->loadDetector($path, str_repeat('0', 64)));
        $stored = json_decode(File::get($path), true);
        $stored['metadata']['evaluation']['recall'] = 0;
        File::put($path, json_encode($stored));
        $this->assertNull($service->loadDetector($path, $cost['model_sha256']));
    }
}
