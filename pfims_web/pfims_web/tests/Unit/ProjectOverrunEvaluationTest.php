<?php

namespace Tests\Unit;

use App\Services\ProjectOverrunEvaluation;
use PHPUnit\Framework\TestCase;

class ProjectOverrunEvaluationTest extends TestCase
{
    public function test_small_and_material_overruns_have_different_detection_results(): void
    {
        $evaluator = new ProjectOverrunEvaluation;
        $predicted = [103, 110, 110, 100];
        $actual = [103, 110, 100, 110];
        $budgets = [100, 100, 100, 100];
        $any = $evaluator->evaluate($predicted, $actual, $budgets, 'any_overrun');
        $material = $evaluator->evaluate($predicted, $actual, $budgets, 'material_overrun');
        $this->assertSame(['tp' => 2, 'fp' => 1, 'tn' => 0, 'fn' => 1], $any['classification_counts']);
        $this->assertSame(66.67, $any['f1_score']);
        $this->assertSame(['tp' => 1, 'fp' => 1, 'tn' => 1, 'fn' => 1], $material['classification_counts']);
        $this->assertSame(50.0, $material['precision']);
        $this->assertSame(50.0, $material['recall']);
        $this->assertSame(50.0, $material['balanced_accuracy']);
    }

    public function test_all_missed_overruns_have_zero_recall_and_f1_with_unavailable_precision(): void
    {
        $result = (new ProjectOverrunEvaluation)->evaluate([90, 90], [110, 90], [100, 100], 'material_overrun');
        $this->assertNull($result['precision']);
        $this->assertSame(0.0, $result['recall']);
        $this->assertSame(0.0, $result['f1_score']);
        $this->assertSame(50.0, $result['classification_accuracy']);
    }

    public function test_no_positives_and_invalid_budgets_do_not_create_false_evidence(): void
    {
        $result = (new ProjectOverrunEvaluation)->evaluate([110, 100, 100, INF], [105, 100, 110, 110], [100, 100, 0, 100], 'material_overrun');
        $this->assertSame(2, $result['excluded_observations']);
        $this->assertSame(2, $result['evaluated_observations']);
        $this->assertSame('no_actual_overruns', $result['evidence_status']);
        $this->assertNull($result['recall']);
        $this->assertNull($result['f1_score']);
        $this->assertSame(0.0, $result['precision']);
    }
}
