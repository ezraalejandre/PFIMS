<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Phpml\ModelManager;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\Regression;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use RuntimeException;
use Throwable;

class MLService
{
    private const MODEL_SCHEMA_VERSION = 9;

    private const MINIMUM_REAL_SAMPLES = 10;

    private const K_FOLD_COUNT = 5;

    private const SNAPSHOT_MINIMUM_PROJECTS_PER_STAGE = 10;

    private const FIN_FEATURE_IMPROVEMENT_THRESHOLD_PERCENT = 5.0;

    private const OVERRUN_RISK_TOLERANCE = 0.05;

    private const MINIMUM_BASELINE_MAPE_IMPROVEMENT_POINTS = 2.0;

    private const FORECAST_HORIZON_DAYS = 30;

    private const USAGE_LOOKBACK_DAYS = 90;

    private const BASE_FEATURE_NAMES = [
        'budget', 'duration_months', 'worker_count',
        'completion_percentage', 'material_cost', 'labor_cost',
    ];

    /** Inputs that are expected to exist before work starts. */
    private const PLANNING_FEATURE_NAMES = ['budget', 'duration_months'];

    /** Inputs captured at a real point in time while work is in progress. */
    private const SNAPSHOT_FEATURE_NAMES = [
        'budget', 'duration_months', 'worker_count', 'completion_percentage',
        'fin_total_expense', 'fin_material_expense', 'fin_labor_expense',
        'fin_equipment_expense', 'fin_other_expense',
    ];

    private const FIN_FEATURE_NAMES = [
        'fin_total_expense', 'fin_material_expense', 'fin_labor_expense',
        'fin_equipment_expense', 'fin_other_expense',
    ];

    private const FINANCE_ENRICHED_FEATURE_NAMES = [
        'budget', 'duration_months', 'worker_count',
        'completion_percentage', 'material_cost', 'labor_cost',
        'fin_total_expense', 'fin_material_expense', 'fin_labor_expense',
        'fin_equipment_expense', 'fin_other_expense',
    ];

    private const FEATURE_NAMES = self::FINANCE_ENRICHED_FEATURE_NAMES;

    protected ?LeastSquares $model = null;

    protected string $modelPath;

    protected string $metadataPath;

    protected array $metadata = [];

    protected string $lastPredictionSource = 'unavailable';

    protected array $lastPredictionWarnings = [];

    protected bool $lastPredictionWasConstrained = false;

    protected array $lastPredictionSupport = [
        'prediction_usable' => false,
        'support_level' => 'unavailable',
        'forecast_context' => 'unknown',
        'status_reason' => 'No prediction has been produced.',
    ];

    public function __construct(?string $modelPath = null)
    {
        $this->modelPath = $modelPath ?: $this->defaultModelPath();
        $this->metadataPath = $this->modelPath.'.meta.json';
        $this->loadOrTrainModel();
    }

    /** Keep automated-test artifacts isolated from the model served by the application. */
    protected function defaultModelPath(): string
    {
        if (app()->environment('testing')) {
            $process = (string) (getenv('TEST_TOKEN') ?: getmypid());

            return storage_path("framework/testing/ml-model-{$process}.phpml");
        }

        return storage_path('app/ml_model.phpml');
    }

    protected function loadOrTrainModel(): void
    {
        if (File::exists($this->modelPath) && File::exists($this->metadataPath)) {
            try {
                $metadata = json_decode((string) File::get($this->metadataPath), true, 512, JSON_THROW_ON_ERROR);
                if (($metadata['schema_version'] ?? null) !== self::MODEL_SCHEMA_VERSION) {
                    throw new RuntimeException('Stored model metadata is outdated.');
                }
                $restored = (new ModelManager)->restoreFromFile($this->modelPath);
                if (! $restored instanceof LeastSquares) {
                    throw new RuntimeException('Stored estimator is not a LeastSquares model.');
                }
                $this->model = $restored;
                $this->metadata = $metadata;
                Log::info('ML model and metadata loaded successfully.');

                return;
            } catch (Throwable $exception) {
                Log::warning('Failed to load the stored ML model; retraining.', ['message' => $exception->getMessage()]);
            }
        }
        $this->train();
    }

    /** Synthetic examples are never mixed into real training or evaluation. */
    public function train(): bool
    {
        File::ensureDirectoryExists(dirname($this->modelPath));
        $lockHandle = fopen($this->modelPath.'.lock', 'c');
        if ($lockHandle === false) {
            throw new RuntimeException('Unable to create the model training lock.');
        }

        try {
            if (! flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire the model training lock.');
            }
            $cohortSelection = $this->selectTrainingCohort();
            $trainingCohort = $cohortSelection['records'];
            $featureNames = $cohortSelection['feature_names'];
            $strategy = $cohortSelection['strategy'];
            $sampleCount = $trainingCohort->where('data_source', 'company_inspired_sample')->count();
            $realSampleCount = $trainingCohort->count() - $sampleCount;
            $usesSampleData = $sampleCount > 0;

            if ($trainingCohort->pluck('project_id')->unique()->count() < self::MINIMUM_REAL_SAMPLES) {
                $this->createFallbackModel(
                    $realSampleCount,
                    'At least '.self::MINIMUM_REAL_SAMPLES.' eligible completed projects are required; '
                    .$trainingCohort->pluck('project_id')->unique()->count().' are available.'
                );

                return false;
            }

            $featureSelection = $this->trainingFeatureSetMetadata($trainingCohort, $featureNames, $strategy);
            $splitSelection = $this->selectChronologicalSplit($trainingCohort, $featureNames);
            $trainingData = $splitSelection['training_data'];
            $testData = $splitSelection['test_data'];
            $evaluation = $splitSelection['selected']['evaluation'];
            $comparison = $this->compareRegressionModels($trainingData, $testData, $featureNames);
            $baselineComparison = $this->budgetBaselineComparison($testData, $evaluation);

            // Deploy on all verified records after independent chronological evaluation.
            [$candidateModel, $productionTransformer] = $this->buildLeastSquaresModel($trainingCohort, $featureNames);
            $candidateMetadata = [
                'schema_version' => self::MODEL_SCHEMA_VERSION,
                'trained_at' => now()->toIso8601String(),
                'model_type' => 'least_squares_linear_regression',
                'model_source' => $usesSampleData ? 'sample_trained_model' : 'real_trained_model',
                'prediction_strategy' => $strategy,
                'prediction_target' => $strategy === 'progress_snapshot_model'
                    ? 'remaining_cost_then_add_recorded_spend'
                    : 'final_cost',
                'uses_synthetic_data' => $usesSampleData,
                'real_samples_available' => $realSampleCount,
                'sample_samples_available' => $sampleCount,
                'samples_trained' => $trainingCohort->count(),
                'training_samples_evaluated' => $trainingData->count(),
                'test_samples' => $testData->count(),
                'evaluation_method' => $splitSelection['selected']['method'],
                'evaluation' => $evaluation,
                'split_selection' => $splitSelection['summary'],
                'cross_validation' => $featureSelection['cross_validation'],
                'feature_set' => $featureSelection,
                'model_comparison' => $comparison,
                'budget_baseline_comparison' => $baselineComparison,
                'snapshot_readiness' => $cohortSelection['snapshot_readiness'],
                'data_capture_policy' => $this->dataCapturePolicy(),
                'retraining_policy' => $this->retrainingPolicy(),
                'risk_business_actions' => $this->riskBusinessActions(),
                'transformer' => $productionTransformer,
                'feature_ranges' => $this->featureRanges($trainingCohort, $featureNames),
                'sample_sufficiency' => $this->sampleSufficiency($trainingCohort->count(), $usesSampleData),
                'training_criteria' => [
                    'fixed_newest_20_percent_project_holdout', 'all_snapshots_for_a_project_stay_in_one_partition',
                    $strategy === 'progress_snapshot_model' ? 'genuine_timestamped_progress_snapshots' : 'planning_inputs_only',
                    'status_is_completed', 'completion_is_100_percent',
                    'actual_end_date_is_present', 'start_date_is_not_after_actual_end_date',
                    'persisted_budget_and_final_actual_amount_are_positive',
                    'persisted_budget_history_is_not_claimed_as_immutable_initial_budget',
                    $usesSampleData ? 'company_inspired_sample_not_verified_company_performance' : 'operational_company_records',
                ],
            ];
            $this->model = $candidateModel;
            $this->metadata = $candidateMetadata;
            $this->saveModelAndMetadata();
            Log::info('Real-data ML model trained successfully.', [
                'samples' => $trainingCohort->count(), 'holdout_samples' => $testData->count(),
                'sample_records' => $sampleCount, 'real_records' => $realSampleCount,
                'evaluation_method' => $splitSelection['selected']['method'],
            ]);

            return true;
        } catch (Throwable $exception) {
            Log::error('ML training candidate failed validation.', ['message' => $exception->getMessage()]);
            $realSampleCount = isset($realSampleCount) ? $realSampleCount : 0;
            if (! $this->restoreStoredModel()) {
                $this->createFallbackModel($realSampleCount, 'Real-data training failed: '.$exception->getMessage());
            }

            return false;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    protected function createFallbackModel(int $realSampleCount, string $reason): void
    {
        $fallbackData = $this->getFallbackData();
        [$this->model, $transformer] = $this->buildLeastSquaresModel($fallbackData, self::BASE_FEATURE_NAMES);
        $this->metadata = [
            'schema_version' => self::MODEL_SCHEMA_VERSION,
            'trained_at' => now()->toIso8601String(),
            'model_type' => 'least_squares_linear_regression',
            'model_source' => 'synthetic_fallback_model',
            'uses_synthetic_data' => true,
            'fallback_reason' => $reason,
            'real_samples_available' => $realSampleCount,
            'sample_samples_available' => 0,
            'samples_trained' => $fallbackData->count(),
            'training_samples_evaluated' => 0,
            'test_samples' => 0,
            'evaluation_method' => 'unavailable_for_synthetic_fallback',
            'evaluation' => null,
            'split_selection' => null,
            'cross_validation' => null,
            'feature_set' => [
                'selected_feature_names' => self::BASE_FEATURE_NAMES,
                'candidate_fin_feature_names' => self::FIN_FEATURE_NAMES,
                'included_fin_features' => [],
                'decision' => 'unavailable_for_synthetic_fallback',
                'significant_improvement_threshold_percent' => self::FIN_FEATURE_IMPROVEMENT_THRESHOLD_PERCENT,
            ],
            'model_comparison' => null,
            'data_capture_policy' => $this->dataCapturePolicy(),
            'retraining_policy' => $this->retrainingPolicy(),
            'risk_business_actions' => $this->riskBusinessActions(),
            'transformer' => $transformer,
            'feature_ranges' => $this->featureRanges($fallbackData, self::BASE_FEATURE_NAMES),
            'sample_sufficiency' => $this->sampleSufficiency($realSampleCount),
            'training_criteria' => ['synthetic_examples_only', 'not_company_performance_data'],
        ];
        $this->saveModelAndMetadata();
        Log::warning('Synthetic fallback model activated.', ['real_samples_available' => $realSampleCount, 'reason' => $reason]);
    }

    /** Retrieve one deduplicated, completed and auditable record per project. */
    protected function getTrainingData(): Collection
    {
        try {
            $latestBudgetIds = DB::table('budgets_tbl')
                ->select('project_id', DB::raw('MAX(budget_id) as budget_id'))
                ->groupBy('project_id');
            $finExpenses = $this->finExpenseAggregateQuery();
            $actualExpenses = $this->finExpenseAggregateQuery(false);
            $actualCost = $actualExpenses === null
                ? 'COALESCE(budgets_tbl.actual_amount, 0)'
                : 'COALESCE(NULLIF(fin_actuals.fin_total_expense, 0), budgets_tbl.actual_amount, 0)';
            $projectTypeSelect = $this->projectTypeSelect();
            $hasDataSource = Schema::hasColumn('project_tbl', 'data_source');
            $query = DB::table('project_tbl')
                ->joinSub($latestBudgetIds, 'latest_budget', fn ($join) => $join->on('project_tbl.project_id', '=', 'latest_budget.project_id'))
                ->join('budgets_tbl', 'latest_budget.budget_id', '=', 'budgets_tbl.budget_id');

            if ($finExpenses !== null) {
                $query->leftJoinSub($finExpenses, 'fin_expenses', fn ($join) => $join->on('project_tbl.project_id', '=', 'fin_expenses.project_id'));
            }
            if ($actualExpenses !== null) {
                $query->leftJoinSub($actualExpenses, 'fin_actuals', fn ($join) => $join->on('project_tbl.project_id', '=', 'fin_actuals.project_id'));
            }

            return $query
                ->select(
                    'project_tbl.project_id', 'project_tbl.project_name', 'project_tbl.start_date',
                    'project_tbl.estimated_end_date',
                    'project_tbl.actual_end_date as completed_at', 'project_tbl.worker_count',
                    'project_tbl.completion_percentage', 'project_tbl.status',
                    DB::raw($hasDataSource
                        ? "COALESCE(project_tbl.data_source, 'operational') as data_source"
                        : "'operational' as data_source"),
                    DB::raw($projectTypeSelect.' as raw_project_type'),
                    'budgets_tbl.budget_amount as budget', DB::raw("{$actualCost} as actual_cost"),
                    DB::raw($finExpenses === null ? '0 as material_cost' : 'COALESCE(fin_expenses.fin_material_expense, 0) as material_cost'),
                    DB::raw($finExpenses === null ? '0 as labor_cost' : 'COALESCE(fin_expenses.fin_labor_expense, 0) as labor_cost'),
                    DB::raw($finExpenses === null ? '0 as fin_total_expense' : 'COALESCE(fin_expenses.fin_total_expense, 0) as fin_total_expense'),
                    DB::raw($finExpenses === null ? '0 as fin_material_expense' : 'COALESCE(fin_expenses.fin_material_expense, 0) as fin_material_expense'),
                    DB::raw($finExpenses === null ? '0 as fin_labor_expense' : 'COALESCE(fin_expenses.fin_labor_expense, 0) as fin_labor_expense'),
                    DB::raw($finExpenses === null ? '0 as fin_equipment_expense' : 'COALESCE(fin_expenses.fin_equipment_expense, 0) as fin_equipment_expense'),
                    DB::raw($finExpenses === null ? '0 as fin_other_expense' : 'COALESCE(fin_expenses.fin_other_expense, 0) as fin_other_expense')
                )
                ->where('project_tbl.status', 'Completed')
                ->where('project_tbl.completion_percentage', '>=', 100)
                ->whereNotNull('project_tbl.start_date')
                ->whereNotNull('project_tbl.estimated_end_date')
                ->whereNotNull('project_tbl.actual_end_date')
                ->where('budgets_tbl.budget_amount', '>', 0)
                ->whereRaw("{$actualCost} > 0")
                ->where('project_tbl.worker_count', '>=', 1)
                ->whereColumn('project_tbl.actual_end_date', '>=', 'project_tbl.start_date')
                ->whereColumn('project_tbl.estimated_end_date', '>=', 'project_tbl.start_date')
                // Cap the newest verified cohort first, then restore chronological order for evaluation.
                ->orderByDesc('project_tbl.actual_end_date')->orderByDesc('project_tbl.project_id')->limit(500)->get()
                ->filter(function ($row) {
                    try {
                        return Carbon::parse($row->completed_at)->startOfDay()
                            ->greaterThanOrEqualTo(Carbon::parse($row->start_date)->startOfDay())
                            && is_numeric($row->worker_count) && (float) $row->worker_count >= 1;
                    } catch (Throwable) {
                        return false;
                    }
                })
                ->map(function ($row) {
                    $start = Carbon::parse($row->start_date)->startOfDay();
                    $plannedEnd = Carbon::parse($row->estimated_end_date)->startOfDay();
                    $row->duration_months = max(1, (int) $start->diffInMonths($plannedEnd));
                    $row->project_type = $this->normalizeProjectType($row->raw_project_type ?? null, $row->project_name ?? null);
                    $row->project_type_source = blank($row->raw_project_type ?? null)
                        ? 'normalized_project_name'
                        : $this->projectTypeSource();

                    return $row;
                })
                ->unique(fn ($row) => (string) $row->project_id)
                ->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])
                ->values();
        } catch (Throwable $exception) {
            Log::error('Unable to read ML training data.', ['message' => $exception->getMessage()]);

            return collect();
        }
    }

