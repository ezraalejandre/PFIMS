<?php

namespace Tests\Feature;

use App\Services\ML\CostModelStore;
use App\Services\ML\LiveProjectCostSource;
use App\Services\ML\ProjectCostAugmentationAudit;
use App\Services\ML\ProjectCostAugmentationDataset;
use App\Services\ML\ProjectCostDistributionGenerator;
use App\Services\ML\ProjectCostOptimizationService;
use App\Services\MLService;
use App\Services\ProjectCostFeatureBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class ProjectCostAugmentationTest extends TestCase
{
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            File::deleteDirectory($directory);
        }
        parent::tearDown();
    }

    private function directory(): string
    {
        $path = storage_path('framework/testing/augmentation-'.uniqid());
        $this->directories[] = $path;

        return $path;
    }

    private function source(): array
    {
        $source = ['source' => ['host' => 'srv603.hstgr.io', 'database' => 'u822802132_pfims', 'captured_at' => '2026-10-08'],
            'project_tbl' => [], 'fin_expense_tbl' => [], 'inventory_transaction_tbl' => [],
            'inventory_item_tbl' => [['item_id' => 1, 'item_name' => 'Cement', 'unit_price' => 250], ['item_id' => 2, 'item_name' => 'Steel', 'unit_price' => 500]],
            'cohort' => ['strategy' => 'planning_only_baseline', 'records' => []]];
        foreach (range(1, 10) as $id) {
            $budget = 500000 + $id * 100000;
            $cost = $budget * (.95 + .02 * ($id % 4));
            $end = sprintf('2025-%02d-20', $id);
            $source['project_tbl'][] = ['project_id' => $id, 'project_name' => 'Residence '.$id, 'start_date' => '2025-01-01', 'estimated_end_date' => $end, 'actual_end_date' => $end];
            $source['cohort']['records'][] = ['project_id' => $id, 'completed_at' => $end, 'budget' => $budget, 'actual_cost' => $cost, 'duration_months' => $id,
                'worker_count' => 5, 'data_source' => 'operational'];
            $source['fin_expense_tbl'][] = ['project_id' => $id, 'inventory_transaction_id' => null, 'amount' => $cost * .5, 'expense_date' => '2025-01-15',
                'project_cost_component' => 'labor', 'fin_category_id' => 1, 'expense_description' => 'Masonry payroll'];
            $source['inventory_transaction_tbl'][] = ['project_id' => $id, 'item_id' => 1, 'quantity' => 30, 'transaction_type' => 'OUT', 'transaction_date' => '2025-01-20'];
        }

        return $source;
    }

    public function test_distribution_generation_preserves_counts_catalogue_reconciliation_and_training_only_lineage(): void
    {
        $source = $this->source();
        $path = $this->directory();
        $manifest = (new ProjectCostDistributionGenerator)->generate($source, $path, 100, 3, 10, 101);
        $audit = (new ProjectCostAugmentationAudit)->audit($path);
        $this->assertTrue($audit['valid']);
        $this->assertSame(100, $audit['projects']);
        $this->assertSame(300, $audit['expenses']);
        $this->assertSame(1000, $audit['inventory_transactions']);
        $this->assertSame(2, $audit['items']);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $manifest['database_training_project_ids']);
        $this->assertSame([9, 10], $manifest['database_test_project_ids']);
        $this->assertSame($source['inventory_item_tbl'], iterator_to_array(ProjectCostAugmentationDataset::read($path.'/items.jsonl.gz')));
        $database = collect($source['cohort']['records'])->map(fn ($r) => (object) $r);
        $dataset = new ProjectCostAugmentationDataset($path);
        $dataset->assertDatabaseMatches($database);
        $fold = $database->whereIn('project_id', [1, 2, 3])->values();
        $mixed = $dataset->augment($fold);
        $this->assertGreaterThan($fold->count(), $mixed->count());
        foreach ($mixed->filter(fn ($row) => isset($row->donor_project_id)) as $row) {
            $this->assertContains($row->donor_project_id, [1, 2, 3]);
        }
        $this->expectException(RuntimeException::class);
        $dataset->augment($database->whereIn('project_id', [9, 10])->values());
    }

    public function test_test_project_values_do_not_influence_any_generated_training_files(): void
    {
        $source = $this->source();
        $first = $this->directory();
        $second = $this->directory();
        (new ProjectCostDistributionGenerator)->generate($source, $first, 30, 3, 10, 27);
        foreach ([8, 9] as $index) {
            $source['cohort']['records'][$index]['budget'] = 999999999;
            $source['cohort']['records'][$index]['actual_cost'] = 777777777;
            $source['fin_expense_tbl'][$index]['amount'] = 666666666;
            $source['inventory_transaction_tbl'][$index]['quantity'] = 888888;
        }
        (new ProjectCostDistributionGenerator)->generate($source, $second, 30, 3, 10, 27);
        foreach (['projects', 'expenses', 'inventory-transactions', 'training-observations', 'items'] as $file) {
            $this->assertSame(gzdecode(file_get_contents($first.'/'.$file.'.jsonl.gz')), gzdecode(file_get_contents($second.'/'.$file.'.jsonl.gz')));
        }
    }

    public function test_changed_database_records_and_tampered_files_fail_closed(): void
    {
        $source = $this->source();
        $path = $this->directory();
        (new ProjectCostDistributionGenerator)->generate($source, $path, 20, 3, 10, 27);
        $dataset = new ProjectCostAugmentationDataset($path);
        $changed = collect($source['cohort']['records'])->map(fn ($r) => (object) $r);
        $changed[0]->budget++;
        try {
            $dataset->assertDatabaseMatches($changed);
            $this->fail('Stale donors must be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('changed', $error->getMessage());
        }
        file_put_contents($path.'/training-observations.jsonl.gz', 'corrupted');
        $this->expectException(RuntimeException::class);
        new ProjectCostAugmentationDataset($path);
    }

    public function test_regression_cross_validation_uses_dummy_training_rows_and_database_only_validation(): void
    {
        $source = $this->source();
        $path = $this->directory();
        (new ProjectCostDistributionGenerator)->generate($source, $path, 40, 3, 10, 27);
        $service = new MLService(storage_path('framework/testing/augmentation-model.phpml'), loadModel: false);
        $property = new \ReflectionProperty($service, 'augmentation');
        $property->setValue($service, new ProjectCostAugmentationDataset($path));
        $records = collect(array_slice($source['cohort']['records'], 0, 8))->map(fn ($r) => (object) $r);
        $cv = (new \ReflectionMethod($service, 'kFoldCrossValidation'))->invoke($service, $records, ['budget', 'duration_months']);
        $this->assertGreaterThan(0, $cv['folds_run']);
        foreach ($cv['folds'] as $fold) {
            $this->assertGreaterThan(0, $fold['dummy_training_samples']);
            $this->assertEmpty(array_intersect($fold['training_project_ids'], $fold['test_project_ids']));
            foreach ($fold['test_project_ids'] as $id) {
                $this->assertIsInt($id);
            }
        }
    }

    public function test_source_export_rejects_a_local_database_without_writing_an_output(): void
    {
        $path = $this->directory().'/source.json.gz';
        try {
            (new LiveProjectCostSource)->export('sqlite', $path);
            $this->fail('A local testing database must not be accepted as live.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('live PFIMS', $error->getMessage());
            $this->assertFileDoesNotExist($path);
        }
    }

    public function test_dummy_planned_duration_uses_the_planned_date_instead_of_the_actual_completion_date(): void
    {
        $source = $this->source();
        $path = $this->directory();
        foreach ($source['project_tbl'] as &$project) {
            $project['estimated_end_date'] = '2025-06-01';
            $project['actual_end_date'] = '2025-03-01';
        }
        unset($project);
        (new ProjectCostDistributionGenerator)->generate($source, $path, 12, 3, 10, 27);
        foreach (ProjectCostAugmentationDataset::read($path.'/projects.jsonl.gz') as $project) {
            $this->assertGreaterThan($project['actual_end_date'], $project['estimated_end_date']);
            $months = (int) CarbonImmutable::parse($project['start_date'])->diffInMonths(CarbonImmutable::parse($project['estimated_end_date']));
            $this->assertSame(max(1, $months), $project['duration_months']);
        }
    }

    public function test_progress_dummy_training_labels_and_features_are_calculated_from_generated_events(): void
    {
        $source = $this->source();
        $path = $this->directory();
        $snapshots = [];
        foreach ($source['cohort']['records'] as $row) {
            $project = $source['project_tbl'][$row['project_id'] - 1];
            $days = (int) CarbonImmutable::parse($project['start_date'])->diffInDays(CarbonImmutable::parse($project['actual_end_date']));
            foreach ([.25, .65] as $stage) {
                $snapshots[] = array_replace($row, [
                    'snapshot_id' => $row['project_id'].'-'.$stage, 'elapsed_days' => (int) floor($days * $stage),
                    'completion_percentage' => $stage * 100, 'fin_total_expense' => $row['actual_cost'] * $stage,
                    'reconciled_final_cost' => $row['actual_cost'], 'actual_cost' => $row['actual_cost'] * (1 - $stage),
                ]);
            }
        }
        $source['cohort']['records'] = $snapshots;
        $source['cohort']['strategy'] = 'progress_snapshot_model';
        (new ProjectCostDistributionGenerator)->generate($source, $path, 12, 3, 10, 27);
        $audit = (new ProjectCostAugmentationAudit)->audit($path);
        $this->assertTrue($audit['valid']);
        $rows = iterator_to_array(ProjectCostAugmentationDataset::read($path.'/training-observations.jsonl.gz'));
        $this->assertCount(24, $rows);
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta($row['reconciled_final_cost'], $row['actual_cost'] + $row['fin_total_expense'], .01);
            foreach (app(ProjectCostFeatureBuilder::class)->build($row)['values'] as $name => $value) {
                $this->assertEqualsWithDelta($value, $row[$name], .000001);
            }
        }
    }

    public function test_augmented_retrain_persists_a_candidate_with_database_only_test_metrics_and_preserves_activation_gates(): void
    {
        $source = $this->source();
        $path = $this->directory();
        (new ProjectCostDistributionGenerator)->generate($source, $path, 40, 3, 10, 27);
        config(['ml.augmentation_directory' => $path, 'ml.augmentation_enabled' => true]);
        $modelPath = $path.'/active.phpml';
        $service = new class($modelPath, $source['cohort']['records']) extends MLService
        {
            public function __construct(string $path, private array $rows)
            {
                parent::__construct($path, loadModel: false);
            }

            public function databaseTrainingCohort(): array
            {
                return ['records' => collect($this->rows)->map(fn ($r) => (object) $r), 'strategy' => 'planning_only_baseline',
                    'feature_names' => ['budget', 'duration_months'], 'snapshot_readiness' => ['eligible' => false]];
            }
        };
        $result = $service->retrain('auto');
        $this->assertFalse($result['candidate_activated']);
        $this->assertFileDoesNotExist($modelPath);
        $metadata = json_decode(file_get_contents($result['candidate_path'].'.meta.json'), true);
        $this->assertSame(48, $metadata['training_projects']);
        $this->assertSame(2, $metadata['test_samples']);
        $this->assertSame([9, 10], $metadata['evaluation_holdout_project_ids']);
        $this->assertSame(2, $result['candidate_evaluation']['evaluation_projects']);
        $this->assertTrue($result['activation']['checks']['project_level_primary_evaluation']);
        $this->assertContains('same_holdout_active_comparison', $result['activation']['failed_requirements']);
        $this->assertContains('fresh_independent_holdout', $result['activation']['failed_requirements']);
    }

    public function test_reduced_influence_keeps_projects_together_and_excludes_validation_donors(): void
    {
        $source = $this->source();
        $path = $this->directory();
        (new ProjectCostDistributionGenerator)->generate($source, $path, 100, 3, 10, 101);
        $training = collect($source['cohort']['records'])->map(fn ($row) => (object) $row)->whereIn('project_id', [1, 2, 3, 4]);
        $none = new ProjectCostAugmentationDataset($path, 0);
        $reduced = new ProjectCostAugmentationDataset($path, .5);
        $this->assertCount(4, $none->augment($training));
        $mixed = $reduced->augment($training);
        $this->assertCount(6, $mixed);
        $this->assertSame($mixed->pluck('project_id')->all(), $reduced->augment($training)->pluck('project_id')->all());
        foreach ($mixed->filter(fn ($row) => isset($row->donor_project_id)) as $row) {
            $this->assertContains($row->donor_project_id, [1, 2, 3, 4]);
        }
        $this->expectException(RuntimeException::class);
        new ProjectCostAugmentationDataset($path, -1);
    }

    public function test_optimization_uses_training_only_selection_preserves_active_model_and_reproduces_served_formulas(): void
    {
        $source = $this->source();
        $source['cohort']['feature_names'] = ['budget', 'duration_months'];
        $source['cohort']['snapshot_readiness'] = ['eligible' => false];
        $path = $this->directory();
        (new ProjectCostDistributionGenerator)->generate($source, $path, 20, 3, 10, 101);
        config(['ml.augmentation_directory' => $path]);
        $sourcePath = $path.'/source.json.gz';
        file_put_contents($sourcePath, gzencode(json_encode($source)));
        $modelPath = $path.'/active.phpml';
        $report = (new ProjectCostOptimizationService($modelPath))->run($sourcePath);
        $this->assertFalse($report['active_model_changed']);
        $this->assertFileDoesNotExist($modelPath);
        $this->assertTrue($report['restored_evaluation_verified']);
        $this->assertSame([9, 10], $report['holdout_project_ids']);
        $this->assertSame(2, $report['training_counts']['test_database_projects']);
        $this->assertContains('fresh_independent_holdout', $report['activation_checks']['cost_model']['failed_requirements']);
        $this->assertFalse($report['steps']['5_validation']['test_used_for_selection']);
        foreach ($report['selected_candidate']['cross_validation']['folds'] as $fold) {
            $this->assertEmpty(array_intersect($fold['test_project_ids'], [9, 10]));
            $this->assertEmpty(array_intersect($fold['test_project_ids'], $fold['training_project_ids']));
        }
        $pair = (new CostModelStore($report['candidate_path'], 10))->load();
        $service = new MLService($report['candidate_path']);
        $row = (object) $source['cohort']['records'][8];
        $vector = (new \ReflectionMethod($service, 'rowToFeatures'))->invoke($service, $row, $pair['metadata']['transformer']['feature_names']);
        $served = (new \ReflectionMethod($service, 'predictionFeatureVector'))->invoke($service,
            [$row->budget, $row->duration_months, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $pair['metadata']['transformer']['feature_names']);
        $this->assertSame($vector, $served);
        // A changed holdout with unchanged training/dummy inputs must select the same settings.
        foreach ([8, 9] as $index) {
            $source['cohort']['records'][$index]['actual_cost'] *= 2;
        }
        $manifest = json_decode(file_get_contents($path.'/manifest.json'), true);
        $manifest['database_fingerprint'] = ProjectCostAugmentationDataset::fingerprint(collect($source['cohort']['records'])->map(fn ($row) => (object) $row));
        file_put_contents($path.'/manifest.json', json_encode($manifest));
        file_put_contents($sourcePath, gzencode(json_encode($source)));
        $second = (new ProjectCostOptimizationService($modelPath))->run($sourcePath);
        $this->assertSame($report['selected_candidate'], $second['selected_candidate']);
        $this->assertSame($report['steps']['4_detection'], $second['steps']['4_detection']);
        $this->assertNotSame($report['evaluation']['mean_absolute_error'], $second['evaluation']['mean_absolute_error']);
        $summary = (new MLService($modelPath, false))->getOptimizationSummary();
        $this->assertArrayNotHasKey('candidate_path', $summary);
        $this->assertSame(2, $summary['training_counts']['test_database_projects']);
    }

    public function test_budget_ratio_target_is_converted_back_to_pesos_in_evaluation_and_serving(): void
    {
        $path = $this->directory().'/ratio.phpml';
        $service = new class($path, false) extends MLService
        {
            public function fitRatio(): array
            {
                $this->useBudgetRatioTarget = true;

                return $this->buildLeastSquaresModel(collect([100000, 200000, 300000, 400000])->map(fn ($budget) => (object) ['budget' => $budget, 'actual_cost' => $budget * 1.1]), ['budget']);
            }
        };
        [$model, $transformer] = $service->fitRatio();
        $this->assertSame('final_cost_fraction_of_budget', $transformer['target_scaling']);
        (new CostModelStore($path, 10))->save($model, ['schema_version' => 10, 'model_type' => 'least_squares_linear_regression',
            'transformer' => $transformer, 'prediction_target' => 'final_cost']);
        $restored = new MLService($path);
        $this->assertEqualsWithDelta(550000, $restored->predict([500000, 3, 5, 0, 0, 0]), .01);
        $evaluation = (new \ReflectionMethod($restored, 'evaluateModel'))->invoke($restored, $model, $transformer,
            collect([(object) ['project_id' => 1, 'budget' => 500000, 'actual_cost' => 550000]]));
        $this->assertEqualsWithDelta(0, $evaluation['mean_absolute_error'], .01);
        $this->assertSame(1, $evaluation['overrun_detection']['any_overrun']['classification_counts']['tp']);
    }

    public function test_mae_scale_uses_one_final_cost_per_project_and_never_selects_the_more_favorable_mean(): void
    {
        $service = new MLService(loadModel: false);
        $calculate = new \ReflectionMethod($service, 'calculateMetrics');
        $metrics = $calculate->invoke($service, [110, 110, 110, 310, 510], [100, 100, 100, 300, 500],
            [150, 150, 150, 350, 550], collect([1, 1, 1, 2, 3])->map(fn ($id) => (object) ['project_id' => $id, 'budget' => 550]));
        $this->assertSame(300.0, $metrics['target_median']);
        $this->assertSame(15.0, $metrics['mae_target']['maximum_pesos']);
        $this->assertSame(3.3333, $metrics['mae_target']['actual_percent']);
        $outliers = $calculate->invoke($service, [110, 210, 310, 1000010], [100, 200, 300, 1000000], [100, 200, 300, 1000000]);
        $this->assertSame(250.0, $outliers['target_median']);
        $this->assertSame(250150.0, $outliers['target_mean']);
        $this->assertSame(4.0, $outliers['mae_target']['actual_percent']);
        $this->assertNotSame($outliers['mean_absolute_percentage_error'], $outliers['mae_target']['actual_percent']);
    }
}
