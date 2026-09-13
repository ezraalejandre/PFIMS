<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AutomaticModelRetraining;
use App\Services\MLService;
use App\Services\ProjectCostSnapshotService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class MLImprovementTest extends TestCase
{
    protected string $modelPath;

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
            ->assertSee('model-performance-card" hidden', false)
            ->assertSee('Management diagnostic', false)
            ->assertSee('Recommended action', false)
            ->assertSee('Prediction completed. The business diagnostic is ready.', false)
            ->assertDontSee('function refreshData()', false);

        $adminDashboard->assertSeeInOrder([
            'Project Cost Prediction',
            '30-Day Material Stock Projection',
            'Budget-Spending Comparison',
            'Model Performance',
        ]);

        $this->actingAs($this->user('accounting'))
            ->get('/ml-dashboard-test?embedded=1')
            ->assertOk()
            ->assertSee('Project Cost Prediction', false)
            ->assertDontSee('id="retrainConfirmModal"', false);
        $this->actingAs($this->user('accounting'))->get('/ml-dashboard-test?section=material-projection')->assertForbidden();

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
            ->assertJsonPath('input_features.fin_total_expense', 400000);
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

    public function test_automatic_retraining_hook_captures_each_affected_project_before_coalescing_retraining(): void
    {
        $projectId = $this->insertProject([
            'project_name' => 'Hooked Project',
            'start_date' => '2026-01-01',
            'estimated_end_date' => '2026-07-01',
            'worker_count' => 8,
            'completion_percentage' => 20,
            'status' => 'On Track',
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
            'status' => 'On Track',
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
        $this->assertCount(1, $comparison);
        $this->assertSame('Active project', $comparison->first()->project_name);

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

        $this->assertSame('5-fold_cross_validation', $metrics['cross_validation']['method']);
        $this->assertSame(5, $metrics['cross_validation']['folds_run']);
        $this->assertIsNumeric($metrics['cross_validation']['average_mean_absolute_error']);

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