    /**
     * Prefer genuine progress observations only after every progress stage has
     * enough finalized projects. Until then, train on the planning fields that
     * are represented consistently at prediction time.
     */
    protected function selectTrainingCohort(): array
    {
        $snapshots = $this->getSnapshotTrainingData();
        $operationalSnapshots = $snapshots->where('data_source', 'operational')->values();
        $stageProjectCounts = [];
        foreach (['early', 'middle', 'late'] as $stage) {
            $stageProjectCounts[$stage] = $operationalSnapshots
                ->filter(fn ($row) => $this->progressStage((float) $row->completion_percentage) === $stage)
                ->pluck('project_id')->unique()->count();
        }
        $snapshotProjects = $operationalSnapshots->pluck('project_id')->unique()->count();
        $ready = $snapshotProjects >= self::MINIMUM_REAL_SAMPLES
            && collect($stageProjectCounts)->every(fn ($count) => $count >= self::SNAPSHOT_MINIMUM_PROJECTS_PER_STAGE);
        $readiness = [
            'eligible' => $ready,
            'finalized_snapshot_rows' => $snapshots->count(),
            'finalized_projects' => $snapshotProjects,
            'minimum_projects_per_stage' => self::SNAPSHOT_MINIMUM_PROJECTS_PER_STAGE,
            'projects_by_stage' => $stageProjectCounts,
            'activation_rule' => 'At least 10 finalized projects represented in each early, middle, and late progress stage.',
            'note' => 'Only snapshots captured by the application at the time of a real data change are eligible; no history is reconstructed.',
        ];

        if ($ready) {
            return [
                'records' => $operationalSnapshots,
                'feature_names' => self::SNAPSHOT_FEATURE_NAMES,
                'strategy' => 'progress_snapshot_model',
                'snapshot_readiness' => $readiness,
            ];
        }

        return [
            'records' => $this->getTrainingData()
                ->unique(fn ($row) => (string) $row->project_id)
                ->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])->values(),
            'feature_names' => self::PLANNING_FEATURE_NAMES,
            'strategy' => 'planning_only_baseline',
            'snapshot_readiness' => $readiness,
        ];
    }

    protected function getSnapshotTrainingData(): Collection
    {
        if (! Schema::hasTable('ml_project_cost_snapshots')) {
            return collect();
        }

        return DB::table('ml_project_cost_snapshots as snapshot')
            ->join('project_tbl as project', 'project.project_id', '=', 'snapshot.project_id')
            ->select(
                'snapshot.snapshot_id', 'snapshot.project_id', 'snapshot.captured_at',
                'project.actual_end_date as completed_at', 'snapshot.planned_budget as budget',
                'snapshot.planned_duration_months as duration_months', 'snapshot.elapsed_duration_months',
                'snapshot.worker_count', 'snapshot.phase',
                'snapshot.completion_percentage',
                'snapshot.cumulative_material_expense as material_cost',
                'snapshot.cumulative_labor_expense as labor_cost',
                'snapshot.cumulative_total_expense as fin_total_expense',
                'snapshot.cumulative_material_expense as fin_material_expense',
                'snapshot.cumulative_labor_expense as fin_labor_expense',
                'snapshot.cumulative_equipment_expense as fin_equipment_expense',
                'snapshot.cumulative_other_expense as fin_other_expense',
                'snapshot.final_actual_cost as actual_cost', 'snapshot.data_source',
                'project.project_name'
            )
            ->whereNotNull('snapshot.final_actual_cost')
            ->whereNotNull('snapshot.finalized_at')
            ->whereColumn('snapshot.captured_at', '<=', 'snapshot.finalized_at')
            ->where('snapshot.finalized_at', '<=', now())
            ->where('snapshot.final_actual_cost', '>', 0)
            ->where('snapshot.planned_budget', '>', 0)
            ->where('snapshot.completion_percentage', '>=', 0)
            ->where('snapshot.completion_percentage', '<', 100)
            ->where('project.status', 'Completed')
            ->where('project.completion_percentage', '>=', 100)
            ->whereNotNull('project.actual_end_date')
            ->orderBy('project.actual_end_date')->orderBy('snapshot.project_id')->orderBy('snapshot.captured_at')
            ->get()
            ->map(function ($row) {
                $row->actual_cost = max(0.0, (float) $row->actual_cost - (float) $row->fin_total_expense);
                $row->project_type = $this->normalizeProjectType(null, $row->project_name ?? null);
                $row->project_type_source = 'normalized_project_name';

                return $row;
            });
    }

    /**
     * Build finance signals exactly as they would have been known before the
     * completed project's outcome.  Expenses entered on or after actual_end_date
     * can be close-out corrections, so including them would leak the target.
     */
    protected function finExpenseAggregateQuery(bool $beforeProjectCompletion = true): mixed
    {
        if (! Schema::hasTable('fin_expense_tbl')
            || ! Schema::hasTable('fin_expense_category_tbl')
            || ! Schema::hasColumn('fin_expense_tbl', 'project_id')
            || ! Schema::hasColumn('fin_expense_tbl', 'fin_category_id')
            || ! Schema::hasColumn('fin_expense_tbl', 'expense_date')) {
            return null;
        }

        $amountColumn = Schema::hasColumn('fin_expense_tbl', 'amount') ? 'amount'
            : (Schema::hasColumn('fin_expense_tbl', 'actual_amount') ? 'actual_amount' : null);
        if ($amountColumn === null) {
            return null;
        }

        $amount = "COALESCE(fin_expense.{$amountColumn}, 0)";
        $component = $this->finExpenseComponentExpression();

        $query = DB::table('fin_expense_tbl as fin_expense')
            ->join('project_tbl as finance_project', 'finance_project.project_id', '=', 'fin_expense.project_id')
            ->join('fin_expense_category_tbl as fin_category', 'fin_category.fin_category_id', '=', 'fin_expense.fin_category_id')
            ->select('fin_expense.project_id')
            ->whereNotNull('fin_expense.project_id')
            ->selectRaw("COALESCE(SUM({$amount}), 0) as fin_total_expense")
            ->selectRaw('MAX(fin_expense.expense_date) as finance_as_of_date');

        if ($beforeProjectCompletion) {
            $query->whereNotNull('fin_expense.expense_date')
                ->whereNotNull('finance_project.actual_end_date')
                ->whereColumn('fin_expense.expense_date', '<', 'finance_project.actual_end_date');
        }

        foreach (['material', 'labor', 'equipment', 'other'] as $name) {
            $expression = "COALESCE(SUM(CASE WHEN {$component} = '{$name}' THEN {$amount} ELSE 0 END), 0)";
            $query->selectRaw("{$expression} as fin_{$name}_expense");
        }

        return $query->groupBy('fin_expense.project_id');
    }

    /** Current, incomplete projects whose model inputs can be derived from recorded system data. */
    public function getPredictionProjects(): Collection
    {
        $latestBudgetIds = DB::table('budgets_tbl')
            ->select('project_id', DB::raw('MAX(budget_id) as budget_id'))
            ->groupBy('project_id');
        $finance = $this->finExpenseAggregateQuery(false);
        if ($finance !== null) {
            $finance->whereDate('fin_expense.expense_date', '<=', now()->toDateString());
        }

        $query = DB::table('project_tbl')
            ->joinSub($latestBudgetIds, 'latest_budget', fn ($join) => $join->on('project_tbl.project_id', '=', 'latest_budget.project_id'))
            ->join('budgets_tbl', 'latest_budget.budget_id', '=', 'budgets_tbl.budget_id');
        if ($finance !== null) {
            $query->leftJoinSub($finance, 'prediction_finance', fn ($join) => $join->on('project_tbl.project_id', '=', 'prediction_finance.project_id'));
        }

        return $query->select(
            'project_tbl.project_id', 'project_tbl.project_name', 'project_tbl.status',
            'project_tbl.start_date', 'project_tbl.estimated_end_date',
            'project_tbl.worker_count', 'project_tbl.completion_percentage',
            'budgets_tbl.budget_amount as budget',
            DB::raw($finance === null ? '0 as fin_total_expense' : 'COALESCE(prediction_finance.fin_total_expense, 0) as fin_total_expense'),
            DB::raw($finance === null ? '0 as fin_material_expense' : 'COALESCE(prediction_finance.fin_material_expense, 0) as fin_material_expense'),
            DB::raw($finance === null ? '0 as fin_labor_expense' : 'COALESCE(prediction_finance.fin_labor_expense, 0) as fin_labor_expense'),
            DB::raw($finance === null ? '0 as fin_equipment_expense' : 'COALESCE(prediction_finance.fin_equipment_expense, 0) as fin_equipment_expense'),
            DB::raw($finance === null ? '0 as fin_other_expense' : 'COALESCE(prediction_finance.fin_other_expense, 0) as fin_other_expense'),
            DB::raw($finance === null ? 'NULL as finance_as_of_date' : 'prediction_finance.finance_as_of_date')
        )
            ->whereNotNull('project_tbl.start_date')
            ->whereNotNull('project_tbl.estimated_end_date')
            ->whereColumn('project_tbl.estimated_end_date', '>=', 'project_tbl.start_date')
            ->where('project_tbl.worker_count', '>=', 1)
            ->where('project_tbl.completion_percentage', '<', 100)
            ->where('budgets_tbl.budget_amount', '>', 0)
            ->where(fn ($builder) => $builder->whereNull('project_tbl.status')->orWhere('project_tbl.status', '!=', 'Completed'))
            ->orderBy('project_tbl.project_name')
            ->get()
            ->map(function ($project) {
                $duration = max(1, Carbon::parse($project->start_date)->startOfDay()
                    ->diffInMonths(Carbon::parse($project->estimated_end_date)->startOfDay()));

                return [
                    'project_id' => (int) $project->project_id,
                    'project_name' => $project->project_name ?: 'Project #'.$project->project_id,
                    'status' => $project->status ?: 'Unspecified',
                    'budget' => (float) $project->budget,
                    'duration' => (int) $duration,
                    'workers' => (int) $project->worker_count,
                    'completion' => (float) $project->completion_percentage,
                    'material_cost' => (float) $project->fin_material_expense,
                    'labor_cost' => (float) $project->fin_labor_expense,
                    'fin_total_expense' => (float) $project->fin_total_expense,
                    'fin_material_expense' => (float) $project->fin_material_expense,
                    'fin_labor_expense' => (float) $project->fin_labor_expense,
                    'fin_equipment_expense' => (float) $project->fin_equipment_expense,
                    'fin_other_expense' => (float) $project->fin_other_expense,
                    'finance_as_of_date' => $project->finance_as_of_date,
                ];
            })->values();
    }

    protected function finExpenseComponentExpression(): string
    {
        return 'CASE
            WHEN '.$this->finCategoryLikeClause(['material', 'materials', 'supply', 'supplies', 'cement', 'steel', 'sand', 'gravel', 'aggregate', 'lumber', 'hardware'])." THEN 'material'
            WHEN ".$this->finCategoryLikeClause(['labor', 'labour', 'salary', 'salaries', 'wage', 'wages', 'payroll', 'worker', 'manpower'])." THEN 'labor'
            WHEN ".$this->finCategoryLikeClause(['equipment', 'machine', 'machinery', 'backhoe', 'rental', 'repair', 'maintenance', 'fuel', 'diesel', 'gasoline'])." THEN 'equipment'
            WHEN LOWER(COALESCE(fin_expense.project_cost_component, '')) IN ('material', 'labor', 'equipment', 'other')
                THEN LOWER(fin_expense.project_cost_component)
            ELSE 'other'
        END";
    }

    protected function finCategoryLikeClause(array $terms): string
    {
        return collect($terms)->map(function (string $term) {
            $term = str_replace("'", "''", strtolower($term));

            return "LOWER(COALESCE(fin_category.category_code, '')) LIKE '%{$term}%'
                OR LOWER(COALESCE(fin_category.category_name, '')) LIKE '%{$term}%'";
        })->implode(' OR ');
    }

    protected function projectTypeSelect(): string
    {
        foreach (['project_type', 'type', 'category'] as $column) {
            if (Schema::hasColumn('project_tbl', $column)) {
                return "project_tbl.{$column}";
            }
        }

        return 'NULL';
    }

    protected function projectTypeSource(): string
    {
        foreach (['project_type', 'type', 'category'] as $column) {
            if (Schema::hasColumn('project_tbl', $column)) {
                return "project_tbl.{$column}";
            }
        }

        return 'normalized_project_name';
    }

    protected function normalizeProjectType(mixed $rawType, ?string $projectName): string
    {
        $value = strtolower(trim((string) $rawType));
        if ($value !== '') {
            return ucwords(str_replace(['_', '-'], ' ', $value));
        }

        $normalizedName = preg_replace('/\s*-\s*site\s*#?\d+\s*$/i', '', trim((string) $projectName));
        $normalizedName = preg_replace('/\s+/', ' ', (string) $normalizedName);
        $name = strtolower((string) $normalizedName);

        return match (true) {
            str_contains($name, 'road'), str_contains($name, 'highway') => 'Roadwork',
            str_contains($name, 'bridge') => 'Bridge',
            str_contains($name, 'building'), str_contains($name, 'office') => 'Building',
            str_contains($name, 'residential'), str_contains($name, 'house') => 'Residential',
            str_contains($name, 'drainage'), str_contains($name, 'canal') => 'Drainage',
            default => $normalizedName !== '' ? $normalizedName : 'General Construction',
        };
    }

    protected function selectTrainingFeatureSet(Collection $realData): array
    {
        $baseCv = $this->kFoldCrossValidation($realData, self::BASE_FEATURE_NAMES);
        $financeRows = $realData->filter(fn ($row) => $this->hasAnyFinanceFeatureValue($row))->count();
        $financeCv = null;
        $selectedFeatureNames = self::BASE_FEATURE_NAMES;
        $decision = 'base_features_selected';
        $improvementPoints = null;

        if ($financeRows >= self::MINIMUM_REAL_SAMPLES) {
            $financeCv = $this->kFoldCrossValidation($realData, self::FINANCE_ENRICHED_FEATURE_NAMES);
            $baseMape = $baseCv['average_mean_absolute_percentage_error'] ?? null;
            $financeMape = $financeCv['average_mean_absolute_percentage_error'] ?? null;
            $improvementPoints = is_numeric($baseMape) && is_numeric($financeMape)
                ? ((float) $baseMape - (float) $financeMape)
                : null;
            if ($improvementPoints !== null && $improvementPoints >= self::FIN_FEATURE_IMPROVEMENT_THRESHOLD_PERCENT) {
                $selectedFeatureNames = self::FINANCE_ENRICHED_FEATURE_NAMES;
                $decision = 'finance_features_selected_significant_cross_validated_mape_improvement';
            } else {
                $decision = 'finance_features_rejected_below_significance_threshold';
            }
        } else {
            $decision = 'finance_features_unavailable_insufficient_fin_rows';
        }

        return [
            'selected_feature_names' => $selectedFeatureNames,
            'candidate_fin_feature_names' => self::FIN_FEATURE_NAMES,
            'included_fin_features' => array_values(array_diff($selectedFeatureNames, self::BASE_FEATURE_NAMES)),
            'decision' => $decision,
            'significant_improvement_threshold_percent' => self::FIN_FEATURE_IMPROVEMENT_THRESHOLD_PERCENT,
            'finance_rows_available' => $financeRows,
            'mape_improvement_points' => $improvementPoints === null ? null : round($improvementPoints, 4),
            'baseline_cross_validation' => $baseCv,
            'finance_cross_validation' => $financeCv,
            'finance_feature_leakage_note' => 'Finance expense totals use only rows dated strictly before actual_end_date for historical evaluation. At prediction time they must be cumulative through the declared finance_as_of_date. They are selected only when deterministic k-fold MAPE improves by the documented threshold; otherwise the production model uses the original project/budget/labor/material fields.',
            'cross_validation' => $selectedFeatureNames === self::BASE_FEATURE_NAMES ? $baseCv : $financeCv,
        ];
    }

    protected function trainingFeatureSetMetadata(Collection $records, array $featureNames, string $strategy): array
    {
        $crossValidation = $this->kFoldCrossValidation($records, $featureNames);

        return [
            'selected_feature_names' => array_values($featureNames),
            'candidate_fin_feature_names' => self::FIN_FEATURE_NAMES,
            'included_fin_features' => array_values(array_intersect($featureNames, self::FIN_FEATURE_NAMES)),
            'decision' => $strategy === 'progress_snapshot_model'
                ? 'genuine_progress_snapshot_features_selected'
                : 'planning_only_features_selected_until_snapshot_stage_coverage_is_sufficient',
            'cross_validation' => $crossValidation,
            'leakage_controls' => [
                'No reconstructed historical progress is used.',
                'Planning duration uses start_date to estimated_end_date, never actual duration.',
                'Completion and cumulative expense fields are used only from timestamped snapshots.',
                'Every snapshot for one project remains in the same evaluation partition.',
            ],
        ];
    }

    protected function hasAnyFinanceFeatureValue(object $row): bool
    {
        foreach (array_diff(self::FINANCE_ENRICHED_FEATURE_NAMES, self::BASE_FEATURE_NAMES) as $name) {
            if ((float) ($row->{$name} ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function bestChronologicalSplit(Collection $realData, array $featureNames): array
    {
        $options = [
            $this->evaluateChronologicalSplit($realData, $featureNames, 0.20, 'chronological_80_20_holdout'),
            $this->evaluateChronologicalSplit($realData, $featureNames, 0.30, 'chronological_70_30_holdout'),
        ];
        usort($options, function (array $left, array $right): int {
            $mae = ($left['evaluation']['mean_absolute_error'] ?? INF) <=> ($right['evaluation']['mean_absolute_error'] ?? INF);
            if ($mae !== 0) {
                return $mae;
            }
            $mape = ($left['evaluation']['mean_absolute_percentage_error'] ?? INF) <=> ($right['evaluation']['mean_absolute_percentage_error'] ?? INF);
            if ($mape !== 0) {
                return $mape;
            }

            return $right['training_samples'] <=> $left['training_samples'];
        });

        return [
            'selected' => $options[0],
            'options' => $options,
        ];
    }

    protected function selectChronologicalSplit(Collection $realData, array $featureNames): array
    {
        $selection = $this->evaluateChronologicalSplit(
            $realData,
            $featureNames,
            0.20,
            'fixed_grouped_chronological_80_20_holdout'
        );

        return [
            'selected' => $selection,
            'summary' => [
                'selected_method' => $selection['method'],
                'selection_metric' => 'predeclared_not_selected_from_test_performance',
                'scoring_rule' => 'The newest 20% of projects are always the untouched holdout.',
                'options' => [$this->splitSummary($selection)],
            ],
            'training_data' => $selection['training_data'],
            'test_data' => $selection['test_data'],
        ];
    }

    protected function evaluateChronologicalSplit(Collection $realData, array $featureNames, float $testRatio, string $method): array
    {
        $projects = $this->chronologicalSort($realData)
            ->groupBy(fn ($row) => (string) $row->project_id)
            ->map(fn (Collection $rows, string $projectId) => (object) [
                'project_id' => $projectId,
                'completed_at' => $rows->max('completed_at'),
            ]);
        $projects = $this->chronologicalSort($projects)->values();
        $testProjectCount = max(1, (int) ceil($projects->count() * $testRatio));
        $testProjectIds = $projects->slice($projects->count() - $testProjectCount)
            ->pluck('project_id')->map(fn ($id) => (string) $id)->all();
        $testLookup = array_fill_keys($testProjectIds, true);
        $trainingData = $realData->filter(fn ($row) => ! isset($testLookup[(string) $row->project_id]))->values();
        $testData = $realData->filter(fn ($row) => isset($testLookup[(string) $row->project_id]))->values();
        if ($trainingData->count() < 2 || $testData->isEmpty()) {
            throw new RuntimeException('The grouped chronological holdout does not contain enough training and test records.');
        }
        [$model, $transformer] = $this->buildLeastSquaresModel($trainingData, $featureNames);
        $evaluation = $this->evaluateModel($model, $transformer, $testData);

        return [
            'method' => $method,
            'test_ratio' => $testRatio,
            'training_samples' => $trainingData->count(),
            'test_samples' => $testData->count(),
            'training_projects' => $trainingData->pluck('project_id')->unique()->count(),
            'test_projects' => $testData->pluck('project_id')->unique()->count(),
            'feature_names' => array_values($featureNames),
            'evaluation' => $evaluation,
            'training_data' => $trainingData,
            'test_data' => $testData,
        ];
    }

    protected function chronologicalSort(Collection $records): Collection
    {
        return $records->sort(function ($left, $right): int {
            $dateOrder = strcmp((string) ($left->completed_at ?? ''), (string) ($right->completed_at ?? ''));

            return $dateOrder !== 0
                ? $dateOrder
                : ((int) ($left->project_id ?? 0) <=> (int) ($right->project_id ?? 0));
        })->values();
    }

    protected function splitSummary(array $split): array
    {
        return [
            'method' => $split['method'],
            'test_ratio' => $split['test_ratio'],
            'training_samples' => $split['training_samples'],
            'test_samples' => $split['test_samples'],
            'mean_absolute_error' => $split['evaluation']['mean_absolute_error'] ?? null,
            'mean_absolute_percentage_error' => $split['evaluation']['mean_absolute_percentage_error'] ?? null,
            'f1_score' => $split['evaluation']['f1_score'] ?? null,
            'feature_names' => $split['feature_names'],
        ];
    }

    protected function kFoldCrossValidation(Collection $records, array $featureNames): array
    {
        $projectIds = $records->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])
            ->pluck('project_id')->map(fn ($id) => (string) $id)->unique()->values();
        $foldCount = min(self::K_FOLD_COUNT, $projectIds->count());
        $folds = [];
        for ($fold = 0; $fold < $foldCount; $fold++) {
            $testIds = $projectIds->filter(fn ($id, $index) => $index % $foldCount === $fold)->flip()->all();
            $testData = $records->filter(fn ($row) => isset($testIds[(string) $row->project_id]))->values();
            $trainingData = $records->filter(fn ($row) => ! isset($testIds[(string) $row->project_id]))->values();
            if ($trainingData->count() < 2 || $testData->isEmpty()) {
                continue;
            }

            [$model, $transformer] = $this->buildLeastSquaresModel($trainingData, $featureNames);
            $evaluation = $this->evaluateModel($model, $transformer, $testData);
            $folds[] = [
                'fold' => $fold + 1,
                'training_samples' => $trainingData->count(),
                'test_samples' => $testData->count(),
                'evaluation' => $evaluation,
            ];
        }

        $maes = array_values(array_filter(array_map(fn ($fold) => $fold['evaluation']['mean_absolute_error'] ?? null, $folds), 'is_numeric'));
        $mapes = array_values(array_filter(array_map(fn ($fold) => $fold['evaluation']['mean_absolute_percentage_error'] ?? null, $folds), 'is_numeric'));
        $f1s = array_values(array_filter(array_map(fn ($fold) => $fold['evaluation']['f1_score'] ?? null, $folds), 'is_numeric'));

        return [
            'method' => $foldCount.'-fold_cross_validation',
            'folds_requested' => self::K_FOLD_COUNT,
            'folds_run' => count($folds),
            'folding' => 'deterministic_grouped_by_project_chronological_round_robin',
            'feature_names' => array_values($featureNames),
            'average_mean_absolute_error' => $maes === [] ? null : round(array_sum($maes) / count($maes), 2),
            'average_mean_absolute_percentage_error' => $mapes === [] ? null : round(array_sum($mapes) / count($mapes), 4),
            'average_f1_score' => $f1s === [] ? null : round(array_sum($f1s) / count($f1s), 2),
            'folds' => $folds,
        ];
    }

    protected function compareRegressionModels(Collection $trainingData, Collection $testData, array $featureNames): array
    {
        [$linearModel, $linearTransformer] = $this->buildLeastSquaresModel($trainingData, $featureNames);
        $linearEvaluation = $this->evaluateModel($linearModel, $linearTransformer, $testData);

        $comparison = [
            'production_model' => 'least_squares_linear_regression',
            'comparison_model' => 'support_vector_regression_rbf',
            'selection_policy' => 'Production remains LeastSquares linear regression; compare SVR on the same holdout without serving or deploying it.',
            'models' => [
                'least_squares_linear_regression' => [
                    'status' => 'evaluated',
                    'is_production' => true,
                    'evaluation' => $linearEvaluation,
                    'persistence' => 'serialized_as_the_only_production_model',
                ],
            ],
        ];

        try {
            [$svrModel, $svrTransformer] = $this->buildSvrModel($trainingData, $featureNames);
            $svrEvaluation = $this->evaluateModel($svrModel, $svrTransformer, $testData);
            $comparison['models']['support_vector_regression_rbf'] = [
                'status' => 'evaluated',
                'is_production' => false,
                'evaluation' => $svrEvaluation,
                'persistence' => 'evaluated_in_memory_only_not_serialized_or_deployed',
            ];
        } catch (Throwable $exception) {
            $comparison['models']['support_vector_regression_rbf'] = [
                'status' => 'unavailable',
                'is_production' => false,
                'message' => $exception->getMessage(),
                'evaluation' => null,
                'persistence' => 'not_serialized_or_deployed',
            ];
        }
        $linearMae = $comparison['models']['least_squares_linear_regression']['evaluation']['mean_absolute_error'] ?? null;
        $svrMae = $comparison['models']['support_vector_regression_rbf']['evaluation']['mean_absolute_error'] ?? null;
        $svrOutperformed = is_numeric($linearMae) && is_numeric($svrMae) && (float) $svrMae < (float) $linearMae;
        $comparison['production_model_is_best_option'] = ! $svrOutperformed;
        $comparison['comparison_model_outperformed_linear'] = $svrOutperformed;
        $comparison['comparison_result'] = $svrOutperformed
            ? 'comparison_model_had_lower_holdout_mae_but_was_not_deployed'
            : 'linear_regression_was_equal_or_better_by_holdout_mae_or_comparison_unavailable';

        return $comparison;
    }

    protected function budgetBaselineComparison(Collection $testData, array $modelEvaluation): array
    {
        $isRemainingCostTarget = $testData->contains(fn ($row) => isset($row->snapshot_id));
        $actuals = $testData->map(fn ($row) => (float) $row->actual_cost
            + ($isRemainingCostTarget ? (float) ($row->fin_total_expense ?? 0) : 0.0))->values()->all();
        $budgets = $testData->map(fn ($row) => (float) $row->budget)->values()->all();
        $baseline = $this->calculateMetrics($budgets, $actuals, $budgets, $testData);
        $modelMape = $modelEvaluation['mean_absolute_percentage_error'] ?? null;
        $baselineMape = $baseline['mean_absolute_percentage_error'] ?? null;
        $improvementPoints = is_numeric($modelMape) && is_numeric($baselineMape)
            ? (float) $baselineMape - (float) $modelMape
            : null;
        $outperforms = is_numeric($modelMape) && is_numeric($baselineMape)
            && $improvementPoints >= self::MINIMUM_BASELINE_MAPE_IMPROVEMENT_POINTS;

        return [
            'baseline' => 'recorded_budget_as_final_cost_estimate',
            'untouched_holdout' => true,
            'baseline_evaluation' => $baseline,
            'model_evaluation' => $modelEvaluation,
            'minimum_mape_improvement_points' => self::MINIMUM_BASELINE_MAPE_IMPROVEMENT_POINTS,
            'mape_improvement_points' => $improvementPoints === null ? null : round($improvementPoints, 4),
            'model_outperforms_budget_baseline' => $outperforms,
            'serving_support' => $outperforms ? 'supported_by_holdout_comparison' : 'insufficient_evidence_over_budget_baseline',
        ];
    }

    protected function dataCapturePolicy(): array
    {
        return [
            'required_fields' => [
                'fin_expense_tbl.project_id',
                'fin_expense_tbl.fin_category_id',
                'fin_expense_tbl.amount',
                'fin_expense_tbl.expense_date',
                'fin_expense_category_tbl.category_code',
                'fin_expense_category_tbl.category_name',
                'fin_expense_category_tbl.classification',
                'budgets_tbl.budget_amount',
                'project_tbl.worker_count',
                'project_tbl.start_date',
                'project_tbl.estimated_end_date',
                'project_tbl.actual_end_date',
                'ml_project_cost_snapshots.captured_at',
                'ml_project_cost_snapshots.completion_percentage',
                'ml_project_cost_snapshots.cumulative_total_expense',
            ],
            'recommendation' => 'Keep planned schedules, completion, and dated finance expenses current. PFIMS records genuine append-only progress snapshots and never reconstructs historical progress.',
            'finance_fields_considered' => [
                'fin_expense_tbl.amount grouped by fin_expense_category_tbl category_code/category_name inference.',
                'fin_expense_category_tbl is the authoritative finance expense category source; expense_category_tbl is not used for ML preparation.',
                'Only finance records with expense_date strictly before the completed project actual_end_date are eligible for historical feature selection.',
                'At prediction time, finance totals must be cumulative through the supplied finance_as_of_date; otherwise they are rejected.',
            ],
        ];
    }

    protected function retrainingPolicy(): array
    {
        return [
            'scheduled_command' => 'ml:retrain',
            'cadence' => 'weekly',
            'schedule' => 'Mondays at 02:00 application time',
            'data_window' => 'Newest 500 verified completed projects, or genuine finalized snapshots when every progress stage has sufficient project coverage.',
            'minimum_real_samples' => self::MINIMUM_REAL_SAMPLES,
        ];
    }

    protected function riskBusinessActions(): array
    {
        return [
            'On track' => 'Continue normal monitoring and keep material/labor actuals updated.',
            'Low risk' => 'Review open purchase requests and update forecast inputs during the next project check-in.',
            'Moderate risk' => 'Ask the project owner to validate labor/material assumptions and document mitigation actions.',
            'High risk' => 'Escalate to finance and operations for budget review before approving additional spend.',
            'Critical risk' => 'Freeze nonessential spend, require management approval, and prepare a recovery plan.',
        ];
    }

    protected function getFallbackData(): Collection
    {
        return collect([
            (object) ['project_id' => 'synthetic-1', 'budget' => 1000000, 'duration_months' => 6, 'worker_count' => 10, 'completion_percentage' => 100, 'material_cost' => 600000, 'labor_cost' => 350000, 'actual_cost' => 950000],
            (object) ['project_id' => 'synthetic-2', 'budget' => 2000000, 'duration_months' => 8, 'worker_count' => 15, 'completion_percentage' => 100, 'material_cost' => 1200000, 'labor_cost' => 600000, 'actual_cost' => 1800000],
            (object) ['project_id' => 'synthetic-3', 'budget' => 500000, 'duration_months' => 3, 'worker_count' => 5, 'completion_percentage' => 100, 'material_cost' => 300000, 'labor_cost' => 150000, 'actual_cost' => 450000],
            (object) ['project_id' => 'synthetic-4', 'budget' => 3000000, 'duration_months' => 12, 'worker_count' => 20, 'completion_percentage' => 100, 'material_cost' => 1800000, 'labor_cost' => 1000000, 'actual_cost' => 2800000],
            (object) ['project_id' => 'synthetic-5', 'budget' => 1500000, 'duration_months' => 5, 'worker_count' => 8, 'completion_percentage' => 100, 'material_cost' => 900000, 'labor_cost' => 500000, 'actual_cost' => 1400000],
            (object) ['project_id' => 'synthetic-6', 'budget' => 800000, 'duration_months' => 4, 'worker_count' => 6, 'completion_percentage' => 100, 'material_cost' => 480000, 'labor_cost' => 280000, 'actual_cost' => 760000],
            (object) ['project_id' => 'synthetic-7', 'budget' => 2500000, 'duration_months' => 10, 'worker_count' => 18, 'completion_percentage' => 100, 'material_cost' => 1500000, 'labor_cost' => 800000, 'actual_cost' => 2300000],
            (object) ['project_id' => 'synthetic-8', 'budget' => 4000000, 'duration_months' => 14, 'worker_count' => 25, 'completion_percentage' => 100, 'material_cost' => 2400000, 'labor_cost' => 1300000, 'actual_cost' => 3700000],
        ])->map(function ($row) {
            foreach (self::FIN_FEATURE_NAMES as $name) {
                $row->{$name} = 0;
            }
            $row->project_type = 'Synthetic';
            $row->project_type_source = 'synthetic_examples_only';

            return $row;
        });
    }

    /** Keep LeastSquares, without random changes, using deterministic feature selection/scaling. */
    protected function buildLeastSquaresModel(Collection $records, array $featureNames = self::BASE_FEATURE_NAMES): array
    {
        return $this->buildRegressionModel(new LeastSquares, $records, $featureNames);
    }

    protected function buildSvrModel(Collection $records, array $featureNames): array
    {
        return $this->buildRegressionModel(new SVR(Kernel::RBF), $records, $featureNames);
    }

    protected function buildRegressionModel(Regression $model, Collection $records, array $featureNames): array
    {
        if ($records->count() < 2) {
            throw new RuntimeException('At least two records are required to build a regression model.');
        }
        $rawSamples = $records->map(fn ($row) => $this->rowToFeatures($row, $featureNames))->values()->all();
        $labels = $records->map(fn ($row) => (float) $row->actual_cost)->values()->all();
        $ranges = $this->rangesFromSamples($rawSamples, $featureNames);
        $selectedIndexes = $this->selectIndependentFeatures($rawSamples, $ranges, $featureNames);
        if ($selectedIndexes === []) {
            throw new RuntimeException('Training data has no varying independent features.');
        }
        $samples = array_map(
            fn (array $sample) => $this->transformFeatureVector($sample, $selectedIndexes, $ranges),
            $rawSamples
        );
        $model->train($samples, $labels);
        if ($model instanceof LeastSquares && (! is_finite($model->getIntercept()) || collect($model->getCoefficients())->contains(fn ($value) => ! is_finite((float) $value)))) {
            throw new RuntimeException('LeastSquares produced non-finite coefficients.');
        }

        return [$model, [
            'selected_feature_indexes' => $selectedIndexes,
            'selected_feature_names' => array_map(fn ($index) => $featureNames[$index], $selectedIndexes),
            'feature_names' => array_values($featureNames),
            'ranges' => $ranges,
            'scaling' => 'min_max',
            'excluded_features_note' => 'Constant or linearly dependent columns are excluded deterministically; accepted API fields remain unchanged.',
        ]];
    }

    protected function selectIndependentFeatures(array $samples, array $ranges, array $featureNames): array
    {
        $candidates = [];
        foreach ($featureNames as $index => $name) {
            if (($ranges[$index]['max'] - $ranges[$index]['min']) > 0.000000001) {
                $candidates[] = $index;
            }
        }
        $selected = [];
        $matrix = array_fill(0, count($samples), [1.0]);
        $rank = $this->matrixRank($matrix);
        foreach ($candidates as $index) {
            if (count($selected) + 1 >= count($samples)) {
                break;
            }
            $candidateMatrix = $matrix;
            foreach ($samples as $rowIndex => $sample) {
                $candidateMatrix[$rowIndex][] = ($sample[$index] - $ranges[$index]['min'])
                    / ($ranges[$index]['max'] - $ranges[$index]['min']);
            }
            $candidateRank = $this->matrixRank($candidateMatrix);
            if ($candidateRank > $rank) {
                $selected[] = $index;
                $matrix = $candidateMatrix;
                $rank = $candidateRank;
            }
        }

        return $selected;
    }

    protected function matrixRank(array $matrix, float $epsilon = 0.000000001): int
    {
        if ($matrix === [] || $matrix[0] === []) {
            return 0;
        }
        $rows = count($matrix);
        $columns = count($matrix[0]);
        $rank = 0;
        for ($column = 0; $column < $columns && $rank < $rows; $column++) {
            $pivot = $rank;
            for ($row = $rank + 1; $row < $rows; $row++) {
                if (abs($matrix[$row][$column]) > abs($matrix[$pivot][$column])) {
                    $pivot = $row;
                }
            }
            if (abs($matrix[$pivot][$column]) <= $epsilon) {
                continue;
            }
            [$matrix[$rank], $matrix[$pivot]] = [$matrix[$pivot], $matrix[$rank]];
            $pivotValue = $matrix[$rank][$column];
            for ($currentColumn = $column; $currentColumn < $columns; $currentColumn++) {
                $matrix[$rank][$currentColumn] /= $pivotValue;
            }
            for ($row = 0; $row < $rows; $row++) {
                if ($row === $rank) {
                    continue;
                }
                $factor = $matrix[$row][$column];
                for ($currentColumn = $column; $currentColumn < $columns; $currentColumn++) {
                    $matrix[$row][$currentColumn] -= $factor * $matrix[$rank][$currentColumn];
                }
            }
            $rank++;
        }

        return $rank;
    }

    protected function rowToFeatures(object $row, array $featureNames = self::BASE_FEATURE_NAMES): array
    {
        return array_map(fn (string $name) => $this->featureValue($row, $name), $featureNames);
    }

    protected function featureValue(object $row, string $name): float
    {
        return match ($name) {
            'budget' => (float) $row->budget,
            'duration_months' => max(1, (float) $row->duration_months),
            'worker_count' => max(1, (float) $row->worker_count),
            'completion_percentage' => min(100, max(0, (float) $row->completion_percentage)),
            'material_cost', 'labor_cost',
            'fin_total_expense', 'fin_material_expense', 'fin_labor_expense',
            'fin_equipment_expense', 'fin_other_expense' => max(0, (float) ($row->{$name} ?? 0)),
            default => 0.0,
        };
    }

    protected function rangesFromSamples(array $samples, array $featureNames): array
    {
        $ranges = [];
        foreach ($featureNames as $index => $name) {
            $values = array_column($samples, $index);
            $ranges[$index] = ['name' => $name, 'min' => (float) min($values), 'max' => (float) max($values)];
        }

        return $ranges;
    }

    protected function transformFeatureVector(array $features, array $selectedIndexes, array $ranges): array
    {
        return array_map(function ($index) use ($features, $ranges) {
            $span = $ranges[$index]['max'] - $ranges[$index]['min'];

            return $span > 0 ? ($features[$index] - $ranges[$index]['min']) / $span : 0.0;
        }, $selectedIndexes);
    }

    protected function featureRanges(Collection $records, array $featureNames = self::BASE_FEATURE_NAMES): array
    {
        $ranges = $this->rangesFromSamples($records->map(fn ($row) => $this->rowToFeatures($row, $featureNames))->all(), $featureNames);

        return collect($ranges)->mapWithKeys(fn ($range) => [
            $range['name'] => ['min' => $range['min'], 'max' => $range['max']],
        ])->all();
    }

    protected function evaluateModel(Regression $model, array $transformer, Collection $testData): array
    {
        $predictions = $actuals = $budgets = [];
        $featureNames = $transformer['feature_names'] ?? self::BASE_FEATURE_NAMES;
        foreach ($testData as $row) {
            $transformed = $this->transformFeatureVector(
                $this->rowToFeatures($row, $featureNames),
                $transformer['selected_feature_indexes'],
                $transformer['ranges']
            );
            $prediction = (float) $model->predict($transformed);
            if (! is_finite($prediction)) {
                throw new RuntimeException('Holdout evaluation produced a non-finite prediction.');
            }
            $recordedSpend = isset($row->snapshot_id) ? (float) ($row->fin_total_expense ?? 0) : 0.0;
            $predictions[] = $prediction + $recordedSpend;
            $actuals[] = (float) $row->actual_cost + $recordedSpend;
            $budgets[] = (float) $row->budget;
        }

        return $this->calculateMetrics($predictions, $actuals, $budgets, $testData);
    }

    protected function calculateMetrics(array $predictions, array $actuals, array $budgets, ?Collection $rows = null): array
    {
        $count = count($predictions);
        if ($count === 0) {
            throw new RuntimeException('No holdout predictions are available.');
        }
        $absoluteErrors = $percentageErrors = [];
        foreach ($predictions as $index => $prediction) {
            $absoluteErrors[] = abs($prediction - $actuals[$index]);
            if ($actuals[$index] > 0) {
                $percentageErrors[] = abs(($actuals[$index] - $prediction) / $actuals[$index]) * 100;
            }
        }
        $mae = array_sum($absoluteErrors) / $count;
        $mape = $percentageErrors === [] ? null : array_sum($percentageErrors) / count($percentageErrors);
        $accuracy = $mape === null ? null : max(0, 100 - $mape);

        $meanActual = array_sum($actuals) / count($actuals);
        $totalSumSquares = $residualSumSquares = 0.0;
        foreach ($actuals as $index => $actual) {
            $totalSumSquares += ($actual - $meanActual) ** 2;
            $residualSumSquares += ($actual - $predictions[$index]) ** 2;
        }
        $rSquared = $totalSumSquares > 0 ? 1 - ($residualSumSquares / $totalSumSquares) : null;

        $tp = $fp = $tn = $fn = 0;
        foreach ($predictions as $index => $prediction) {
            $actualRisk = $actuals[$index] > $budgets[$index] * (1 + self::OVERRUN_RISK_TOLERANCE);
            $predictedRisk = $prediction > $budgets[$index] * (1 + self::OVERRUN_RISK_TOLERANCE);
            if ($actualRisk && $predictedRisk) {
                $tp++;
            } elseif (! $actualRisk && $predictedRisk) {
                $fp++;
            } elseif ($actualRisk) {
                $fn++;
            } else {
                $tn++;
            }
        }
        $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : null;
        $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : null;
        $f1 = null;
        if ($precision !== null && $recall !== null) {
            $f1 = ($precision + $recall) > 0 ? 2 * (($precision * $recall) / ($precision + $recall)) : 0.0;
        }

        $metrics = [
            'accuracy' => $accuracy === null ? null : round($accuracy, 2),
            'mean_absolute_error' => round($mae, 2),
            'mean_absolute_percentage_error' => $mape === null ? null : round($mape, 2),
            'r_squared' => $rSquared === null ? null : round($rSquared, 4),
            'precision' => $precision === null ? null : round($precision * 100, 2),
            'recall' => $recall === null ? null : round($recall * 100, 2),
            'f1_score' => $f1 === null ? null : round($f1 * 100, 2),
            'overrun_classification_accuracy' => round((($tp + $tn) / $count) * 100, 2),
            'classification_counts' => compact('tp', 'fp', 'tn', 'fn'),
        ];

        if ($rows !== null) {
            $metrics['monitoring_segments'] = $this->monitoringSegmentMetrics($rows, $predictions, $actuals, $budgets);
        }

        return $metrics;
    }

    protected function monitoringSegmentMetrics(Collection $rows, array $predictions, array $actuals, array $budgets): array
    {
        return [
            'by_project_size' => $this->metricsBySegment($rows, $predictions, $actuals, $budgets, fn ($row) => $this->projectSizeBucket((float) $row->budget)),
            'by_project_type' => $this->metricsBySegment($rows, $predictions, $actuals, $budgets, fn ($row) => (string) ($row->project_type ?? 'General Construction')),
            'by_progress_stage' => $this->metricsBySegment($rows, $predictions, $actuals, $budgets, fn ($row) => $this->progressStage((float) ($row->completion_percentage ?? 0))),
            'project_type_source' => $rows->pluck('project_type_source')->filter()->unique()->values()->all() ?: ['not_available'],
            'note' => 'Project type uses project_tbl project_type/type/category when populated; otherwise it is a normalized project name (with a trailing " - Site N" removed) and keyword categories such as Roadwork or Building when evident.',
        ];
    }

    protected function progressStage(float $completion): string
    {
        return match (true) {
            $completion <= 33.0 => 'early',
            $completion <= 66.0 => 'middle',
            default => 'late',
        };
    }

    protected function metricsBySegment(Collection $rows, array $predictions, array $actuals, array $budgets, callable $segmenter): array
    {
        $groups = [];
        foreach ($rows->values() as $index => $row) {
            $segment = $segmenter($row);
            $groups[$segment]['predictions'][] = $predictions[$index];
            $groups[$segment]['actuals'][] = $actuals[$index];
            $groups[$segment]['budgets'][] = $budgets[$index];
        }

        return collect($groups)->map(function (array $group) {
            $metrics = $this->calculateMetrics($group['predictions'], $group['actuals'], $group['budgets']);

            return [
                'samples' => count($group['predictions']),
                'mean_absolute_error' => $metrics['mean_absolute_error'],
                'mean_absolute_percentage_error' => $metrics['mean_absolute_percentage_error'],
                'precision' => $metrics['precision'],
                'recall' => $metrics['recall'],
                'f1_score' => $metrics['f1_score'],
                'classification_counts' => $metrics['classification_counts'],
            ];
        })->all();
    }

    protected function projectSizeBucket(float $budget): string
    {
        return match (true) {
            $budget < 1000000 => 'Small (< 1M)',
            $budget < 5000000 => 'Medium (1M-5M)',
            default => 'Large (>= 5M)',
        };
    }

    public function predict(array $features): float
    {
        $features = $this->normalizePredictionFeatures($features);
        $this->validateFeatureVector($features);
        $this->lastPredictionWarnings = $this->predictionWarnings($features);
        $this->lastPredictionWasConstrained = false;
        if (! $this->model) {
            throw new RuntimeException('Model not trained. Please train the model first.');
        }
        try {
            $transformer = $this->metadata['transformer'] ?? null;
            if (! is_array($transformer)) {
                throw new RuntimeException('Model transformation metadata is unavailable.');
            }
            $featureNames = $transformer['feature_names'] ?? self::BASE_FEATURE_NAMES;
            $transformed = $this->transformFeatureVector(
                $this->predictionFeatureVector($features, $featureNames),
                $transformer['selected_feature_indexes'],
                $transformer['ranges']
            );
            $prediction = (float) $this->model->predict($transformed);
            if (! is_finite($prediction) || $prediction <= 0) {
                throw new RuntimeException('Model returned an invalid project cost.');
            }
            $this->lastPredictionSource = $this->metadata['model_source'] ?? 'trained_model';
            if (($this->metadata['prediction_target'] ?? 'final_cost') === 'remaining_cost_then_add_recorded_spend') {
                $prediction += $this->recordedSpend($features);
            }
        } catch (Throwable $exception) {
            Log::error('Trained-model prediction failed; applying the rule-based estimate.', ['message' => $exception->getMessage()]);
            $this->lastPredictionSource = 'rule_based_fallback';
            $this->lastPredictionWarnings[] = 'The trained estimator failed, so this result uses the rule-based fallback formula.';

            $prediction = $this->fallbackPrediction($features);
        }

        $recordedSpend = $this->recordedSpend($features);
        if ($prediction < $recordedSpend) {
            $prediction = $recordedSpend;
            $this->lastPredictionWasConstrained = true;
            $this->lastPredictionWarnings[] = 'The raw estimate was below recorded spending. The displayed value was raised to the recorded-spend floor and must not be treated as an on-track forecast.';
        }
        $this->lastPredictionSupport = $this->predictionSupport($features);

        return $prediction;
    }

    protected function recordedSpend(array $features): float
    {
        return max(
            (float) ($features[6] ?? 0),
            (float) ($features[4] ?? 0) + (float) ($features[5] ?? 0),
            array_sum(array_map('floatval', array_slice($features, 7, 4)))
        );
    }

    protected function predictionFeatureVector(array $features, array $featureNames): array
    {
        $byName = array_combine(self::FEATURE_NAMES, array_map('floatval', $features));

        return array_map(fn (string $name) => (float) ($byName[$name] ?? 0), $featureNames);
    }

    protected function normalizePredictionFeatures(array $features): array
    {
        $count = count($features);
        if ($count < count(self::BASE_FEATURE_NAMES) || $count > count(self::FEATURE_NAMES)) {
            throw new InvalidArgumentException('Six base project-cost features are required; finance features are optional.');
        }

        return array_pad(array_values($features), count(self::FEATURE_NAMES), 0.0);
    }

    protected function validateFeatureVector(array $features): void
    {
        foreach ($features as $index => $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException(self::FEATURE_NAMES[$index].' must be a finite number.');
            }
        }
        [$budget, $duration, $workers, $completion, $material, $labor] = array_map('floatval', $features);
        if ($budget <= 0 || $budget > 9999999999.99) {
            throw new InvalidArgumentException('Budget must be greater than zero and at most ₱9,999,999,999.99.');
        }
        if ($duration < 1 || $duration > 600 || floor($duration) !== $duration) {
            throw new InvalidArgumentException('Duration must be a whole number from 1 to 600 months.');
        }
        if ($workers < 1 || $workers > 100000 || floor($workers) !== $workers) {
            throw new InvalidArgumentException('Worker count must be a whole number from 1 to 100,000.');
        }
        if ($completion < 0 || $completion > 100) {
            throw new InvalidArgumentException('Completion percentage must be from 0 to 100.');
        }
        if ($material < 0 || $material > 9999999999.99 || $labor < 0 || $labor > 9999999999.99) {
            throw new InvalidArgumentException('Material and labor costs must be between zero and ₱9,999,999,999.99.');
        }
        foreach (array_slice($features, count(self::BASE_FEATURE_NAMES)) as $index => $value) {
            if ((float) $value < 0 || (float) $value > 9999999999.99) {
                $name = self::FEATURE_NAMES[$index + count(self::BASE_FEATURE_NAMES)];
                throw new InvalidArgumentException($name.' must be between zero and ₱9,999,999,999.99.');
            }
        }
    }

    protected function fallbackPrediction(array $features): float
    {
        [$budget, $duration, $workers] = array_map('floatval', array_slice($features, 0, 3));
        $prediction = ($budget * 0.7) + ($duration * 5000) + ($workers * 1000)
            + ((float) $features[4] * 0.5) + ((float) $features[5] * 0.5);

        return max($prediction, $budget * 0.5);
    }

    public function predictProjectCost(
        $budget,
        $durationMonths,
        $workerCount = 5,
        $completionPercentage = 0,
        $materialCost = 0,
        $laborCost = 0,
        $finTotalExpense = 0,
        $finMaterialExpense = 0,
        $finLaborExpense = 0,
        $finEquipmentExpense = 0,
        $finOtherExpense = 0
    ): float {
        return $this->predict(array_map('floatval', [
            $budget, $durationMonths, $workerCount, $completionPercentage, $materialCost, $laborCost,
            $finTotalExpense, $finMaterialExpense, $finLaborExpense, $finEquipmentExpense, $finOtherExpense,
        ]));
    }

    public function getLastPredictionSource(): string
    {
        return $this->lastPredictionSource;
    }

    public function getLastPredictionWarnings(): array
    {
        return array_values(array_unique($this->lastPredictionWarnings));
    }

    public function wasLastPredictionConstrained(): bool
    {
        return $this->lastPredictionWasConstrained;
    }

    public function getLastPredictionSupport(): array
    {
        return $this->lastPredictionSupport;
    }

    protected function predictionSupport(array $features): array
    {
        $source = $this->metadata['model_source'] ?? 'unknown';
        $strategy = $this->metadata['prediction_strategy'] ?? 'unknown';
        $completion = (float) $features[3];
        $recordedSpend = max((float) $features[6], (float) $features[4] + (float) $features[5]);
        $context = $completion <= 0 && $recordedSpend <= 0 ? 'pre_start_planning' : 'ongoing_progress';
        $baselineSupported = (bool) ($this->metadata['budget_baseline_comparison']['model_outperforms_budget_baseline'] ?? false);
        $productionModelIsBest = (bool) ($this->metadata['model_comparison']['production_model_is_best_option'] ?? false);
        $snapshotReady = (bool) ($this->metadata['snapshot_readiness']['eligible'] ?? false);

        if ($source !== 'real_trained_model') {
            return [
                'prediction_usable' => false,
                'support_level' => $source === 'sample_trained_model' ? 'demo_only' : 'insufficient_evidence',
                'forecast_context' => $context,
                'status_reason' => $source === 'sample_trained_model'
                    ? 'Company-inspired sample data is not validated company performance.'
                    : 'Verified company training evidence is insufficient.',
            ];
        }
        if ($this->lastPredictionWasConstrained) {
            return [
                'prediction_usable' => false,
                'support_level' => 'safeguard_only',
                'forecast_context' => $context,
                'status_reason' => 'The raw model estimate failed the recorded-spend floor.',
            ];
        }
        if (collect($this->lastPredictionWarnings)->contains(fn ($warning) => str_contains($warning, 'outside the'))) {
            return [
                'prediction_usable' => false,
                'support_level' => 'out_of_range',
                'forecast_context' => $context,
                'status_reason' => 'One or more active inputs are outside the verified training range.',
            ];
        }
        if (! $baselineSupported) {
            return [
                'prediction_usable' => false,
                'support_level' => 'insufficient_evidence',
                'forecast_context' => $context,
                'status_reason' => 'The model has not outperformed the original-budget baseline on the untouched holdout.',
            ];
        }
        if (! $productionModelIsBest) {
            return [
                'prediction_usable' => false,
                'support_level' => 'candidate_model_not_best',
                'forecast_context' => $context,
                'status_reason' => 'The served estimator was not the best evaluated candidate on the untouched holdout.',
            ];
        }
        if ($strategy === 'progress_snapshot_model' && ! $snapshotReady) {
            return [
                'prediction_usable' => false,
                'support_level' => 'insufficient_stage_history',
                'forecast_context' => $context,
                'status_reason' => 'Genuine progress snapshots do not yet meet the required stage coverage.',
            ];
        }
        if ($strategy === 'planning_only_baseline' && $context !== 'pre_start_planning') {
            return [
                'prediction_usable' => false,
                'support_level' => 'planning_only',
                'forecast_context' => $context,
                'status_reason' => 'Progress-stage history is not yet sufficient; the active model supports planning estimates only.',
            ];
        }

        return [
            'prediction_usable' => true,
            'support_level' => $strategy === 'progress_snapshot_model' ? 'stage_validated' : 'planning_validated',
            'forecast_context' => $context,
            'status_reason' => 'The estimate is within its validated input and evaluation scope.',
        ];
    }

    public function businessActionForRiskLevel(string $riskLevel): string
    {
        return $this->riskBusinessActions()[$riskLevel]
            ?? 'Review the forecast inputs and continue monitoring the project.';
    }

    protected function predictionWarnings(array $features): array
    {
        $warnings = [];
        $source = $this->metadata['model_source'] ?? 'unknown';
        if ($source === 'synthetic_fallback_model') {
            $warnings[] = 'Insufficient verified company data: this prediction uses a synthetic fallback model and is experimental.';
        } elseif ($source === 'sample_trained_model') {
            $warnings[] = 'This prediction uses a company-inspired sample dataset; evaluation metrics are demo sample results, not company-validated accuracy.';
        }
        $sufficiency = $this->metadata['sample_sufficiency'] ?? null;
        if (is_array($sufficiency) && ($sufficiency['level'] ?? null) !== 'adequate') {
            $warnings[] = $sufficiency['message'];
        }
        $hasFinanceInputs = collect(array_slice($features, count(self::BASE_FEATURE_NAMES)))
            ->contains(fn ($value) => (float) $value > 0);
        $includedFinanceFeatures = $this->metadata['feature_set']['included_fin_features'] ?? [];
        if ($hasFinanceInputs && $includedFinanceFeatures === []) {
            $warnings[] = 'Finance totals were received but were not used because the finance feature gate has not met its cross-validated improvement threshold.';
        }
        foreach (self::FEATURE_NAMES as $index => $name) {
            $range = $this->metadata['feature_ranges'][$name] ?? null;
            if (! is_array($range)) {
                continue;
            }
            $value = (float) $features[$index];
            if ($value < $range['min'] || $value > $range['max']) {
                $rangeSource = match ($source) {
                    'real_trained_model' => 'verified training projects',
                    'sample_trained_model' => 'company-inspired sample projects',
                    default => 'synthetic fallback examples',
                };
                $warnings[] = sprintf('%s (%s) is outside the %s range of %s to %s.',
                    ucwords(str_replace('_', ' ', $name)), $this->plainNumber($value), $rangeSource,
                    $this->plainNumber((float) $range['min']), $this->plainNumber((float) $range['max'])
                );
            }
        }

        return array_values(array_unique(array_filter($warnings)));
    }

    protected function plainNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /** Time-based 30-day stock projection; compatibility response fields remain. */
    public function predictMaterialDemand(): Collection
    {
        try {
            $items = DB::table('inventory_item_tbl')
                ->select('item_id', 'item_name', 'current_stock', 'reorder_level')->orderBy('item_name')->get();
            $transactions = DB::table('inventory_transaction_tbl')
                ->select('item_id', 'project_id', 'quantity', 'transaction_date')
                ->where('transaction_type', 'OUT')->where('quantity', '>', 0)->get()
                ->groupBy(fn ($transaction) => (string) $transaction->item_id);
            $today = now()->startOfDay();
            $windowStart = $today->copy()->subDays(self::USAGE_LOOKBACK_DAYS - 1);

            return $items->mapWithKeys(function ($item) use ($transactions, $today, $windowStart) {
                $itemTransactions = $transactions->get((string) $item->item_id, collect());
                $datedTransactions = $itemTransactions->filter(function ($transaction) use ($today) {
                    if (empty($transaction->transaction_date)) {
                        return false;
                    }
                    try {
                        return Carbon::parse($transaction->transaction_date)->startOfDay()->lessThanOrEqualTo($today);
                    } catch (Throwable) {
                        return false;
                    }
                });
                $totalUsed = (float) $itemTransactions->sum(fn ($transaction) => (float) $transaction->quantity);
                $averageTransaction = $itemTransactions->isEmpty() ? 0.0 : $totalUsed / $itemTransactions->count();

                if ($datedTransactions->isNotEmpty()) {
                    $recentTransactions = $datedTransactions->filter(fn ($transaction) => Carbon::parse($transaction->transaction_date)->startOfDay()->greaterThanOrEqualTo($windowStart));
                    $firstObserved = $datedTransactions
                        ->map(fn ($transaction) => Carbon::parse($transaction->transaction_date)->startOfDay())->sort()->first();
                    $observationStart = $firstObserved->greaterThan($windowStart) ? $firstObserved : $windowStart;
                    $usageWindowDays = max(1, (int) floor($observationStart->diffInDays($today)) + 1);
                    $recentUsage = (float) $recentTransactions->sum(fn ($transaction) => (float) $transaction->quantity);
                    $averageDailyUsage = $recentUsage / $usageWindowDays;
                    $projectedDemand = $averageDailyUsage * self::FORECAST_HORIZON_DAYS;
                    $calculationMethod = 'time_based_'.self::USAGE_LOOKBACK_DAYS.'_day_usage';
                    $dataQuality = $usageWindowDays < 30 ? 'limited_history' : 'dated_history';
                } else {
                    $usageWindowDays = null;
                    $averageDailyUsage = null;
                    $projectedDemand = $averageTransaction * 1.2;
                    $calculationMethod = 'legacy_transaction_average_fallback';
                    $dataQuality = $itemTransactions->isEmpty() ? 'no_usage_history' : 'missing_transaction_dates';
                }

                $currentStock = (float) ($item->current_stock ?? 0);
                $reorderLevel = (float) ($item->reorder_level ?? 0);
                $status = $currentStock <= $reorderLevel
                    ? 'Reorder Needed'
                    : ($currentStock <= $projectedDemand ? 'Low Stock' : 'Sufficient');
                $recommendedOrder = max(0, $projectedDemand + $reorderLevel - $currentStock);
                $recommendation = match ($status) {
                    'Reorder Needed' => 'Place an order now; review the recommended quantity and supplier lead time.',
                    'Low Stock' => 'Plan replenishment within the 30-day demand horizon.',
                    default => 'Stock covers the current projection; continue monitoring usage.',
                };
                if ($calculationMethod === 'legacy_transaction_average_fallback') {
                    $recommendation .= ' Add transaction dates to enable a time-based forecast.';
                }

                return [(string) $item->item_id => [
                    'item_id' => (int) $item->item_id,
                    'item_name' => $item->item_name ?? 'Unknown',
                    'current_stock' => $currentStock,
                    'avg_usage' => round((float) ($averageDailyUsage ?? $averageTransaction), 2),
                    'average_daily_usage' => $averageDailyUsage === null ? null : round($averageDailyUsage, 2),
                    'projected_demand' => round($projectedDemand, 2),
                    'forecast_horizon_days' => $averageDailyUsage === null ? null : self::FORECAST_HORIZON_DAYS,
                    'usage_window_days' => $usageWindowDays,
                    'reorder_level' => $reorderLevel,
                    'recommended_order_quantity' => round($recommendedOrder, 2),
                    'total_used' => $totalUsed,
                    'project_count' => $itemTransactions->pluck('project_id')->filter()->unique()->count(),
                    'transaction_count' => $itemTransactions->count(),
                    'calculation_method' => $calculationMethod,
                    'data_quality' => $dataQuality,
                    'status' => $status,
                    'recommendation' => $recommendation,
                ]];
            });
        } catch (Throwable $exception) {
            Log::error('Material stock projection failed.', ['message' => $exception->getMessage()]);

            return collect();
        }
    }

    public function getModelMetrics(): array
    {
        if (! $this->model || $this->metadata === []) {
            return $this->emptyMetrics('Model not trained', 'Train the model first.');
        }
        $evaluation = $this->metadata['evaluation'] ?? null;
        $source = $this->metadata['model_source'] ?? 'unknown';
        $metrics = is_array($evaluation) ? $evaluation : [];
        $mae = $metrics['mean_absolute_error'] ?? null;
        $sufficiency = $this->metadata['sample_sufficiency'] ?? $this->sampleSufficiency(0);

        $provenanceWarnings = match ($source) {
            'sample_trained_model' => ['Company-inspired sample data is active. Reported metrics are sample evaluation only and are not validated company-performance accuracy.'],
            'synthetic_fallback_model' => ['Synthetic fallback examples are active. No unseen-project evaluation metrics are reported.'],
            default => [],
        };

        return [
            'status' => match ($source) {
                'real_trained_model' => 'Model is trained on verified completed projects',
                'sample_trained_model' => 'Model is trained on a company-inspired sample dataset',
                default => 'Synthetic fallback model is active',
            },
            'model_source' => $source,
            'uses_synthetic_data' => (bool) ($this->metadata['uses_synthetic_data'] ?? false),
            'samples_trained' => (int) ($this->metadata['samples_trained'] ?? 0),
            'real_samples_available' => (int) ($this->metadata['real_samples_available'] ?? 0),
            'sample_samples_available' => (int) ($this->metadata['sample_samples_available'] ?? 0),
            'training_samples' => (int) ($this->metadata['training_samples_evaluated'] ?? 0),
            'test_samples' => (int) ($this->metadata['test_samples'] ?? 0),
            'evaluation_method' => $this->metadata['evaluation_method'] ?? 'unavailable',
            'accuracy' => $metrics['accuracy'] ?? null,
            'mean_absolute_error' => $mae,
            'mean_absolute_percentage_error' => $metrics['mean_absolute_percentage_error'] ?? null,
            'r_squared' => $metrics['r_squared'] ?? null,
            'precision' => $metrics['precision'] ?? null,
            'recall' => $metrics['recall'] ?? null,
            'f1_score' => $metrics['f1_score'] ?? null,
            'overrun_classification_accuracy' => $metrics['overrun_classification_accuracy'] ?? null,
            'mae_formatted' => $mae === null ? 'Unavailable' : '₱'.number_format((float) $mae, 2),
            'metric_scope' => match ($source) {
                'real_trained_model' => 'fixed newest-20-percent grouped chronological project holdout',
                'sample_trained_model' => 'company-inspired sample chronological holdout; sample evaluation only, not company-validated accuracy',
                default => 'unavailable for synthetic fallback data',
            },
            'warnings' => $provenanceWarnings,
            'classification_definition' => 'Precision, recall and F1 classify project-overrun risk when cost exceeds budget by more than 5%. Values are percentages and are unavailable when the holdout has no applicable positive cases.',
            'monitoring_segments' => $metrics['monitoring_segments'] ?? null,
            'split_selection' => $this->metadata['split_selection'] ?? null,
            'cross_validation' => $this->metadata['cross_validation'] ?? null,
            'feature_set' => $this->metadata['feature_set'] ?? null,
            'model_comparison' => $this->metadata['model_comparison'] ?? null,
            'budget_baseline_comparison' => $this->metadata['budget_baseline_comparison'] ?? null,
            'snapshot_readiness' => $this->metadata['snapshot_readiness'] ?? null,
            'prediction_strategy' => $this->metadata['prediction_strategy'] ?? 'unavailable',
            'prediction_target' => $this->metadata['prediction_target'] ?? 'unavailable',
            'data_capture_policy' => $this->metadata['data_capture_policy'] ?? $this->dataCapturePolicy(),
            'retraining_policy' => $this->metadata['retraining_policy'] ?? $this->retrainingPolicy(),
            'risk_business_actions' => $this->metadata['risk_business_actions'] ?? $this->riskBusinessActions(),
            'sample_sufficiency' => $sufficiency,
            'feature_ranges' => $this->metadata['feature_ranges'] ?? [],
            'trained_at' => $this->metadata['trained_at'] ?? null,
            'fallback_reason' => $this->metadata['fallback_reason'] ?? null,
            'interpretation' => $this->getInterpretation($metrics, $sufficiency, $source),
        ];
    }

    protected function emptyMetrics(string $status, string $interpretation): array
    {
        return [
            'status' => $status, 'model_source' => 'unavailable', 'uses_synthetic_data' => false,
            'samples_trained' => 0, 'real_samples_available' => 0, 'sample_samples_available' => 0, 'training_samples' => 0,
            'test_samples' => 0, 'evaluation_method' => 'unavailable', 'accuracy' => null,
            'mean_absolute_error' => null, 'mean_absolute_percentage_error' => null,
            'r_squared' => null, 'precision' => null, 'recall' => null, 'f1_score' => null,
            'overrun_classification_accuracy' => null, 'mae_formatted' => 'Unavailable',
            'metric_scope' => 'unavailable', 'warnings' => [],
            'classification_definition' => 'Precision, recall and F1 are 5% overrun-risk classification metrics.',
            'monitoring_segments' => null, 'split_selection' => null, 'cross_validation' => null,
            'feature_set' => null, 'model_comparison' => null,
            'budget_baseline_comparison' => null, 'snapshot_readiness' => null,
            'prediction_strategy' => 'unavailable', 'prediction_target' => 'unavailable',
            'data_capture_policy' => $this->dataCapturePolicy(),
            'retraining_policy' => $this->retrainingPolicy(),
            'risk_business_actions' => $this->riskBusinessActions(),
            'sample_sufficiency' => $this->sampleSufficiency(0), 'feature_ranges' => [],
            'trained_at' => null, 'fallback_reason' => null, 'interpretation' => $interpretation,
        ];
    }

    protected function sampleSufficiency(int $samples, bool $usesSampleData = false): array
    {
        $description = $usesSampleData ? 'company-inspired sample projects' : 'verified completed projects';
        if ($samples < self::MINIMUM_REAL_SAMPLES) {
            return ['level' => 'insufficient', 'message' => "Only {$samples} {$description} are available; at least ".self::MINIMUM_REAL_SAMPLES.' are required for training and holdout evaluation.'];
        }
        if ($samples < 30) {
            return ['level' => 'experimental', 'message' => "The model uses only {$samples} {$description}; treat its results as experimental."];
        }
        if ($samples < 100) {
            return ['level' => 'limited', 'message' => "The model uses {$samples} {$description}; continue collecting varied completed-project data."];
        }

        return ['level' => 'adequate', 'message' => "The model uses {$samples} {$description} and has an adequate initial sample size."];
    }

    protected function getInterpretation(array $metrics, array $sufficiency, string $source): string
    {
        if ($source === 'synthetic_fallback_model') {
            return 'No unseen-project performance is reported because the active model uses synthetic fallback examples.';
        }
        $accuracy = $metrics['accuracy'] ?? null;
        $mae = $metrics['mean_absolute_error'] ?? null;
        if ($accuracy === null || $mae === null) {
            return 'Holdout performance is unavailable; do not use this model for financial decisions.';
        }

        $scope = $source === 'sample_trained_model'
            ? 'company-inspired sample project(s); this is sample evaluation, not company-validated accuracy'
            : 'verified completed project(s)';

        return sprintf(
            'On the newest %d unseen %s, average percentage closeness was %.2f%% and MAE was ₱%s. Data sufficiency is %s.',
            (int) ($this->metadata['test_samples'] ?? 0), $scope, $accuracy, number_format($mae, 2),
            $sufficiency['level'] ?? 'unknown'
        );
    }

    public function retrain(): array
    {
        $realModelTrained = $this->train();
        $metrics = $this->getModelMetrics();

        return [
            'message' => $realModelTrained
                ? ($metrics['model_source'] === 'sample_trained_model'
                    ? 'Model retrained on a company-inspired sample dataset; metrics are sample evaluation only.'
                    : 'Model retrained on verified completed projects.')
                : 'Retraining completed, but the transparent synthetic fallback remains active.',
            'model_source' => $metrics['model_source'], 'metrics' => $metrics,
        ];
    }

    public function getModelMetadata(): array
    {
        return $this->metadata;
    }

    protected function restoreStoredModel(): bool
    {
        if (! File::exists($this->modelPath) || ! File::exists($this->metadataPath)) {
            return false;
        }

        try {
            $metadata = json_decode((string) File::get($this->metadataPath), true, 512, JSON_THROW_ON_ERROR);
            if (($metadata['schema_version'] ?? null) !== self::MODEL_SCHEMA_VERSION) {
                return false;
            }
            $model = (new ModelManager)->restoreFromFile($this->modelPath);
            if (! $model instanceof LeastSquares) {
                return false;
            }
            $this->model = $model;
            $this->metadata = $metadata;

            return true;
        } catch (Throwable $exception) {
            Log::warning('Stored ML model could not be restored.', ['message' => $exception->getMessage()]);

            return false;
        }
    }

    protected function saveModelAndMetadata(): void
    {
        if (! $this->model) {
            throw new RuntimeException('Cannot save an empty model.');
        }

        File::ensureDirectoryExists(dirname($this->modelPath));
        $suffix = '.candidate.'.bin2hex(random_bytes(8));
        $candidateModelPath = $this->modelPath.$suffix;
        $candidateMetadataPath = $this->metadataPath.$suffix;
        $backupModelPath = $this->modelPath.'.backup.'.bin2hex(random_bytes(8));
        $backupMetadataPath = $this->metadataPath.'.backup.'.bin2hex(random_bytes(8));
        $json = json_encode($this->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            (new ModelManager)->saveToFile($this->model, $candidateModelPath);
            if (file_put_contents($candidateMetadataPath, $json, LOCK_EX) === false) {
                throw new RuntimeException('Unable to save candidate model metadata.');
            }
            $candidate = (new ModelManager)->restoreFromFile($candidateModelPath);
            if (! $candidate instanceof LeastSquares) {
                throw new RuntimeException('Candidate model validation failed.');
            }
            json_decode((string) File::get($candidateMetadataPath), true, 512, JSON_THROW_ON_ERROR);

            $hadModel = File::exists($this->modelPath);
            $hadMetadata = File::exists($this->metadataPath);
            if ($hadModel && ! @rename($this->modelPath, $backupModelPath)) {
                throw new RuntimeException('Unable to back up the active model.');
            }
            if ($hadMetadata && ! @rename($this->metadataPath, $backupMetadataPath)) {
                if ($hadModel) {
                    @rename($backupModelPath, $this->modelPath);
                }
                throw new RuntimeException('Unable to back up active model metadata.');
            }

            if (! @rename($candidateModelPath, $this->modelPath)
                || ! @rename($candidateMetadataPath, $this->metadataPath)) {
                File::delete([$this->modelPath, $this->metadataPath]);
                if ($hadModel) {
                    @rename($backupModelPath, $this->modelPath);
                }
                if ($hadMetadata) {
                    @rename($backupMetadataPath, $this->metadataPath);
                }
                throw new RuntimeException('Unable to atomically activate the candidate model and metadata.');
            }

            File::delete([$backupModelPath, $backupMetadataPath]);
        } finally {
            File::delete([$candidateModelPath, $candidateMetadataPath]);
        }
    }

    public function analyzeBudgetVariance(): Collection
    {
        try {
            $expenseTotals = $this->financeActualExpenseTotalsQuery();
            $actual = $expenseTotals === null
                ? 'COALESCE(budgets_tbl.actual_amount, 0)'
                : 'COALESCE(expense_totals.actual_cost, budgets_tbl.actual_amount, 0)';

            // Budgets is the source of truth for this comparison.  Start with
            // every budget row (rather than the latest row per project) and
            // left join its project so the comparison cannot silently omit a
            // budget that is present in the Budgets module.
            $query = DB::table('budgets_tbl')
                ->leftJoin('project_tbl', 'project_tbl.project_id', '=', 'budgets_tbl.project_id');

            if ($expenseTotals !== null) {
                $query->leftJoinSub($expenseTotals, 'expense_totals', fn ($join) => $join->on('project_tbl.project_id', '=', 'expense_totals.project_id'));
            }

            return $query
                ->select(
                    'budgets_tbl.budget_id', 'budgets_tbl.project_id',
                    'project_tbl.project_name', 'budgets_tbl.budget_amount as budget', 'project_tbl.status',
                    DB::raw("{$actual} as actual_cost"),
                    DB::raw("budgets_tbl.budget_amount - {$actual} as variance"),
                    DB::raw("CASE WHEN budgets_tbl.budget_amount > 0 THEN (budgets_tbl.budget_amount - {$actual}) / budgets_tbl.budget_amount * 100 ELSE 0 END as variance_percentage"),
                    DB::raw("CASE WHEN budgets_tbl.budget_amount - {$actual} < 0 THEN 'over' ELSE 'within' END as position")
                )
                ->orderByDesc('budgets_tbl.budget_id')
                ->get();
        } catch (Throwable $exception) {
            Log::error('Budget variance analysis failed.', ['message' => $exception->getMessage()]);

            return collect();
        }
    }

    protected function financeActualExpenseTotalsQuery(): mixed
    {
        if (! Schema::hasTable('fin_expense_tbl')
            || ! Schema::hasColumn('fin_expense_tbl', 'project_id')) {
            return null;
        }

        $amountColumn = Schema::hasColumn('fin_expense_tbl', 'amount') ? 'amount'
            : (Schema::hasColumn('fin_expense_tbl', 'actual_amount') ? 'actual_amount' : null);
        if ($amountColumn === null) {
            return null;
        }

        return DB::table('fin_expense_tbl')
            ->select('project_id')
            ->selectRaw("COALESCE(SUM(COALESCE({$amountColumn}, 0)), 0) as actual_cost")
            ->whereNotNull('project_id')
            ->groupBy('project_id');
    }
}
