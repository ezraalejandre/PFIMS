<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use App\Services\AutomaticModelRetraining;
use App\Services\BudgetHistoryService;
use App\Services\InventoryCostAllocator;
use App\Services\MLService;
use App\Services\ProjectCostDataQualityService;
use App\Services\ProjectCostFeatureBuilder;
use App\Services\ProjectCostPresentationPlan;
use App\Services\ProjectCostPresentationService;
use App\Services\ProjectCostSnapshotService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Phpml\Regression\LeastSquares;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Unit\ProjectCostPresentationPlanTest;

class MLImprovementTest extends TestCase
{
    protected string $modelPath;

    public function test_training_readiness_audit_does_not_create_training_evidence_or_change_records(): void
    {
        $before = DB::table('project_tbl')->get()->toJson();
        $report = app(ProjectCostDataQualityService::class)->trainingReadiness();
        $this->assertTrue($report['read_only']);
        $this->assertFalse($report['database_changed']);
        $this->assertFalse($report['model_changed']);
        $this->assertFalse($report['independent_holdout_reserved']);
        $this->assertSame(0, $report['operational_not_in_saved_evaluations']['projects']);
        $this->assertSame($before, DB::table('project_tbl')->get()->toJson());
    }

    protected function presentationSchema(): void
    {
        Schema::table('project_tbl', function (Blueprint $table) {
            $table->string('client_name')->nullable();
            $table->string('project_manager')->nullable();
        });
        Schema::table('inventory_item_tbl', function (Blueprint $table) {
            $table->integer('inventory_category_id')->nullable();
            $table->integer('supplier_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
        });
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->string('expense_description')->nullable();
            $table->string('remarks')->nullable();
            $table->integer('inventory_transaction_id')->nullable();
            $table->string('entry_kind')->nullable();
            $table->timestamps();
        });
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->string('movement_reason')->nullable();
            $table->dateTime('recorded_at')->nullable();
        });
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('in_transaction_id');
            $table->integer('out_transaction_id');
            $table->integer('project_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('allocated_amount', 14, 2);
            $table->string('valuation_status');
            $table->dateTime('allocated_at');
        });
        foreach (['2026_09_28_000001_add_activity_to_project_cost_snapshots.php',
            '2026_10_02_000001_create_project_budget_history.php',
            '2026_10_02_000002_add_capture_quality_to_project_cost_snapshots.php',
            '2026_10_02_000003_add_recent_costs_to_project_cost_snapshots.php',
            '2026_10_02_000004_create_ml_presentation_batches_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        DB::table('fin_expense_category_tbl')->insert(['category_code' => 'TRANSPORTATION_EXPENSES',
            'category_name' => 'Transportation', 'classification' => 'direct']);
        DB::table('inventory_item_tbl')->insert([
            ['item_id' => 1, 'item_name' => 'Cement', 'current_stock' => 50],
            ['item_id' => 2, 'item_name' => 'Steel', 'current_stock' => 30],
        ]);
    }

    public function test_presentation_batch_is_reconciled_idempotent_and_preserves_existing_stock(): void
    {
        Carbon::setTestNow('2026-10-02 21:00:00');
        $this->presentationSchema();
        [$references, $items] = ProjectCostPresentationPlanTest::inputs();
        $plan = app(ProjectCostPresentationPlan::class)->build($references, $items, 6);
        $stock = DB::table('inventory_item_tbl')->whereIn('item_id', [1, 2])->get()->toJson();
        $service = app(ProjectCostPresentationService::class);
        $manifest = $service->apply($plan);
        $this->assertSame(['within_budget' => 2, 'small_overrun' => 1, 'material_overrun' => 3], $manifest['outcome_counts']);
        $this->assertSame(18, $manifest['snapshot_count']);
        $this->assertFalse($manifest['already_applied']);
        $this->assertTrue($service->apply($plan)['already_applied']);
        $this->assertSame(6, DB::table('project_tbl')->count());
        $this->assertSame($stock, DB::table('inventory_item_tbl')->whereIn('item_id', [1, 2])->get()->toJson());
        $this->assertSame(0.0, (float) DB::table('inventory_item_tbl')->whereNotIn('item_id', [1, 2])->sum('current_stock'));
        foreach ($manifest['records']['project_tbl']['ids'] as $id) {
            $this->assertTrue(app(ProjectCostDataQualityService::class)->inspect($id)['eligible']);
        }
        $model = new MLService($this->modelPath, false);
        $rows = $this->invokeProtected($model, 'getSnapshotTrainingData');
        $this->assertCount(18, $rows);
        $this->assertSame(['company_inspired_sample'], $rows->pluck('data_source')->unique()->values()->all());
        $this->assertSame(0, $model->getSnapshotReadiness()['finalized_projects']);
        $migration = require database_path('migrations/2026_10_02_000004_create_ml_presentation_batches_table.php');
        try {
            $migration->down();
            $this->fail('Non-empty tracking must be retained.');
        } catch (\RuntimeException) {
            $this->assertTrue(Schema::hasTable('ml_presentation_batches'));
        }
        DB::table('budgets_tbl')->where('project_id', $manifest['records']['project_tbl']['ids'][0])->update(['budget_amount' => 1]);
        $this->expectException(\RuntimeException::class);
        $service->apply($plan);
    }

    public function test_invalid_presentation_project_rolls_back_the_entire_batch(): void
    {
        Carbon::setTestNow('2026-10-02');
        $this->presentationSchema();
        [$references, $items] = ProjectCostPresentationPlanTest::inputs();
        $plan = app(ProjectCostPresentationPlan::class)->build($references, $items, 6);
        $plan['projects'][2]['waves'][0]['receipt_date'] = '2030-01-01';
        try {
            app(ProjectCostPresentationService::class)->apply($plan);
            $this->fail('Invalid receipt must prevent all imports.');
        } catch (\RuntimeException) {
            foreach (['project_tbl', 'budgets_tbl', 'fin_expense_tbl', 'inventory_transaction_tbl',
                'inventory_cost_allocation_tbl', 'ml_project_cost_snapshots', 'ml_presentation_batches'] as $table) {
                $this->assertSame(0, DB::table($table)->count());
            }
        }
    }

    public function test_presentation_material_history_remains_compatible_with_future_fifo_allocations(): void
    {
        Carbon::setTestNow('2026-10-02');
        $this->presentationSchema();
        [$references, $items] = ProjectCostPresentationPlanTest::inputs();
        $plan = app(ProjectCostPresentationPlan::class)->build($references, $items, 6);
        $manifest = app(ProjectCostPresentationService::class)->apply($plan);
        $item = $manifest['records']['inventory_item_tbl']['ids'][0];
        $project = $this->insertProject(['project_name' => 'New ongoing site', 'status' => 'Ongoing',
            'completion_percentage' => 10, 'worker_count' => 4, 'start_date' => '2026-10-01',
            'estimated_end_date' => '2027-02-01'], 10000, 0);
        $receipt = DB::table('inventory_transaction_tbl')->insertGetId(['item_id' => $item, 'quantity' => 1,
            'transaction_type' => 'IN', 'movement_reason' => 'purchase', 'transaction_date' => '2026-10-02']);
        DB::table('fin_expense_tbl')->insert(['fin_category_id' => 1, 'amount' => 285, 'expense_date' => '2026-10-02',
            'entry_kind' => 'inventory_purchase', 'inventory_transaction_id' => $receipt]);
        $withdrawal = DB::table('inventory_transaction_tbl')->insertGetId(['item_id' => $item, 'quantity' => 1,
            'project_id' => $project, 'transaction_type' => 'OUT', 'transaction_date' => '2026-10-02']);
        app(InventoryCostAllocator::class)->allocate($withdrawal);
        $this->assertSame(285.0, (float) DB::table('budgets_tbl')->where('project_id', $project)->value('actual_amount'));
        app(ProjectCostPresentationService::class)->verifyManifest($manifest);
        $this->assertSame(50.0, (float) DB::table('inventory_item_tbl')->where('item_id', 1)->value('current_stock'));
        $this->assertSame(30.0, (float) DB::table('inventory_item_tbl')->where('item_id', 2)->value('current_stock'));
    }

    public function test_candidate_evaluation_is_read_only_and_does_not_replace_the_saved_estimator(): void
    {
        for ($index = 1; $index <= 18; $index++) {
            $this->insertCompletedProject($index);
        }
        $service = new MLService($this->modelPath);
        $beforeModel = hash_file('sha256', $this->modelPath);
        $beforeMetadata = hash_file('sha256', $this->modelPath.'.meta.json');
        $beforeRows = DB::table('budgets_tbl')->orderBy('budget_id')->get()->toJson();
        $beforeSnapshots = DB::table('ml_project_cost_snapshots')->count();
        $report = (new MLService($this->modelPath, false))->evaluateCandidate();
        $this->assertSame('evaluated', $report['status']);
        $this->assertFalse($report['active_model_changed']);
        $this->assertArrayHasKey('any_overrun', $report['evaluation']['overrun_detection']);
        $this->assertArrayHasKey('material_overrun', $report['evaluation']['overrun_detection']);
        foreach ($report['cross_validation']['folds'] as $fold) {
            $this->assertSame([], array_values(array_intersect($report['holdout_project_ids'], array_merge($fold['training_project_ids'], $fold['test_project_ids']))));
        }
        $this->assertSame($beforeModel, hash_file('sha256', $this->modelPath));
        $this->assertSame($beforeMetadata, hash_file('sha256', $this->modelPath.'.meta.json'));
        $this->assertSame($beforeRows, DB::table('budgets_tbl')->orderBy('budget_id')->get()->toJson());
        $this->assertSame($beforeSnapshots, DB::table('ml_project_cost_snapshots')->count());
        $this->artisan('ml:evaluate')->assertExitCode(0);
        $this->assertSame($beforeModel, hash_file('sha256', $this->modelPath));
    }

    public function test_presentation_progress_is_explicit_and_evaluation_reports_do_not_activate_it(): void
    {
        Carbon::setTestNow('2026-10-02 23:00:00');
        $this->presentationSchema();
        [$references, $items] = ProjectCostPresentationPlanTest::inputs();
        $manifest = app(ProjectCostPresentationService::class)->apply(
            app(ProjectCostPresentationPlan::class)->build($references, $items, 24)
        );
        $service = new MLService($this->modelPath);
        $modelHash = hash_file('sha256', $this->modelPath);
        $metadataHash = hash_file('sha256', $this->modelPath.'.meta.json');
        $automatic = $this->invokeProtected($service, 'selectTrainingCohort');
        $this->assertSame('planning_only_baseline', $automatic['strategy']);
        $this->assertFalse($automatic['snapshot_readiness']['eligible']);
        $presentation = $this->invokeProtected($service, 'selectTrainingCohort', 'presentation_progress');
        $this->assertCount(72, $presentation['records']);
        $this->assertSame(0, $presentation['snapshot_readiness']['operational_finalized_projects']);
        $report = $service->evaluateCandidate('presentation_progress');
        $this->assertSame(5, $report['evaluation']['evaluation_projects']);
        $this->assertSame(15, $report['evaluation']['evaluation_observations']);
        $this->assertSame(5, $report['evaluation']['latest_observation_per_project']['evaluation_observations']);
        foreach ($report['feature_selection']['options'] as $option) {
            foreach ($option['cross_validation']['folds'] ?? [] as $fold) {
                $this->assertSame([], array_values(array_intersect($report['holdout_project_ids'], array_merge($fold['training_project_ids'], $fold['test_project_ids']))));
            }
        }
        $service->saveCandidateEvaluationReport($report);
        $this->assertSame('presentation_progress', $service->getCandidateEvaluationReports()['reports']['presentation_progress']['cohort_policy']);
        $this->assertSame($modelHash, hash_file('sha256', $this->modelPath));
        $this->assertSame($metadataHash, hash_file('sha256', $this->modelPath.'.meta.json'));
        app(ProjectCostPresentationService::class)->verifyManifest($manifest);
        File::delete($this->modelPath.'.evaluation.json');
    }

    public function test_rejected_presentation_training_preserves_the_saved_model_bytes(): void
    {
        Carbon::setTestNow('2026-10-02 23:00:00');
        $this->presentationSchema();
        [$references, $items] = ProjectCostPresentationPlanTest::inputs();
        app(ProjectCostPresentationService::class)->apply(
            app(ProjectCostPresentationPlan::class)->build($references, $items, 24)
        );
        $service = new class($this->modelPath) extends MLService
        {
            protected function budgetBaselineComparison(Collection $rows, array $evaluation): array
            {
                return ['model_outperforms_budget_baseline' => false];
            }
        };
        $beforeModel = hash_file('sha256', $this->modelPath);
        $beforeMetadata = hash_file('sha256', $this->modelPath.'.meta.json');
        $this->assertFalse($service->train('presentation_progress'));
        $this->assertSame($beforeModel, hash_file('sha256', $this->modelPath));
        $this->assertSame($beforeMetadata, hash_file('sha256', $this->modelPath.'.meta.json'));
    }

    public function test_budget_normalized_remaining_target_has_identical_currency_outputs_in_serving_and_evaluation(): void
    {
        $service = new MLService($this->modelPath, false);
        $records = collect(range(1, 6))->map(fn ($index) => (object) [
            'snapshot_id' => $index, 'project_id' => $index, 'budget' => $index * 1000,
            'actual_cost' => $index * 250, 'fin_total_expense' => $index * 100,
        ]);
        [$model, $transformer] = $this->invokeProtected($service, 'buildLeastSquaresModel', $records, ['budget']);
        $this->assertSame('remaining_cost_fraction_of_budget', $transformer['target_scaling']);
        (new \ReflectionProperty($service, 'model'))->setValue($service, $model);
        (new \ReflectionProperty($service, 'metadata'))->setValue($service, [
            'model_source' => 'sample_trained_model', 'prediction_strategy' => 'progress_snapshot_model',
            'prediction_target' => 'remaining_cost_then_add_recorded_spend', 'transformer' => $transformer,
        ]);
        $served = $service->predict([7000, 6, 5, 10, 700, 0, 700], ['cost_coverage_complete' => true]);
        $this->assertEqualsWithDelta(2450, $served, 0.001);
        $evaluation = $this->invokeProtected($service, 'evaluateModel', $model, $transformer, collect([
            (object) ['snapshot_id' => 7, 'project_id' => 7, 'budget' => 7000, 'actual_cost' => 1750, 'fin_total_expense' => 700],
        ]));
        $this->assertSame(0.0, $evaluation['mean_absolute_error']);
        $this->assertSame('demo_only', $service->getLastPredictionSupport()['support_level']);
        $this->assertFalse($service->getLastPredictionSupport()['prediction_usable']);
    }

    public function test_malformed_candidate_evidence_cannot_break_metrics_or_select_an_unknown_cohort(): void
    {
        $service = new MLService($this->modelPath, false);
        File::put($this->modelPath.'.evaluation.json', '{invalid');
        $this->assertNull($service->getCandidateEvaluationReports());
        File::delete($this->modelPath.'.evaluation.json');
        $this->expectException(\InvalidArgumentException::class);
        $service->evaluateCandidate('unknown_policy');
    }

    public function test_evaluation_endpoint_is_admin_only_and_never_creates_an_active_estimator(): void
    {
        $this->postJson('/api/ml/evaluate', ['cohort' => 'planning'])->assertUnauthorized();
        $this->actingAs($this->user('operations'))->postJson('/api/ml/evaluate', ['cohort' => 'planning'])->assertForbidden();
        $this->actingAs($this->user('admin'))->postJson('/api/ml/evaluate', ['cohort' => 'unknown'])->assertUnprocessable();
        $service = new MLService(loadModel: false);
        $path = (new \ReflectionProperty($service, 'modelPath'))->getValue($service);
        $modelHash = File::exists($path) ? hash_file('sha256', $path) : null;
        $metaHash = File::exists($path.'.meta.json') ? hash_file('sha256', $path.'.meta.json') : null;
        $this->postJson('/api/ml/evaluate', ['cohort' => 'planning'])->assertOk()
            ->assertJsonPath('active_model_changed', false)
            ->assertJsonPath('report.status', 'insufficient_projects');
        $this->assertSame($modelHash, File::exists($path) ? hash_file('sha256', $path) : null);
        $this->assertSame($metaHash, File::exists($path.'.meta.json') ? hash_file('sha256', $path.'.meta.json') : null);
        $this->assertSame('planning', $service->getCandidateEvaluationReports()['reports']['planning']['cohort_policy']);
        File::delete($path.'.evaluation.json');
    }

    public function test_temporal_cross_validation_keeps_all_observations_for_a_project_together(): void
    {
        $records = collect();
        for ($project = 1; $project <= 12; $project++) {
            foreach ([10, 50, 90] as $completion) {
                $records->push((object) ['project_id' => $project, 'budget' => 1000 + $project * 100,
                    'actual_cost' => 500 + $project * 100, 'duration_months' => 6,
                    'completion_percentage' => $completion,
                    'completed_at' => Carbon::create(2024, 1, 1)->addDays($project)->toDateString()]);
            }
        }
        $service = new MLService($this->modelPath, false);
        $cv = $this->invokeProtected($service, 'kFoldCrossValidation', $records, ['budget', 'duration_months']);
        $seen = [];
        foreach ($cv['folds'] as $fold) {
            $this->assertSame(count($fold['training_project_ids']) * 3, $fold['training_samples']);
            $this->assertSame(count($fold['test_project_ids']) * 3, $fold['test_samples']);
            $this->assertSame([], array_values(array_intersect($fold['test_project_ids'], $seen)));
            $this->assertTrue(strcmp($fold['latest_training_completion'], $fold['earliest_test_completion']) < 0);
            $seen = array_merge($seen, $fold['test_project_ids']);
        }
        $this->assertCount(5, $cv['folds']);
    }

    public function test_remaining_cost_serving_and_evaluation_share_the_spend_floor(): void
    {
        foreach ([0.0, -300.0, 500.0] as $remaining) {
            $service = new MLService($this->modelPath, false);
            $model = new LeastSquares;
            $model->train([[0.0], [1.0], [2.0]], [$remaining, $remaining, $remaining]);
            $transformer = ['feature_names' => ['budget'], 'selected_feature_indexes' => [0],
                'ranges' => [['min' => 1000, 'max' => 2000]]];
            (new \ReflectionProperty($service, 'model'))->setValue($service, $model);
            (new \ReflectionProperty($service, 'metadata'))->setValue($service, [
                'model_source' => 'real_trained_model', 'prediction_strategy' => 'progress_snapshot_model',
                'prediction_target' => 'remaining_cost_then_add_recorded_spend', 'transformer' => $transformer]);
            $served = $service->predict([1000, 1, 1, 50, 0, 0, 1200], ['cost_coverage_complete' => true]);
            $expected = max(1200, 1200 + $remaining);
            $this->assertEqualsWithDelta($expected, $served, 0.000001);
            $this->assertSame('real_trained_model', $service->getLastPredictionSource());
            $this->assertSame($remaining < 0, $service->getLastForecastCalculation()['spend_floor_applied']);
            $metrics = $this->invokeProtected($service, 'evaluateModel', $model, $transformer, collect([
                (object) ['snapshot_id' => 1, 'budget' => 1000, 'fin_total_expense' => 1200, 'actual_cost' => 500],
            ]));
            $this->assertEqualsWithDelta(abs(1700 - $served), $metrics['mean_absolute_error'], 0.000001);
            $this->assertSame((int) ($remaining < 0), $metrics['spend_floor_count']);
        }
    }

    public function test_failed_model_fallback_cannot_inherit_validated_support(): void
    {
        $service = new MLService($this->modelPath, false);
        (new \ReflectionProperty($service, 'model'))->setValue($service, new LeastSquares);
        (new \ReflectionProperty($service, 'metadata'))->setValue($service, [
            'model_source' => 'real_trained_model', 'prediction_strategy' => 'planning_only_baseline',
            'budget_baseline_comparison' => ['model_outperforms_budget_baseline' => true],
            'model_comparison' => ['production_model_is_best_option' => true],
        ]);
        $service->predict([1000, 1, 1, 0, 0, 0]);
        $this->assertSame('rule_based_fallback', $service->getLastPredictionSource());
        $support = $this->invokeProtected($service, 'predictionSupport', [1000, 1, 1, 0, 0, 0, 0]);
        $this->assertFalse($support['prediction_usable']);
        $this->assertSame('fallback_only', $support['support_level']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->modelPath = storage_path('framework/testing/ml-'.uniqid('', true).'.phpml');
        File::ensureDirectoryExists(dirname($this->modelPath));
        $this->createSchema();
        $modelPath = $this->modelPath;
        $this->app->singleton(MLService::class, fn () => new MLService($modelPath));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::delete([$this->modelPath, $this->modelPath.'.meta.json', $this->modelPath.'.lock']);
        parent::tearDown();
    }

    public function test_ml_routes_require_authentication_and_retraining_requires_an_admin(): void
    {
        $this->getJson('/api/ml/status')->assertUnauthorized();
        $this->getJson('/api/ml/analytics/dashboard')->assertUnauthorized();
        $this->postJson('/api/ml/predict/cost', [])->assertUnauthorized();

        $operations = $this->user('operations');
        $this->actingAs($operations)->get('/ml-dashboard-test')->assertForbidden();
        $this->actingAs($operations)->get('/ml-dashboard-test?section=budget-comparison')->assertForbidden();
        $this->actingAs($operations)->get('/ml-dashboard-test?section=material-projection')->assertOk();
        $this->actingAs($operations)->getJson('/api/ml/status')->assertForbidden();
        $this->actingAs($operations)->getJson('/api/ml/analytics/dashboard')->assertForbidden();
        $this->actingAs($operations)->getJson('/api/ml/analytics/budget-variance')->assertForbidden();
        $this->actingAs($operations)->getJson('/api/ml/prediction-projects')->assertForbidden();
        $this->actingAs($operations)->postJson('/api/ml/predict/cost', [])->assertForbidden();
        $this->actingAs($operations)
            ->postJson('/api/ml/retrain')
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $accounting = $this->user('accounting');
        $this->actingAs($accounting)->get('/ml-dashboard-test')->assertForbidden();
        $this->actingAs($accounting)->getJson('/api/ml/prediction-projects')->assertForbidden();
        $this->actingAs($accounting)->postJson('/api/ml/predict/cost', [])->assertForbidden();

        $this->actingAs($operations)->getJson('/api/ml/retrain')->assertMethodNotAllowed();
        $this->actingAs($operations)->getJson('/api/ml/predict/cost')->assertMethodNotAllowed();
        $this->getJson('/ml-debug')->assertNotFound();

        $admin = $this->user('admin');
        $adminDashboard = $this->actingAs($admin)
            ->get('/ml-dashboard-test?embedded=1')
            ->assertOk()
            ->assertSee('embedded-ml-dashboard', false)
            ->assertSee('class="projects-page analytics-module-page"', false)
            ->assertSee('class="top-header"', false)
            ->assertSee('class="sidebar"', false)
            ->assertDontSee('<h1>PROJECTS</h1>', false)
            ->assertDontSee('data-auto-refresh="false"', false)
            ->assertSee('css/centralized-predictive-analytics.css', false)
            ->assertSee('js/ml-performance.js', false)
            ->assertDontSee('id="performanceSource"', false)
            ->assertDontSee('id="performanceWeighting"', false)
            ->assertDontSee('id="performanceThreshold"', false)
            ->assertDontSee('id="refreshPerformance"', false)
            ->assertSee('Average percentage error (MAPE)', false)
            ->assertDontSee('Avg. Closeness', false)
            ->assertDontSee('DECISION SUPPORT', false)
            ->assertSee('Project Cost Prediction', false)
            ->assertSee('class="prediction-row analytics-tab-content"', false)
            ->assertSee('id="predictionProject"', false)
            ->assertDontSee('id="budget"', false)
            ->assertDontSee('id="statsGrid"', false)
            ->assertDontSee('Model Accuracy (holdout)', false)
            ->assertDontSee('id="retrainConfirmModal"', false)
            ->assertDontSee('Retrain prediction model?', false)
            ->assertDontSee("confirm('Retraining", false)
            ->assertSee('class="card analytics-panel model-performance-card" aria-labelledby="modelPerformanceTitle">', false)
            ->assertSee('Management diagnostic', false)
            ->assertSee('Recommended action', false)
            ->assertSee('Projected budget savings', false)
            ->assertSee('Projected budget overrun', false)
            ->assertSee('How to read this:', false)
            ->assertSee('Prediction completed.', false)
            ->assertDontSee('The business diagnostic is ready.', false)
            ->assertDontSee('function refreshData()', false);

        $adminDashboard->assertSeeInOrder([
            'Project Cost Prediction',
            'Model Performance',
            '30-Day Material Stock Projection',
            'Budget-Spending Comparison',
        ]);

        $this->actingAs($accounting)
            ->get('/ml-dashboard-test?section=budget-comparison&embedded=1')
            ->assertOk()
            ->assertDontSee('id="predictionProject"', false)
            ->assertDontSee('Project Cost Prediction', false)
            ->assertDontSee('id="retrainConfirmModal"', false);
        $this->actingAs($accounting)->get('/ml-dashboard-test?section=material-projection')->assertForbidden();

        $this->actingAs($admin)->get('/ml-dashboard-test?section=budget-comparison')
            ->assertOk()
            ->assertSee('class="finance-page analytics-module-page"', false)
            ->assertSee('data-parent-module="finance"', false)
            ->assertDontSee('<h1>FINANCE</h1>', false)
            ->assertSee('id="budgetVarianceProject"', false)
            ->assertDontSee('Import Expenses', false)
            ->assertDontSee('Report View', false);

        $this->actingAs($this->user('operations'))->get('/ml-dashboard-test?section=material-projection')
            ->assertOk()
            ->assertSee('class="inventory-page analytics-module-page"', false)
            ->assertSee('data-parent-module="inventory"', false)
            ->assertDontSee('<h1>INVENTORY</h1>', false)
            ->assertDontSee('Import CSV/XLSX', false)
            ->assertDontSee('class="inventory-tabs"', false);
    }

    public function test_cost_prediction_validates_every_input_and_keeps_compatible_response_fields(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->postJson('/api/ml/predict/cost', [
            'budget' => 0,
            'duration' => 1.5,
            'workers' => 0,
            'completion' => 101,
            'material_cost' => -1,
            'labor_cost' => 10000000000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'budget', 'duration', 'workers', 'completion', 'material_cost', 'labor_cost',
            ]);

        $response = $this->actingAs($admin)->postJson('/api/ml/predict/cost', [
            'budget' => 1000000,
            'duration' => 6,
            'workers' => 10,
            'completion' => 50,
            'material_cost' => 300000,
            'labor_cost' => 200000,
        ])->assertOk()
            ->assertJsonStructure([
                'success', 'predicted_cost', 'formatted', 'variance', 'variance_percentage',
                'status', 'risk_level', 'business_action', 'prediction_source', 'model_accuracy',
                'model_accuracy_scope', 'warnings', 'input_features', 'prediction_usable',
                'support_level', 'forecast_context', 'status_reason',
            ]);

        $this->assertSame('synthetic_fallback_model', $response->json('prediction_source'));
        $this->assertFalse($response->json('prediction_usable'));
        $this->assertSame('Insufficient evidence', $response->json('risk_level'));
        $this->assertNotSame('On track', $response->json('status'));
        $this->assertIsString($response->json('business_action'));
        $this->assertNotEmpty($response->json('warnings'));

        $this->actingAs($admin)->postJson('/api/ml/predict/cost', [
            'budget' => 1000000,
            'duration' => 6,
            'workers' => 10,
            'completion' => 50,
            'material_cost' => 300000,
            'labor_cost' => 200000,
            'fin_total_expense' => 450000,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['finance_as_of_date']);

        $this->actingAs($admin)->postJson('/api/ml/predict/cost', [
            'budget' => 1000000,
            'duration' => 6,
            'workers' => 10,
            'completion' => 50,
            'material_cost' => 300000,
            'labor_cost' => 200000,
            'fin_total_expense' => 450000,
            'finance_as_of_date' => now()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('input_features.finance_as_of_date', now()->toDateString());
    }

    public function test_budget_variance_endpoint_returns_every_budget_with_a_server_position(): void
    {
        $completedId = $this->insertProject([
            'project_name' => 'Closed-out roadworks',
            'status' => 'Completed',
            'completion_percentage' => 100,
            'actual_end_date' => '2025-08-01',
        ], 100000, 90000);
        $pendingId = $this->insertProject([
            'project_name' => 'Pending drainage works',
            'status' => 'Pending',
            'completion_percentage' => 0,
        ], 50000, 60000);

        $response = $this->actingAs($this->user('admin'))
            ->getJson('/api/ml/analytics/budget-variance')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $this->assertCount(2, $rows);
        $this->assertSame(['within', 'over'], $rows->sortBy('project_id')->pluck('position')->values()->all());
        $this->assertTrue($rows->contains('project_id', $completedId));
        $this->assertTrue($rows->contains('project_id', $pendingId));
    }

    public function test_project_prediction_options_derive_inputs_from_current_project_and_finance_records(): void
    {
        $projectId = $this->insertProject([
            'project_name' => 'Active Warehouse',
            'start_date' => '2025-01-01',
            'estimated_end_date' => '2025-07-01',
            'actual_end_date' => null,
            'worker_count' => 12,
            'completion_percentage' => 40,
            'status' => 'In Progress',
        ], 1200000, 0);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => $projectId, 'fin_category_id' => 1, 'amount' => 250000, 'expense_date' => '2025-03-01'],
            ['project_id' => $projectId, 'fin_category_id' => 2, 'amount' => 150000, 'expense_date' => '2025-03-15'],
        ]);
        $admin = $this->user('admin');

        $this->actingAs($admin)->getJson('/api/ml/prediction-projects')
            ->assertOk()
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.project_id', $projectId)
            ->assertJsonPath('projects.0.project_name', 'Active Warehouse')
            ->assertJsonPath('projects.0.budget', 1200000)
            ->assertJsonPath('projects.0.duration', 6)
            ->assertJsonPath('projects.0.fin_total_expense', 400000)
            ->assertJsonPath('projects.0.finance_as_of_date', '2025-03-15');

        $this->actingAs($admin)->postJson('/api/ml/predict/cost', [
            'project_id' => $projectId,
            'budget' => 1,
            'workers' => 1,
        ])->assertOk()
            ->assertJsonPath('input_features.project_id', $projectId)
            ->assertJsonPath('input_features.project_name', 'Active Warehouse')
            ->assertJsonPath('input_features.budget', 1200000)
            ->assertJsonPath('input_features.worker_count', 12)
            ->assertJsonPath('input_features.fin_total_expense', 400000)
            ->assertJsonPath('budget_context.budget_basis', 'latest_recorded_budget')
            ->assertJsonPath('budget_context.original_budget_amount', null)
            ->assertJsonPath('overrun_outcomes.material_overrun_threshold_percent', 5)
            ->assertJsonPath('overrun_outcomes.original_budget.any_overrun', null);
    }

    public function test_snapshots_keep_the_budget_version_known_at_capture_after_a_revision(): void
    {
        Carbon::setTestNow('2025-04-10 09:30:00');
        $projectId = $this->insertProject([
            'project_name' => 'Budget version history', 'start_date' => '2025-01-01',
            'estimated_end_date' => '2025-07-01', 'actual_end_date' => null,
            'worker_count' => 12, 'completion_percentage' => 25, 'status' => 'In Progress',
        ], 1000000, 0);
        $migration = require database_path('migrations/2026_10_02_000001_create_project_budget_history.php');
        $migration->up();
        $this->mock(AutomaticModelRetraining::class, fn ($mock) => $mock->shouldReceive('afterDataChange')->zeroOrMoreTimes());
        $snapshots = app(ProjectCostSnapshotService::class);
        $this->assertTrue($snapshots->capture($projectId, 'before_revision'));
        $first = DB::table('ml_project_cost_snapshots')->where('capture_reason', 'before_revision')->first();
        $this->assertNotNull($first->budget_history_id);
        $this->assertNull($first->original_budget_amount);

        Carbon::setTestNow('2025-04-11 09:30:00');
        $budget = Budget::where('project_id', $projectId)->firstOrFail();
        app(BudgetHistoryService::class)->revise($budget, ['budget_amount' => 1200000], 'Scope revision');
        $this->assertTrue($snapshots->capture($projectId, 'after_revision'));
        $second = DB::table('ml_project_cost_snapshots')->where('capture_reason', 'after_revision')->first();
        $this->assertNotSame($first->budget_history_id, $second->budget_history_id);
        $this->assertSame(1000000.0, (float) DB::table('ml_project_cost_snapshots')->where('snapshot_id', $first->snapshot_id)->value('planned_budget'));
        $this->assertSame(1200000.0, (float) $second->planned_budget);
        $this->assertSame('latest_recorded_budget', $second->budget_basis);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('ml_project_cost_snapshots', 'budget_history_id'));
        $this->assertDatabaseCount('ml_project_cost_snapshots', 2);
    }

    public function test_snapshot_capture_is_append_only_and_uses_the_planned_schedule(): void
    {
        Carbon::setTestNow('2025-04-10 09:30:00.123456');
        $projectId = $this->insertProject([
            'project_name' => 'Genuine progress history',
            'start_date' => '2025-01-01',
            'estimated_end_date' => '2025-07-01',
            'actual_end_date' => null,
            'worker_count' => 12,
            'completion_percentage' => 25,
            'status' => 'In Progress',
        ], 1000000, 0);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $projectId,
            'fin_category_id' => 1,
            'amount' => 120000,
            'project_cost_component' => 'material',
            'expense_date' => '2025-04-01',
        ]);

        $snapshots = new ProjectCostSnapshotService;
        $this->assertTrue($snapshots->capture($projectId, 'first_observation'));

        DB::table('project_tbl')->where('project_id', $projectId)->update([
            'completion_percentage' => 40,
            // A later actual completion date must never replace planned duration.
            'actual_end_date' => '2025-10-01',
        ]);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $projectId,
            'fin_category_id' => 2,
            'amount' => 80000,
            'project_cost_component' => 'labor',
            'expense_date' => '2025-04-10',
        ]);
        Carbon::setTestNow('2025-04-10 15:45:00.654321');
        $this->assertTrue($snapshots->capture($projectId, 'second_observation'));

        $rows = DB::table('ml_project_cost_snapshots')->where('project_id', $projectId)
            ->orderBy('snapshot_id')->get();

        $this->assertCount(2, $rows, 'Multiple genuine observations on one date must be appended, not overwritten.');
        $this->assertSame('first_observation', $rows[0]->capture_reason);
        $this->assertSame(25.0, (float) $rows[0]->completion_percentage);
        $this->assertSame(120000.0, (float) $rows[0]->cumulative_total_expense);
        $this->assertSame(120000.0, (float) $rows[0]->cumulative_material_expense);
        $this->assertSame('second_observation', $rows[1]->capture_reason);
        $this->assertSame(40.0, (float) $rows[1]->completion_percentage);
        $this->assertSame(200000.0, (float) $rows[1]->cumulative_total_expense);
        $this->assertSame(6, (int) $rows[0]->planned_duration_months);
        $this->assertSame(6, (int) $rows[1]->planned_duration_months);
        $this->assertNotSame($rows[0]->captured_at, $rows[1]->captured_at);
    }

    public function test_snapshot_activity_uses_only_transactions_known_at_capture_time(): void
    {
        (require database_path('migrations/2026_09_28_000001_add_activity_to_project_cost_snapshots.php'))->up();
        Carbon::setTestNow('2025-04-10 10:00:00');
        $projectId = $this->insertProject([
            'project_name' => 'Activity evidence',
            'start_date' => '2025-01-01',
            'estimated_end_date' => '2025-07-01',
            'actual_end_date' => null,
            'worker_count' => 8,
            'completion_percentage' => 25,
            'status' => 'In Progress',
        ], 100000, 0);
        foreach ([['2025-04-09', 250], ['2025-03-01', 500], ['2025-04-11', 700]] as [$date, $amount]) {
            DB::table('fin_expense_tbl')->insert([
                'project_id' => $projectId, 'fin_category_id' => 1,
                'amount' => $amount, 'project_cost_component' => 'material', 'expense_date' => $date,
            ]);
        }
        foreach ([['2025-04-08', 3], ['2025-04-11', 9]] as [$date, $quantity]) {
            DB::table('inventory_transaction_tbl')->insert([
                'project_id' => $projectId, 'item_id' => 1,
                'transaction_type' => 'OUT', 'quantity' => $quantity, 'transaction_date' => $date,
            ]);
        }

        $this->assertTrue((new ProjectCostSnapshotService)->capture($projectId, 'activity_check'));
        $row = DB::table('ml_project_cost_snapshots')->where('project_id', $projectId)->first();
        $this->assertSame(1, (int) $row->direct_expense_count_7d);
        $this->assertSame(1, (int) $row->direct_expense_count_30d);
        $this->assertSame(250.0, (float) $row->direct_expense_amount_30d);
        $this->assertSame(1, (int) $row->stock_out_count_30d);
        $this->assertSame(3.0, (float) $row->stock_out_quantity_30d);
    }

    public function test_automatic_retraining_hook_captures_each_affected_project_before_coalescing_retraining(): void
    {
        $projectId = $this->insertProject([
            'project_name' => 'Hooked Project',
            'start_date' => '2026-01-01',
            'estimated_end_date' => '2026-07-01',
            'worker_count' => 8,
            'completion_percentage' => 20,
            'status' => 'Ongoing',
        ], 500000, 0);

        $service = app(AutomaticModelRetraining::class);
        $service->afterDataChange([$projectId, $projectId]);

        $this->assertSame(1, DB::table('ml_project_cost_snapshots')->where('project_id', $projectId)->count());
    }

    public function test_completion_labels_only_existing_genuine_snapshots_without_inventing_history(): void
    {
        Carbon::setTestNow('2025-02-01 08:00:00');
        $projectId = $this->insertProject([
            'project_name' => 'Observed project',
            'start_date' => '2025-01-01',
            'estimated_end_date' => '2025-05-01',
            'actual_end_date' => null,
            'worker_count' => 8,
            'completion_percentage' => 20,
            'status' => 'In Progress',
        ], 500000, 0);
        $snapshots = new ProjectCostSnapshotService;
        $this->assertTrue($snapshots->capture($projectId, 'progress_recorded'));

        DB::table('project_tbl')->where('project_id', $projectId)->update([
            'completion_percentage' => 100,
            'status' => 'Completed',
            'actual_end_date' => '2025-06-15',
        ]);
        DB::table('budgets_tbl')->where('project_id', $projectId)->update(['actual_amount' => 575000]);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $projectId,
            'fin_category_id' => 1,
            'amount' => 560000,
            'project_cost_component' => 'labor',
            'expense_date' => '2025-06-15',
        ]);
        Carbon::setTestNow('2025-06-16 10:00:00');
        $this->assertTrue($snapshots->capture($projectId, 'project_completed'));

        $rows = DB::table('ml_project_cost_snapshots')->where('project_id', $projectId)
            ->orderBy('snapshot_id')->get();

        $this->assertCount(2, $rows, 'Completion may add the current observation but must not synthesize intermediate history.');
        $this->assertSame(20.0, (float) $rows[0]->completion_percentage);
        $this->assertSame(0.0, (float) $rows[0]->cumulative_total_expense);
        $this->assertSame(560000.0, (float) $rows[0]->final_actual_cost, 'Authoritative expenses must win over stale budget actuals.');
        $this->assertSame(560000.0, (float) $rows[1]->final_actual_cost);
        $this->assertSame(560000.0, (float) $rows[1]->cumulative_material_expense, 'Category mapping must stay consistent with training even when the legacy component disagrees.');
        $this->assertNotNull($rows[0]->finalized_at);
        $this->assertNotNull($rows[1]->finalized_at);

        DB::table('fin_expense_tbl')->insert([
            'project_id' => $projectId,
            'fin_category_id' => 2,
            'amount' => 40000,
            'project_cost_component' => 'material',
            'expense_date' => '2025-06-17',
        ]);
        Carbon::setTestNow('2025-06-17 12:00:00');
        $this->assertTrue($snapshots->capture($projectId, 'closeout_corrected'));
        $corrected = DB::table('ml_project_cost_snapshots')->where('project_id', $projectId)
            ->orderBy('snapshot_id')->get();
        $this->assertCount(3, $corrected);
        $this->assertSame([600000.0], $corrected->pluck('final_actual_cost')->map(fn ($cost) => (float) $cost)->unique()->values()->all());
        $this->assertSame(0.0, (float) $corrected[0]->cumulative_total_expense, 'Relabeling must not rewrite the historical feature values.');
        $this->assertSame(560000.0, (float) $corrected[1]->cumulative_total_expense);
        $this->assertSame(600000.0, (float) $corrected[2]->cumulative_total_expense);

        $unobservedProjectId = $this->insertProject([
            'project_name' => 'Never observed before completion',
            'start_date' => '2025-01-01',
            'estimated_end_date' => '2025-05-01',
            'actual_end_date' => '2025-06-01',
            'worker_count' => 8,
            'completion_percentage' => 100,
            'status' => 'Completed',
        ], 400000, 450000);
        $this->assertTrue($snapshots->capture($unobservedProjectId, 'project_completed'));
        $this->assertSame(1, DB::table('ml_project_cost_snapshots')->where('project_id', $unobservedProjectId)->count());
    }

    public function test_training_uses_only_deduplicated_verified_completed_projects_and_holdout_metrics(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            $this->insertCompletedProject($index);
        }

        // A second budget row must not duplicate the project in training.
        DB::table('budgets_tbl')->insert([
            'project_id' => 1,
            'budget_amount' => 145000,
            'actual_amount' => 152000,
        ]);

        $this->insertProject([
            'project_name' => 'Active project',
            'start_date' => '2025-01-01',
            'actual_end_date' => null,
            'worker_count' => 20,
            'completion_percentage' => 70,
            'status' => 'Ongoing',
        ], 500000, 300000);

        $this->insertProject([
            'project_name' => 'Unverified completion',
            'start_date' => '2024-01-01',
            'actual_end_date' => '2024-08-01',
            'worker_count' => 20,
            'completion_percentage' => 80,
            'status' => 'Completed',
        ], 600000, 550000);

        $service = new MLService($this->modelPath);
        $metrics = $service->getModelMetrics();

        $this->assertSame('real_trained_model', $metrics['model_source']);
        $this->assertFalse($metrics['uses_synthetic_data']);
        $this->assertSame(12, $metrics['real_samples_available']);
        $this->assertSame(12, $metrics['samples_trained']);
        $this->assertSame(9, $metrics['training_samples']);
        $this->assertSame(3, $metrics['test_samples']);
        $this->assertSame('fixed_grouped_chronological_80_20_holdout', $metrics['evaluation_method']);
        $this->assertIsNumeric($metrics['accuracy']);
        $this->assertIsNumeric($metrics['mean_absolute_error']);
        $this->assertArrayHasKey('precision', $metrics);
        $this->assertArrayHasKey('recall', $metrics);
        $this->assertArrayHasKey('f1_score', $metrics);
        $this->assertStringContainsString('5%', $metrics['classification_definition']);
        $this->assertSame('experimental', $metrics['sample_sufficiency']['level']);
        $comparison = $service->analyzeBudgetVariance();
        // Budget-spending comparison mirrors every recorded Budgets row,
        // including completed projects and historical duplicate revisions.
        $this->assertCount(15, $comparison);
        $this->assertTrue($comparison->contains('project_name', 'Active project'));
        $this->assertTrue($comparison->contains('project_name', 'Unverified completion'));
        $this->assertContains($comparison->first()->position, ['within', 'over']);

        $service->predictProjectCost(999999999, 500, 90000, 20, 500000000, 300000000);
        $this->assertNotEmpty($service->getLastPredictionWarnings());
    }

    public function test_planning_training_uses_estimated_duration_and_excludes_progress_and_expense_inputs(): void
    {
        for ($index = 1; $index <= 10; $index++) {
            $projectId = $this->insertCompletedProject($index);
            DB::table('project_tbl')->where('project_id', $projectId)->update([
                'start_date' => '2024-01-01',
                'estimated_end_date' => '2024-07-01',
                'actual_end_date' => '2025-01-01',
            ]);
        }

        $service = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $cohort = $this->invokeProtected($service, 'selectTrainingCohort');

        $this->assertSame('planning_only_baseline', $cohort['strategy']);
        $this->assertSame(['budget', 'duration_months'], $cohort['feature_names']);
        $this->assertSame([6.0], $cohort['records']->pluck('duration_months')->map(fn ($months) => (float) $months)->unique()->values()->all());
        $this->assertNotEmpty($cohort['records']->pluck('fin_total_expense')->filter(fn ($amount) => (float) $amount > 0));
        $this->assertSame([], array_values(array_intersect(
            ['completion_percentage', 'material_cost', 'labor_cost', 'fin_total_expense'],
            $cohort['feature_names']
        )));
    }

    public function test_model_metadata_compares_splits_models_cv_and_gates_finance_features(): void
    {
        for ($index = 1; $index <= 18; $index++) {
            $projectId = $this->insertCompletedProject($index);
            $this->insertFinanceSignals($projectId);
        }

        $service = new MLService($this->modelPath);
        $metrics = $service->getModelMetrics();
        $metadata = $service->getModelMetadata();

        $this->assertSame('least_squares_linear_regression', $metadata['model_type']);
        $this->assertSame('fixed_grouped_chronological_80_20_holdout', $metrics['evaluation_method']);
        $this->assertSame('predeclared_not_selected_from_test_performance', $metrics['split_selection']['selection_metric']);
        $this->assertStringContainsString('always', $metrics['split_selection']['scoring_rule']);
        $this->assertSame(
            ['fixed_grouped_chronological_80_20_holdout'],
            array_column($metrics['split_selection']['options'], 'method')
        );

        $this->assertSame('expanding_window_grouped_temporal_cross_validation', $metrics['cross_validation']['method']);
        $this->assertSame(5, $metrics['cross_validation']['folds_run']);
        $this->assertIsNumeric($metrics['cross_validation']['average_mean_absolute_error']);
        $this->assertSame('training_partition_only_final_holdout_excluded', $metrics['feature_set']['cross_validation_scope']);
        $cohort = $this->invokeProtected($service, 'selectTrainingCohort');
        $split = $this->invokeProtected($service, 'selectChronologicalSplit', $cohort['records'], $cohort['feature_names']);
        $holdoutIds = $split['test_data']->pluck('project_id')->unique()->all();
        foreach ($metrics['cross_validation']['folds'] as $fold) {
            $this->assertSame([], array_values(array_intersect($fold['training_project_ids'], $fold['test_project_ids'])));
            $this->assertSame([], array_values(array_intersect($holdoutIds, array_merge($fold['training_project_ids'], $fold['test_project_ids']))));
            $this->assertTrue(strcmp($fold['latest_training_completion'], $fold['earliest_test_completion']) < 0);
        }

        $this->assertSame(['budget', 'duration_months'], $metrics['feature_set']['selected_feature_names']);
        $this->assertSame([], $metrics['feature_set']['included_fin_features']);
        $this->assertSame(
            'planning_only_features_selected_until_snapshot_stage_coverage_is_sufficient',
            $metrics['feature_set']['decision']
        );
        $this->assertFalse($metrics['snapshot_readiness']['eligible']);

        $this->assertSame('least_squares_linear_regression', $metrics['model_comparison']['production_model']);
        $this->assertArrayHasKey('support_vector_regression_rbf', $metrics['model_comparison']['models']);
        $this->assertTrue($metrics['model_comparison']['models']['least_squares_linear_regression']['is_production']);
        $this->assertSame(
            'evaluated_in_memory_only_not_serialized_or_deployed',
            $metrics['model_comparison']['models']['support_vector_regression_rbf']['persistence']
        );

        $this->assertArrayHasKey('by_project_size', $metrics['monitoring_segments']);
        $this->assertArrayHasKey('by_project_type', $metrics['monitoring_segments']);
        $firstSizeSegment = collect($metrics['monitoring_segments']['by_project_size'])->first();
        $this->assertArrayHasKey('mean_absolute_percentage_error', $firstSizeSegment);
        $this->assertArrayHasKey('precision', $firstSizeSegment);
        $this->assertArrayHasKey('recall', $firstSizeSegment);
        $this->assertContains('fin_expense_tbl.amount', $metrics['data_capture_policy']['required_fields']);
        $this->assertContains('fin_expense_category_tbl.category_code', $metrics['data_capture_policy']['required_fields']);
        $this->assertSame('ml:retrain', $metrics['retraining_policy']['scheduled_command']);
        $this->assertArrayHasKey('Critical risk', $metrics['risk_business_actions']);
    }

    public function test_model_performance_discloses_current_price_estimates_in_historical_costs(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->unsignedInteger('inventory_transaction_id')->nullable();
            $table->string('entry_kind')->nullable();
            $table->string('remarks')->nullable();
        });
        for ($index = 1; $index <= 10; $index++) {
            $this->insertCompletedProject($index);
        }
        DB::table('fin_expense_tbl')->insert([
            'project_id' => null, 'fin_category_id' => 1, 'amount' => 100,
            'expense_date' => '2025-01-01', 'entry_kind' => 'inventory_purchase',
            'remarks' => 'Historical item-price estimate: quantity × current Unit Price.',
        ]);

        $metrics = (new MLService($this->modelPath))->retrain()['metrics'];
        $this->assertSame('real_trained_model', $metrics['model_source']);
        $this->assertSame(1, $metrics['estimated_historical_purchase_count']);
        $this->assertStringContainsString('estimated historical inventory costs', $metrics['status']);
        $this->assertStringContainsString('current item prices', $metrics['warnings'][0]);
        $this->assertStringContainsString('provisional', $metrics['interpretation']);
    }

    public function test_snapshot_training_is_stage_aware_targets_remaining_cost_and_keeps_projects_out_of_both_partitions(): void
    {
        $projectIds = [];
        for ($index = 1; $index <= 10; $index++) {
            $projectIds[] = $this->insertCompletedProject($index, withFinanceBaseline: false);
        }

        foreach ($projectIds as $index => $projectId) {
            $finalCost = 200000 + ($index * 10000);
            $finalizedAt = Carbon::create(2025, 1, 1)->addDays($index);
            foreach ([10 => 20000, 50 => 90000, 90 => 170000] as $completion => $spent) {
                DB::table('ml_project_cost_snapshots')->insert([
                    'project_id' => $projectId,
                    'captured_at' => $finalizedAt->copy()->subDays(100 - $completion),
                    'capture_reason' => 'progress_recorded',
                    'planned_budget' => $finalCost * 0.95,
                    'planned_duration_months' => 8,
                    'worker_count' => 10,
                    'completion_percentage' => $completion,
                    'phase' => null,
                    'elapsed_duration_months' => $completion / 10,
                    'finance_as_of_date' => $finalizedAt->copy()->subDays(100 - $completion)->toDateString(),
                    'cumulative_total_expense' => $spent,
                    'cumulative_material_expense' => $spent * 0.6,
                    'cumulative_labor_expense' => $spent * 0.3,
                    'cumulative_equipment_expense' => $spent * 0.1,
                    'cumulative_other_expense' => 0,
                    'final_actual_cost' => $finalCost,
                    'finalized_at' => $finalizedAt,
                    'data_source' => 'operational',
                ]);
            }
        }

        $service = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $cohort = $this->invokeProtected($service, 'selectTrainingCohort');

        $this->assertSame('progress_snapshot_model', $cohort['strategy']);
        $this->assertTrue($cohort['snapshot_readiness']['eligible']);
        $this->assertSame(['early' => 10, 'middle' => 10, 'late' => 10], $cohort['snapshot_readiness']['projects_by_stage']);
        $this->assertSame(30, $cohort['records']->count());
        $earlyFirstProject = $cohort['records']->first(fn ($row) => (int) $row->project_id === $projectIds[0] && (float) $row->completion_percentage === 10.0);
        $lateFirstProject = $cohort['records']->first(fn ($row) => (int) $row->project_id === $projectIds[0] && (float) $row->completion_percentage === 90.0);
        $this->assertSame(180000.0, (float) $earlyFirstProject->actual_cost);
        $this->assertSame(30000.0, (float) $lateFirstProject->actual_cost);

        $split = $this->invokeProtected($service, 'selectChronologicalSplit', $cohort['records'], $cohort['feature_names']);
        $trainingProjectIds = $split['training_data']->pluck('project_id')->map(fn ($id) => (int) $id)->unique()->values();
        $testProjectIds = $split['test_data']->pluck('project_id')->map(fn ($id) => (int) $id)->unique()->values();
        $chronologicalProjectIds = DB::table('project_tbl')->whereIn('project_id', $projectIds)
            ->orderBy('actual_end_date')->orderBy('project_id')->pluck('project_id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([], $trainingProjectIds->intersect($testProjectIds)->all());
        $this->assertSame(array_slice($chronologicalProjectIds, 0, 8), $trainingProjectIds->all());
        $this->assertSame(array_slice($chronologicalProjectIds, 8, 2), $testProjectIds->all());
        $this->assertLessThanOrEqual(
            $split['test_data']->min('completed_at'),
            $split['training_data']->max('completed_at'),
            'Every held-out project must be chronologically newer than every training project.'
        );

        $trained = new MLService($this->modelPath);
        $metadata = $trained->getModelMetadata();
        $this->assertSame('progress_snapshot_model', $metadata['prediction_strategy']);
        $this->assertSame('remaining_cost_then_add_recorded_spend', $metadata['prediction_target']);
        $prediction = $trained->predictProjectCost(190000, 8, 10, 10, 12000, 6000, 20000, 12000, 6000, 2000, 0);
        $this->assertGreaterThanOrEqual(20000, $prediction);
        $this->assertSame('real_trained_model', $trained->getLastPredictionSource());
        $this->assertArrayHasKey('prediction_usable', $trained->getLastPredictionSupport());
        $this->assertSame([], glob($this->modelPath.'.candidate.*') ?: []);
        $this->assertSame([], glob($this->modelPath.'.backup.*') ?: []);
    }

    public function test_company_inspired_sample_projects_train_with_explicit_non_company_provenance(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            $projectId = $this->insertCompletedProject($index);
            DB::table('project_tbl')->where('project_id', $projectId)->update([
                'data_source' => 'company_inspired_sample',
            ]);
        }

        $service = new MLService($this->modelPath);
        $metrics = $service->getModelMetrics();

        $this->assertSame('sample_trained_model', $metrics['model_source']);
        $this->assertTrue($metrics['uses_synthetic_data']);
        $this->assertSame(0, $metrics['real_samples_available']);
        $this->assertSame(12, $metrics['sample_samples_available']);
        $this->assertSame(12, $metrics['samples_trained']);
        $this->assertIsNumeric($metrics['accuracy']);
        $this->assertStringContainsString('sample evaluation', $metrics['metric_scope']);
        $this->assertStringContainsString('not company-validated accuracy', $metrics['interpretation']);
        $this->assertNotEmpty($metrics['warnings']);

        $service->predictProjectCost(100000, 6, 10, 100, 60000, 30000);
        $this->assertStringContainsString(
            'company-inspired sample dataset',
            implode(' ', $service->getLastPredictionWarnings())
        );
    }

    public function test_retrain_console_command_and_schedule_are_registered(): void
    {
        $this->assertArrayHasKey('ml:retrain', Artisan::all());

        $this->artisan('ml:retrain --scheduled')
            ->expectsOutputToContain('Model source:')
            ->expectsOutputToContain('Samples trained:')
            ->assertExitCode(0);

        $this->artisan('schedule:list')
            ->expectsOutputToContain('ml:retrain --scheduled')
            ->assertExitCode(0);
    }

    public function test_finance_features_exclude_expenses_recorded_on_or_after_the_outcome_date(): void
    {
        for ($index = 1; $index <= 10; $index++) {
            $this->insertCompletedProject($index, withFinanceBaseline: $index !== 1);
        }
        $project = DB::table('project_tbl')->where('project_id', 1)->first();
        DB::table('fin_expense_tbl')->insert([
            [
                'project_id' => 1,
                'fin_category_id' => 1,
                'amount' => 12345,
                'project_cost_component' => 'material',
                'expense_date' => Carbon::parse($project->actual_end_date)->subDay()->toDateString(),
            ],
            [
                'project_id' => 1,
                'fin_category_id' => 1,
                'amount' => 999999,
                'project_cost_component' => 'material',
                'expense_date' => $project->actual_end_date,
            ],
        ]);

        $service = new MLService($this->modelPath);
        $records = $this->trainingData($service);
        $record = $records->firstWhere('project_id', 1);

        $this->assertSame(12345.0, (float) $record->fin_total_expense);
        $this->assertSame(12345.0, (float) $record->fin_material_expense);
    }

    public function test_training_data_uses_finance_categories_and_ignores_outdated_expense_rows(): void
    {
        $projectId = $this->insertProject([
            'project_name' => 'Finance Category Source',
            'start_date' => '2024-01-01',
            'estimated_end_date' => '2024-06-01',
            'actual_end_date' => '2024-06-01',
            'worker_count' => 12,
            'completion_percentage' => 100,
            'status' => 'Completed',
        ], 100000, 999999);

        DB::table('fin_expense_tbl')->insert([
            [
                'project_id' => $projectId,
                'fin_category_id' => 1,
                'amount' => 111111,
                'project_cost_component' => 'labor',
                'expense_date' => '2024-05-01',
            ],
            [
                'project_id' => $projectId,
                'fin_category_id' => 2,
                'amount' => 222222,
                'project_cost_component' => 'material',
                'expense_date' => '2024-05-02',
            ],
            [
                'project_id' => $projectId,
                'fin_category_id' => 3,
                'amount' => 333333,
                'project_cost_component' => 'other',
                'expense_date' => '2024-05-03',
            ],
        ]);

        Schema::create('expense_tbl', function (Blueprint $table) {
            $table->increments('expense_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->decimal('material_amount', 12, 2)->nullable();
            $table->decimal('labor_amount', 12, 2)->nullable();
            $table->decimal('equipment_amount', 12, 2)->nullable();
            $table->decimal('other_amount', 12, 2)->nullable();
        });
        DB::table('expense_tbl')->insert([
            'project_id' => $projectId,
            'material_amount' => 900000,
            'labor_amount' => 800000,
            'equipment_amount' => 700000,
            'other_amount' => 600000,
        ]);

        $service = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $records = $this->trainingData($service);
        $record = $records->firstWhere('project_id', $projectId);

        $this->assertSame(111111.0, (float) $record->material_cost);
        $this->assertSame(222222.0, (float) $record->labor_cost);
        $this->assertSame(333333.0, (float) $record->fin_equipment_expense);
        $this->assertSame(666666.0, (float) $record->fin_total_expense);
        $this->assertSame(666666.0, (float) $record->actual_cost);
    }

    public function test_training_data_caps_the_newest_500_records_then_restores_chronological_order(): void
    {
        $firstCompletion = Carbon::create(2020, 1, 1);
        for ($index = 1; $index <= 501; $index++) {
            $completedAt = $firstCompletion->copy()->addDays($index);
            $this->insertProject([
                'project_name' => "Cohort {$index} - Site {$index}",
                'start_date' => $completedAt->copy()->subMonth()->toDateString(),
                'estimated_end_date' => $completedAt->toDateString(),
                'actual_end_date' => $completedAt->toDateString(),
                'worker_count' => 10,
                'completion_percentage' => 100,
                'status' => 'Completed',
            ], 100000 + $index, 110000 + $index);
        }

        $service = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $records = $this->trainingData($service);

        $this->assertCount(500, $records);
        $this->assertFalse($records->contains(fn ($record) => (int) $record->project_id === 1));
        $this->assertSame(2, (int) $records->first()->project_id);
        $this->assertSame(501, (int) $records->last()->project_id);
    }

    public function test_training_uses_allocated_material_cost_without_leaking_post_completion_withdrawals(): void
    {
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('project_id');
            $table->integer('out_transaction_id');
            $table->string('valuation_status');
            $table->decimal('allocated_amount', 14, 2)->nullable();
        });
        $projectId = $this->insertProject([
            'project_name' => 'Material Allocation Site',
            'start_date' => '2025-01-01', 'estimated_end_date' => '2025-04-01',
            'actual_end_date' => '2025-04-01', 'worker_count' => 8,
            'completion_percentage' => 100, 'status' => 'Completed',
        ], 10000, 1200);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $projectId, 'fin_category_id' => 2,
            'amount' => 200, 'expense_date' => '2025-03-01',
        ]);
        foreach ([['2025-03-15', 300], ['2025-04-02', 700]] as [$date, $amount]) {
            $withdrawalId = DB::table('inventory_transaction_tbl')->insertGetId([
                'project_id' => $projectId, 'transaction_type' => 'OUT',
                'quantity' => 1, 'transaction_date' => $date,
            ]);
            DB::table('inventory_cost_allocation_tbl')->insert([
                'project_id' => $projectId, 'out_transaction_id' => $withdrawalId,
                'valuation_status' => 'valued', 'allocated_amount' => $amount,
            ]);
        }

        $service = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $record = $this->trainingData($service)->firstWhere('project_id', $projectId);
        $this->assertNotNull($record);
        $this->assertSame(1200.0, (float) $record->actual_cost);
        $this->assertSame(300.0, (float) $record->material_cost);
        $this->assertSame(500.0, (float) $record->fin_total_expense);
    }

    public function test_project_type_monitoring_uses_normalized_project_names_when_no_explicit_type_exists(): void
    {
        foreach (['Market Upgrade - Site 1', 'Market Upgrade - Site 2', 'Plaza Upgrade - Site 1'] as $index => $projectName) {
            $completedAt = Carbon::create(2024, 1, 1)->addDays($index + 1);
            $this->insertProject([
                'project_name' => $projectName,
                'start_date' => $completedAt->copy()->subMonth()->toDateString(),
                'estimated_end_date' => $completedAt->toDateString(),
                'actual_end_date' => $completedAt->toDateString(),
                'worker_count' => 10,
                'completion_percentage' => 100,
                'status' => 'Completed',
            ], 100000 + $index, 110000 + $index);
        }

        $service = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $records = $this->trainingData($service);

        $this->assertSame(['Market Upgrade', 'Market Upgrade', 'Plaza Upgrade'], $records->pluck('project_type')->all());
        $this->assertSame(['normalized_project_name'], $records->pluck('project_type_source')->unique()->values()->all());
        $this->assertSame(['Market Upgrade', 'Plaza Upgrade'], $records->pluck('project_type')->unique()->values()->all());
    }

    public function test_synthetic_fallback_does_not_publish_fake_evaluation_metrics(): void
    {
        $this->insertCompletedProject(1);
        $service = new MLService($this->modelPath);
        $metrics = $service->getModelMetrics();

        $this->assertSame('synthetic_fallback_model', $metrics['model_source']);
        $this->assertTrue($metrics['uses_synthetic_data']);
        $this->assertSame(1, $metrics['real_samples_available']);
        $this->assertNull($metrics['accuracy']);
        $this->assertNull($metrics['mean_absolute_error']);
        $this->assertNull($metrics['precision']);
        $this->assertNull($metrics['recall']);
        $this->assertNull($metrics['f1_score']);
        $this->assertSame('Unavailable', $metrics['mae_formatted']);
    }

    public function test_material_projection_uses_dated_daily_usage_for_a_30_day_horizon(): void
    {
        Carbon::setTestNow('2026-08-25 10:00:00');
        $itemId = DB::table('inventory_item_tbl')->insertGetId([
            'item_name' => 'Cement',
            'current_stock' => 100,
            'reorder_level' => 10,
        ]);
        DB::table('inventory_transaction_tbl')->insert([
            [
                'item_id' => $itemId,
                'project_id' => 1,
                'transaction_type' => 'OUT',
                'quantity' => 30,
                'transaction_date' => '2026-07-27',
            ],
            [
                'item_id' => $itemId,
                'project_id' => 2,
                'transaction_type' => 'OUT',
                'quantity' => 30,
                'transaction_date' => '2026-08-25',
            ],
        ]);

        $projection = (new MLService($this->modelPath))->predictMaterialDemand()->get((string) $itemId);

        $this->assertSame(2.0, $projection['average_daily_usage']);
        $this->assertSame(60.0, $projection['projected_demand']);
        $this->assertSame(30, $projection['forecast_horizon_days']);
        $this->assertSame(30, $projection['usage_window_days']);
        $this->assertSame('time_based_90_day_usage', $projection['calculation_method']);
        $this->assertSame(2, $projection['project_count']);
    }

    protected function insertCompletedProject(int $index, bool $withFinanceBaseline = true): int
    {
        $start = Carbon::create(2022, 1, 1)->addMonths($index);
        $duration = 3 + ($index % 7);
        $budget = 100000 + ($index * 27000) + (($index % 3) * 4100);
        $actual = $budget * (0.91 + (($index % 5) * 0.045)) + ($index * 137);
        $projectId = $this->insertProject([
            'project_name' => "Completed {$index}",
            'start_date' => $start->toDateString(),
            'estimated_end_date' => $start->copy()->addMonths($duration)->toDateString(),
            'actual_end_date' => $start->copy()->addMonths($duration)->addDays($index)->toDateString(),
            'worker_count' => 4 + ($index * 2) + ($index % 4),
            'completion_percentage' => 100,
            'status' => 'Completed',
        ], $budget, $actual);

        if ($withFinanceBaseline) {
            $this->insertFinanceBaseline($projectId, $index, $budget);
        }

        return $projectId;
    }

    protected function insertFinanceBaseline(int $projectId, int $index, float $budget): void
    {
        $project = DB::table('project_tbl')->where('project_id', $projectId)->first();
        $asOfDate = Carbon::parse($project->actual_end_date)->subDay()->toDateString();

        DB::table('fin_expense_tbl')->insert([
            [
                'project_id' => $projectId,
                'fin_category_id' => 1,
                'amount' => round($budget * (0.18 + (($index % 4) * 0.025)), 2),
                'expense_date' => $asOfDate,
            ],
            [
                'project_id' => $projectId,
                'fin_category_id' => 2,
                'amount' => round($budget * (0.11 + (($index % 3) * 0.02)), 2),
                'expense_date' => $asOfDate,
            ],
            [
                'project_id' => $projectId,
                'fin_category_id' => 1,
                'amount' => round($budget * (0.13 + (($index % 2) * 0.015)), 2),
                'expense_date' => $asOfDate,
            ],
            [
                'project_id' => $projectId,
                'fin_category_id' => 2,
                'amount' => round($budget * (0.08 + (($index % 5) * 0.01)), 2),
                'expense_date' => $asOfDate,
            ],
        ]);
    }

    protected function insertFinanceSignals(int $projectId): void
    {
        $budget = DB::table('budgets_tbl')->where('project_id', $projectId)->latest('budget_id')->first();
        $actual = (float) $budget->actual_amount;

        $project = DB::table('project_tbl')->where('project_id', $projectId)->first();
        $asOfDate = Carbon::parse($project->actual_end_date)->subDay()->toDateString();

        DB::table('fin_expense_tbl')->insert([
            [
                'project_id' => $projectId,
                'fin_category_id' => 1,
                'amount' => round($actual * 0.72, 2),
                'project_cost_component' => 'material',
                'expense_date' => $asOfDate,
            ],
            [
                'project_id' => $projectId,
                'fin_category_id' => 2,
                'amount' => round($actual * 0.08, 2),
                'project_cost_component' => 'labor',
                'expense_date' => $asOfDate,
            ],
        ]);

    }

    /** @return Collection<int, object> */
    protected function trainingData(MLService $service): Collection
    {
        /** @var Collection<int, object> $records */
        $records = $this->invokeProtected($service, 'getTrainingData');

        return $records;
    }

    protected function invokeProtected(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }

    protected function insertProject(array $project, float $budget, float $actual): int
    {
        $projectId = DB::table('project_tbl')->insertGetId($project);
        DB::table('budgets_tbl')->insert([
            'project_id' => $projectId,
            'budget_amount' => $budget,
            'actual_amount' => $actual,
        ]);

        return $projectId;
    }

    protected function user(string $role): User
    {
        return User::query()->create([
            'name' => ucfirst($role).' User',
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    public function test_cost_quality_blocks_partial_allocations_and_invalidates_final_labels_without_changing_costs(): void
    {
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('project_id');
            $table->integer('in_transaction_id');
            $table->integer('out_transaction_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('allocated_amount', 14, 2);
            $table->string('valuation_status');
        });
        $id = $this->insertProject([
            'project_name' => 'Cost quality site', 'status' => 'Completed', 'completion_percentage' => 100,
            'start_date' => '2024-01-01', 'estimated_end_date' => '2024-05-01',
            'actual_end_date' => '2024-05-01', 'worker_count' => 8,
        ], 500, 600);
        DB::table('fin_expense_tbl')->insert(['project_id' => $id, 'fin_category_id' => 2, 'amount' => 100, 'expense_date' => '2024-04-01']);
        $receipt = DB::table('inventory_transaction_tbl')->insertGetId([
            'item_id' => 1, 'transaction_type' => 'IN', 'quantity' => 10, 'transaction_date' => '2024-02-01',
        ]);
        $out = DB::table('inventory_transaction_tbl')->insertGetId([
            'item_id' => 1, 'project_id' => $id, 'transaction_type' => 'OUT', 'quantity' => 10, 'transaction_date' => '2024-03-01',
        ]);
        $allocation = DB::table('inventory_cost_allocation_tbl')->insertGetId([
            'project_id' => $id, 'in_transaction_id' => $receipt, 'out_transaction_id' => $out,
            'quantity' => 10, 'unit_cost' => 50, 'allocated_amount' => 500, 'valuation_status' => 'valued',
        ]);
        $quality = app(ProjectCostDataQualityService::class);
        $this->assertTrue($quality->inspect($id)['eligible']);
        $this->assertTrue($quality->inspect($id)['overrun_outcomes']['current_budget']['material_overrun']);
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->integer('inventory_transaction_id')->nullable();
            $table->string('entry_kind')->nullable();
        });
        // Shared stock may have been purchased for a different project.
        DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $receipt)->update(['project_id' => 77]);
        $purchase = DB::table('fin_expense_tbl')->insertGetId([
            'project_id' => 77, 'fin_category_id' => 1, 'amount' => 500, 'expense_date' => '2024-02-01',
            'inventory_transaction_id' => $receipt, 'entry_kind' => 'inventory_purchase',
        ]);
        $this->assertTrue($quality->inspect($id)['eligible']);
        DB::table('fin_expense_tbl')->where('fin_expense_id', $purchase)->update(['amount' => 400]);
        $this->assertContains('allocation_price_does_not_match_purchase', $quality->inspect($id)['errors']);
        DB::table('fin_expense_tbl')->where('fin_expense_id', $purchase)->update(['amount' => 500]);
        $ml = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $this->assertCount(1, $this->trainingData($ml));
        $snapshots = app(ProjectCostSnapshotService::class);
        $this->assertTrue($snapshots->capture($id));
        $this->assertSame(600.0, (float) DB::table('ml_project_cost_snapshots')->value('final_actual_cost'));

        // The old sum-only reconciliation would accept this incomplete allocation.
        DB::table('inventory_cost_allocation_tbl')->where('allocation_id', $allocation)->update(['quantity' => 5, 'unit_cost' => 100]);
        $beforeBudget = DB::table('budgets_tbl')->first();
        $report = $quality->report();
        $this->assertTrue($report['read_only']);
        $this->assertContains('inventory_quantity_not_fully_allocated', $report['projects'][0]['errors']);
        $this->assertEquals($beforeBudget, DB::table('budgets_tbl')->first());
        $this->assertCount(0, $this->trainingData($ml));
        $snapshots->capture($id);
        $this->assertSame(0, DB::table('ml_project_cost_snapshots')->whereNotNull('final_actual_cost')->count());

        DB::table('inventory_cost_allocation_tbl')->where('allocation_id', $allocation)->update(['quantity' => 10, 'unit_cost' => 50]);
        DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $receipt)->update(['transaction_date' => '2024-04-01']);
        $this->assertContains('inventory_used_before_receipt_or_missing_date', $quality->inspect($id)['errors']);
        DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $receipt)->update(['transaction_date' => '2024-02-01', 'quantity' => 5]);
        $this->assertContains('receipt_quantity_overallocated', $quality->inspect($id)['errors']);
        DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $receipt)->update(['quantity' => 10]);
        DB::table('inventory_cost_allocation_tbl')->where('allocation_id', $allocation)->update(['allocated_amount' => 400]);
        $errors = $quality->inspect($id)['errors'];
        $this->assertContains('invalid_inventory_allocation_value', $errors);
        $this->assertContains('budget_actual_does_not_match_ledger', $errors);
    }

    public function test_cost_quality_reports_future_completion_duplicate_budgets_and_late_invoice_warnings(): void
    {
        Carbon::setTestNow('2025-06-01');
        $id = $this->insertProject([
            'project_name' => 'Closeout site', 'status' => 'Completed', 'completion_percentage' => 100,
            'start_date' => '2025-01-01', 'estimated_end_date' => '2025-04-01',
            'actual_end_date' => '2025-04-01', 'worker_count' => 4,
        ], 1000, 1050);
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->string('remarks')->nullable();
            $table->integer('inventory_transaction_id')->nullable();
        });
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $id, 'fin_category_id' => 1, 'amount' => 1050, 'expense_date' => '2025-04-15',
            'remarks' => 'Historical item-price estimate: existing estimate.',
        ]);
        $quality = app(ProjectCostDataQualityService::class);
        $result = $quality->inspect($id);
        $this->assertTrue($result['eligible']);
        $this->assertContains('estimated_historical_prices', $result['warnings']);
        $this->assertContains('costs_recorded_after_completion', $result['warnings']);
        $this->assertFalse($result['overrun_outcomes']['current_budget']['material_overrun']);
        $this->assertTrue($result['overrun_outcomes']['current_budget']['any_overrun']);
        DB::table('project_tbl')->where('project_id', $id)->update(['actual_end_date' => '2025-07-01']);
        DB::table('budgets_tbl')->insert(['project_id' => $id, 'budget_amount' => 1000, 'actual_amount' => 1050]);
        $errors = $quality->inspect($id)['errors'];
        $this->assertContains('future_completion', $errors);
        $this->assertContains('duplicate_active_budgets', $errors);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => $id, 'fin_category_id' => 1, 'amount' => 10, 'expense_date' => '2025-01-01', 'inventory_transaction_id' => 999],
            ['project_id' => $id, 'fin_category_id' => 1, 'amount' => 10, 'expense_date' => '2025-01-01', 'inventory_transaction_id' => 999],
        ]);
        $errors = $quality->inspect($id)['errors'];
        $this->assertContains('multiple_purchase_expenses_per_receipt', $errors);
        $this->assertContains('invalid_project_purchase_link', $errors);
        $this->artisan('ml:audit-data')->assertExitCode(0);
    }

    public function test_snapshot_timing_keeps_observed_schedule_and_coalesces_duplicate_event_hooks(): void
    {
        (require database_path('migrations/2026_10_02_000002_add_capture_quality_to_project_cost_snapshots.php'))->up();
        Carbon::setTestNow('2025-06-01 09:00:00');
        $id = $this->insertProject([
            'project_name' => 'Observed progress', 'status' => 'Ongoing', 'completion_percentage' => 25,
            'start_date' => '2025-05-01', 'estimated_end_date' => '2025-05-31', 'worker_count' => 4,
        ], 1000, 200);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $id, 'fin_category_id' => 2, 'amount' => 200, 'expense_date' => '2025-05-15',
        ]);
        $snapshots = app(ProjectCostSnapshotService::class);
        $this->assertTrue($snapshots->capture($id, 'stock_out_created'));
        $this->assertFalse($snapshots->capture($id, 'data_change'));
        $first = DB::table('ml_project_cost_snapshots')->first();
        $this->assertSame(2, (int) $first->capture_schema_version);
        $this->assertSame('2025-05-31', $first->planned_end_date);
        $this->assertSame(30, (int) $first->planned_duration_days);
        $this->assertSame(31, (int) $first->elapsed_days);
        $this->assertSame(0, (int) $first->remaining_planned_days);
        $this->assertSame(1, (int) $first->days_past_planned_end);
        $this->assertTrue((bool) $first->cost_coverage_complete);
        $this->assertNull($first->final_actual_cost);
        DB::table('project_tbl')->where('project_id', $id)->update(['estimated_end_date' => '2025-06-15', 'completion_percentage' => 40]);
        $this->assertTrue($snapshots->capture($id, 'data_change'));
        $second = DB::table('ml_project_cost_snapshots')->orderByDesc('snapshot_id')->first();
        $this->assertSame(14, (int) $second->remaining_planned_days);
        $this->assertSame(0, (int) $second->days_past_planned_end);
        $this->assertSame('2025-05-31', DB::table('ml_project_cost_snapshots')->where('snapshot_id', $first->snapshot_id)->value('planned_end_date'));
        // A weekly observation is retained even without a financial change; duplicate jobs are blocked.
        $this->assertTrue($snapshots->capture($id, 'weekly_schedule'));
        $this->assertFalse($snapshots->capture($id, 'weekly_schedule'));
        Carbon::setTestNow('2025-06-02 09:00:00');
        $this->assertTrue($snapshots->capture($id, 'weekly_schedule'));
        DB::table('budgets_tbl')->where('project_id', $id)->update(['actual_amount' => 300]);
        $this->assertTrue($snapshots->capture($id, 'data_change'));
        $this->assertFalse((bool) DB::table('ml_project_cost_snapshots')->orderByDesc('snapshot_id')->value('cost_coverage_complete'));
        DB::table('project_tbl')->where('project_id', $id)->update(['start_date' => '2025-06-03']);
        $count = DB::table('ml_project_cost_snapshots')->count();
        $this->assertFalse($snapshots->capture($id, 'data_change'));
        $this->assertSame($count, DB::table('ml_project_cost_snapshots')->count());
        Carbon::setTestNow('2025-06-10 09:00:00');
        DB::table('budgets_tbl')->where('project_id', $id)->update(['actual_amount' => 200]);
        DB::table('project_tbl')->where('project_id', $id)->update([
            'status' => 'Completed', 'completion_percentage' => 100, 'actual_end_date' => '2025-06-10',
        ]);
        $snapshots->capture($id, 'project_completed');
        $ml = new MLService($this->modelPath, loadModel: false);
        $rows = $this->invokeProtected($ml, 'getSnapshotTrainingData');
        $this->assertSame('2025-05-01', $rows->firstWhere('snapshot_id', $first->snapshot_id)->planned_start_date);
    }

    public function test_capture_quality_migration_does_not_reconstruct_history_and_readiness_counts_projects(): void
    {
        Carbon::setTestNow('2025-06-01');
        $id = $this->insertProject([
            'project_name' => 'Historical observation', 'status' => 'Ongoing', 'completion_percentage' => 20,
            'start_date' => '2025-01-01', 'estimated_end_date' => '2025-12-31', 'worker_count' => 4,
        ], 1000, 0);
        $snapshots = app(ProjectCostSnapshotService::class);
        $snapshots->capture($id);
        $before = DB::table('ml_project_cost_snapshots')->first();
        $migration = require database_path('migrations/2026_10_02_000002_add_capture_quality_to_project_cost_snapshots.php');
        $migration->up();
        $historical = DB::table('ml_project_cost_snapshots')->first();
        $this->assertNull($historical->capture_schema_version);
        $this->assertNull($historical->planned_end_date);
        $this->assertNull($historical->cost_coverage_complete);
        foreach ((array) $before as $column => $value) {
            $this->assertSame($value, $historical->{$column});
        }
        $snapshots->capture($id, 'weekly_schedule');
        $ml = new MLService($this->modelPath, loadModel: false);
        $readiness = $ml->getSnapshotReadiness();
        $this->assertFalse(File::exists($this->modelPath));
        $this->assertFalse(File::exists($this->modelPath.'.meta.json'));
        $this->artisan('ml:audit-snapshots')->assertExitCode(0);
        $this->assertSame(2, $readiness['collected_snapshot_rows']);
        $this->assertSame(0, $readiness['finalized_projects']);
        $this->assertSame(['early' => 10, 'middle' => 10, 'late' => 10], $readiness['projects_still_needed_by_stage']);
        $this->assertFalse($readiness['eligible']);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('ml_project_cost_snapshots', 'elapsed_days'));
        $this->assertEquals($before, DB::table('ml_project_cost_snapshots')->where('snapshot_id', $before->snapshot_id)->first());
        $this->assertDatabaseCount('ml_project_cost_snapshots', 2);
    }

    public function test_snapshot_activity_excludes_rows_posted_after_the_observation_despite_backdated_business_dates(): void
    {
        (require database_path('migrations/2026_09_28_000001_add_activity_to_project_cost_snapshots.php'))->up();
        (require database_path('migrations/2026_10_02_000002_add_capture_quality_to_project_cost_snapshots.php'))->up();
        Schema::table('fin_expense_tbl', fn (Blueprint $table) => $table->dateTime('created_at')->nullable());
        Schema::table('inventory_transaction_tbl', fn (Blueprint $table) => $table->dateTime('recorded_at')->nullable());
        Carbon::setTestNow('2025-06-01 12:00:00');
        $id = $this->insertProject([
            'project_name' => 'Known at observation', 'status' => 'Ongoing', 'completion_percentage' => 50,
            'start_date' => '2025-05-01', 'estimated_end_date' => '2025-07-31', 'worker_count' => 4,
        ], 1000, 200);
        foreach ([['2025-05-25', '2025-05-25 09:00:00', 2], ['2025-05-26', '2025-06-02 09:00:00', 7]] as [$date, $recordedAt, $quantity]) {
            DB::table('fin_expense_tbl')->insert([
                'project_id' => $id, 'fin_category_id' => 2, 'amount' => 100, 'expense_date' => $date, 'created_at' => $recordedAt,
            ]);
            DB::table('inventory_transaction_tbl')->insert([
                'project_id' => $id, 'transaction_type' => 'OUT', 'quantity' => $quantity,
                'transaction_date' => $date, 'recorded_at' => $recordedAt,
            ]);
        }
        $snapshots = app(ProjectCostSnapshotService::class);
        $activity = $snapshots->activityForProject($id, now());
        $this->assertSame(1, $activity['direct_expense_count_30d']);
        $this->assertSame(100.0, $activity['direct_expense_amount_30d']);
        $this->assertSame(1, $activity['stock_out_count_30d']);
        $this->assertSame(2.0, $activity['stock_out_quantity_30d']);
        $snapshots->capture($id);
        $observed = DB::table('ml_project_cost_snapshots')->first();
        $this->assertSame(100.0, (float) $observed->cumulative_total_expense);
        $this->assertFalse((bool) $observed->cost_coverage_complete);
        Carbon::setTestNow('2025-06-03 12:00:00');
        $this->assertSame(2, $snapshots->activityForProject($id, now())['direct_expense_count_30d']);
        // Even an apparently finalized record must be excluded if its observed costs were incomplete.
        DB::table('project_tbl')->where('project_id', $id)->update([
            'status' => 'Completed', 'completion_percentage' => 100, 'actual_end_date' => '2025-06-03',
        ]);
        DB::table('ml_project_cost_snapshots')->update(['final_actual_cost' => 200, 'finalized_at' => now()]);
        $ml = (new \ReflectionClass(MLService::class))->newInstanceWithoutConstructor();
        $this->assertCount(0, $this->invokeProtected($ml, 'getSnapshotTrainingData'));
        $snapshots->capture($id);
        $this->assertSame(0, DB::table('ml_project_cost_snapshots')->whereNotNull('final_actual_cost')->count());
    }

    public function test_engineered_features_match_between_observed_training_and_live_prediction_with_inventory_cost_burn(): void
    {
        (require database_path('migrations/2026_09_28_000001_add_activity_to_project_cost_snapshots.php'))->up();
        (require database_path('migrations/2026_10_02_000002_add_capture_quality_to_project_cost_snapshots.php'))->up();
        $recentMigration = require database_path('migrations/2026_10_02_000003_add_recent_costs_to_project_cost_snapshots.php');
        $recentMigration->up();
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('project_id');
            $table->integer('in_transaction_id');
            $table->integer('out_transaction_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('allocated_amount', 14, 2);
            $table->string('valuation_status');
            $table->dateTime('allocated_at');
        });
        Carbon::setTestNow('2025-06-05 12:00:00');
        $id = $this->insertProject([
            'project_name' => 'Shared feature calculation', 'status' => 'Ongoing', 'completion_percentage' => 50,
            'start_date' => '2025-05-01', 'estimated_end_date' => '2025-06-15', 'worker_count' => 4,
        ], 500, 400);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => $id, 'fin_category_id' => 2, 'amount' => 100, 'expense_date' => '2025-06-04',
        ]);
        $receipt = DB::table('inventory_transaction_tbl')->insertGetId([
            'item_id' => 1, 'transaction_type' => 'IN', 'quantity' => 10, 'transaction_date' => '2025-05-31',
        ]);
        $out = DB::table('inventory_transaction_tbl')->insertGetId([
            'item_id' => 1, 'project_id' => $id, 'transaction_type' => 'OUT', 'quantity' => 10, 'transaction_date' => '2025-06-03',
        ]);
        DB::table('inventory_cost_allocation_tbl')->insert([
            'project_id' => $id, 'in_transaction_id' => $receipt, 'out_transaction_id' => $out,
            'quantity' => 10, 'unit_cost' => 30, 'allocated_amount' => 300, 'valuation_status' => 'valued', 'allocated_at' => now(),
        ]);
        $ml = new MLService($this->modelPath, loadModel: false);
        $live = $ml->getPredictionProjects()->firstWhere('project_id', $id);
        $this->assertSame(400.0, $live['fin_total_expense']);
        $this->assertSame(800.0, $live['feature_indicators']['progress_based_final_cost']);
        $this->assertSame(300.0, $live['forecast_feature_context']['valued_stock_out_cost_30d']);
        $admin = $this->user('admin');
        $this->actingAs($admin)->postJson('/api/ml/predict/cost', [
            'project_id' => $id, 'feature_indicators' => ['progress_based_final_cost' => 1],
            'forecast_feature_context' => ['cost_coverage_complete' => false],
        ])->assertOk()->assertJsonPath('feature_indicators.progress_based_final_cost', 800)
            ->assertJsonPath('feature_indicators.cost_coverage_complete', true);
        $snapshots = app(ProjectCostSnapshotService::class);
        $snapshots->capture($id);
        $snapshot = DB::table('ml_project_cost_snapshots')->first();
        $this->assertSame(100.0, (float) $snapshot->direct_expense_amount_7d);
        $this->assertSame(300.0, (float) $snapshot->valued_stock_out_cost_7d);
        $this->assertSame(300.0, (float) $snapshot->valued_stock_out_cost_30d);
        $expected = app(ProjectCostFeatureBuilder::class)->build($live['forecast_feature_context'] + [
            'budget' => 500, 'completion_percentage' => 50,
        ])['values'];
        Carbon::setTestNow('2025-06-10 12:00:00');
        DB::table('project_tbl')->where('project_id', $id)->update([
            'status' => 'Completed', 'completion_percentage' => 100, 'actual_end_date' => '2025-06-10',
        ]);
        $snapshots->capture($id, 'project_completed');
        $training = $this->invokeProtected($ml, 'getSnapshotTrainingData')->firstWhere('snapshot_id', $snapshot->snapshot_id);
        $this->assertNotNull($training);
        $trainVector = $this->invokeProtected($ml, 'rowToFeatures', $training, ProjectCostFeatureBuilder::FEATURE_NAMES);
        $predictVector = $this->invokeProtected($ml, 'predictionFeatureVector', array_fill(0, 15, 0),
            ProjectCostFeatureBuilder::FEATURE_NAMES, $expected);
        $this->assertEquals($trainVector, $predictVector);
        $this->assertContains(1.6, $trainVector); // Progress estimate is above budget, without using the final label.
        $recentMigration->down();
        $this->assertFalse(Schema::hasColumn('ml_project_cost_snapshots', 'valued_stock_out_cost_30d'));
        $this->assertSame(2, DB::table('ml_project_cost_snapshots')->count());
    }

    public function test_recent_burn_excludes_pre_start_costs_while_preserving_them_in_total_spending(): void
    {
        Carbon::setTestNow('2025-06-03');
        $id = $this->insertProject([
            'project_name' => 'Startup cost windows', 'status' => 'Ongoing', 'completion_percentage' => 30,
            'start_date' => '2025-06-01', 'estimated_end_date' => '2025-06-30', 'worker_count' => 4,
        ], 1000, 300);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => $id, 'fin_category_id' => 2, 'amount' => 100, 'expense_date' => '2025-05-31'],
            ['project_id' => $id, 'fin_category_id' => 2, 'amount' => 200, 'expense_date' => '2025-06-02'],
        ]);
        $input = app(ProjectCostSnapshotService::class)->forecastInputs($id, Carbon::parse('2025-06-01'), Carbon::parse('2025-06-30'));
        $this->assertSame(300.0, $input['fin_total_expense']);
        $this->assertSame(200.0, $input['direct_expense_amount_7d']);
        $this->assertSame(200.0, $input['direct_expense_amount_30d']);
        $this->assertSame(1, $input['direct_expense_count_30d']);
        $values = app(ProjectCostFeatureBuilder::class)->build($input + ['budget' => 1000, 'completion_percentage' => 30])['values'];
        $this->assertEqualsWithDelta(200 / 3, $values['cost_burn_rate_30d'], 0.000001);
        $this->assertSame(0, $values['time_forecast_available']);
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->string('status')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('project_tbl', function (Blueprint $table) {
            $table->increments('project_id');
            $table->string('project_name')->nullable();
            $table->date('start_date')->nullable();
            $table->date('estimated_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->integer('worker_count')->nullable();
            $table->decimal('completion_percentage', 5, 2)->nullable();
            $table->string('phase')->nullable();
            $table->string('status')->nullable();
            $table->string('data_source', 64)->default('operational');
        });
        Schema::create('budgets_tbl', function (Blueprint $table) {
            $table->increments('budget_id');
            $table->unsignedInteger('project_id');
            $table->decimal('budget_amount', 12, 2);
            $table->decimal('actual_amount', 12, 2)->nullable();
        });
        Schema::create('fin_expense_category_tbl', function (Blueprint $table) {
            $table->increments('fin_category_id');
            $table->string('category_code');
            $table->string('category_name');
            $table->string('classification');
            $table->boolean('is_active')->default(true);
        });
        DB::table('fin_expense_category_tbl')->insert([
            ['category_code' => 'CONST_SUPPLY', 'category_name' => 'Construction Supply', 'classification' => 'direct'],
            ['category_code' => 'SALARIES_WAGES', 'category_name' => 'Salaries and Wages', 'classification' => 'direct'],
            ['category_code' => 'EQUIPMENT_RENTAL', 'category_name' => 'Equipment Rental', 'classification' => 'direct'],
            ['category_code' => 'OFFICE_ADMIN', 'category_name' => 'Admin Cost', 'classification' => 'admin'],
        ]);
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('fin_category_id');
            $table->decimal('amount', 12, 2);
            $table->string('project_cost_component')->nullable();
            $table->date('expense_date');
        });
        Schema::create('ml_project_cost_snapshots', function (Blueprint $table) {
            $table->bigIncrements('snapshot_id');
            $table->unsignedInteger('project_id');
            $table->dateTime('captured_at', 6);
            $table->string('capture_reason', 32);
            $table->decimal('planned_budget', 14, 2);
            $table->unsignedInteger('planned_duration_months');
            $table->unsignedInteger('worker_count');
            $table->decimal('completion_percentage', 5, 2);
            $table->string('phase')->nullable();
            $table->decimal('elapsed_duration_months', 8, 2);
            $table->date('finance_as_of_date')->nullable();
            $table->decimal('cumulative_total_expense', 14, 2)->default(0);
            $table->decimal('cumulative_material_expense', 14, 2)->default(0);
            $table->decimal('cumulative_labor_expense', 14, 2)->default(0);
            $table->decimal('cumulative_equipment_expense', 14, 2)->default(0);
            $table->decimal('cumulative_other_expense', 14, 2)->default(0);
            $table->decimal('final_actual_cost', 14, 2)->nullable();
            $table->dateTime('finalized_at', 6)->nullable();
            $table->string('data_source', 64)->default('operational');
        });
        Schema::create('inventory_item_tbl', function (Blueprint $table) {
            $table->increments('item_id');
            $table->string('item_name')->nullable();
            $table->decimal('current_stock', 10, 2)->nullable();
            $table->decimal('reorder_level', 10, 2)->nullable();
        });
        Schema::create('inventory_transaction_tbl', function (Blueprint $table) {
            $table->increments('inventory_transaction_id');
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->date('transaction_date')->nullable();
        });
    }
}
