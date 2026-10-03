<?php

namespace Tests\Unit;

use App\Services\ML\BudgetOverrunClassifier;
use App\Services\ProjectOverrunEvaluation;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BudgetOverrunClassifierTest extends TestCase
{
    public function test_classifier_learns_both_outcomes_and_does_not_fit_held_out_feature_ranges(): void
    {
        $model = new BudgetOverrunClassifier;
        $model->train([[0], [0.1], [0.9], [1]], [false, false, true, true], [1, 2, 3, 4]);
        $this->assertLessThan(0.5, $model->score([0.05]));
        $this->assertGreaterThan(0.5, $model->score([0.95]));
        $before = $model->modelData();
        $this->assertGreaterThanOrEqual(0, $model->score([10]));
        $this->assertLessThanOrEqual(1, $model->score([-10]));
        $this->assertSame($before, $model->modelData());
    }

    public function test_repeating_one_projects_identical_snapshot_does_not_increase_its_training_weight(): void
    {
        $original = new BudgetOverrunClassifier;
        $repeated = new BudgetOverrunClassifier;
        $original->train([[0], [0.1], [0.9], [1]], [false, false, true, true], [1, 2, 3, 4]);
        $repeated->train([[0], [0.1], [0.9], [1], [1], [1]], [false, false, true, true, true, true], [1, 2, 3, 4, 4, 4]);
        $this->assertEqualsWithDelta($original->score([0.7]), $repeated->score([0.7]), 0.000000001);
    }

    public function test_one_class_cannot_train_an_overrun_classifier(): void
    {
        $this->expectException(RuntimeException::class);
        (new BudgetOverrunClassifier)->train([[0], [1], [2], [3]], [false, false, false, false], [1, 2, 3, 4]);
    }

    public function test_binary_evaluation_reports_confusion_counts_and_undefined_scores_honestly(): void
    {
        $metrics = (new ProjectOverrunEvaluation)->evaluateClasses([true, true, false, false], [true, false, true, false]);
        $this->assertSame(['tp' => 1, 'fp' => 1, 'tn' => 1, 'fn' => 1], $metrics['classification_counts']);
        foreach (['classification_accuracy', 'precision', 'recall', 'f1_score', 'balanced_accuracy'] as $metric) {
            $this->assertSame(50.0, $metrics[$metric]);
        }
        $empty = (new ProjectOverrunEvaluation)->evaluateClasses([false], [false]);
        $this->assertNull($empty['recall']);
        $this->assertNull($empty['precision']);
        $this->assertNull($empty['f1_score']);
        $this->assertNull($empty['balanced_accuracy']);
        $this->assertSame(100.0, $empty['classification_accuracy']);
    }
}
