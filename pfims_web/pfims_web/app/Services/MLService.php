<?php

namespace App\Services;

use App\Services\ML\BudgetOverrunClassifier;
use App\Services\ML\CostModelStore;
use App\Services\ML\PortableRbfSvr;
use App\Services\ML\RidgeRegression;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\Regression;
use Phpml\Regression\SVR;
use Phpml\SupportVectorMachine\Kernel;
use RuntimeException;
use Throwable;

class MLService
{
    private const MODEL_SCHEMA_VERSION = 10;

    private const MINIMUM_REAL_SAMPLES = 10;

    private const K_FOLD_COUNT = 5;

    private const SNAPSHOT_MINIMUM_PROJECTS_PER_STAGE = 10;

    private const FIN_FEATURE_IMPROVEMENT_THRESHOLD_PERCENT = 5.0;

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

    private const SNAPSHOT_ACTIVITY_FEATURE_NAMES = [
        'budget', 'duration_months', 'worker_count', 'completion_percentage',
        'fin_total_expense', 'fin_material_expense', 'fin_labor_expense',
        'fin_equipment_expense', 'fin_other_expense',
        'expense_frequency_30d', 'stock_out_frequency_30d',
        'expense_amount_per_day_30d', 'has_unvalued_stock_out',
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

    private const FEATURE_NAMES = [
        'budget', 'duration_months', 'worker_count', 'completion_percentage',
        'material_cost', 'labor_cost', 'fin_total_expense', 'fin_material_expense',
        'fin_labor_expense', 'fin_equipment_expense', 'fin_other_expense',
        'expense_frequency_30d', 'stock_out_frequency_30d',
        'expense_amount_per_day_30d', 'has_unvalued_stock_out',
    ];

    protected ?Regression $model = null;

    protected string $modelRecoverySource = 'active';

    protected string $modelPath;

    protected string $metadataPath;

    protected array $metadata = [];

    protected string $lastPredictionSource = 'unavailable';

    protected array $lastPredictionWarnings = [];

    protected array $lastEngineeredFeatures = [];

    protected bool $lastPredictionWasConstrained = false;

    protected array $lastForecastCalculation = [];

    protected array $lastPredictionSupport = [
        'prediction_usable' => false,
        'support_level' => 'unavailable',
        'forecast_context' => 'unknown',
        'status_reason' => 'No prediction has been produced.',
    ];

    public function __construct(?string $modelPath = null, bool $loadModel = true)
    {
        $this->modelPath = $modelPath ?: $this->defaultModelPath();
        $this->metadataPath = $this->modelPath.'.meta.json';
        if ($loadModel) {
            $this->loadOrTrainModel();
        }
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
        if ($this->restoreStoredModel()) {
            return;
        }
        $this->train();
    }

    public function evaluateCandidate(string $cohortMode = 'auto'): array
    {
        $cohort = $this->selectTrainingCohort($cohortMode);
        $records = $cohort['records'];
        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => 'read_only_candidate_evaluation',
            'evaluation_protocol_version' => 5,
            'active_model_changed' => false,
            'prediction_strategy' => $cohort['strategy'],
            'cohort_policy' => $cohortMode,
            'candidate_feature_names' => $cohort['feature_names'],
            'eligible_projects' => $records->pluck('project_id')->unique()->count(),
            'eligible_observations' => $records->count(),
            'snapshot_readiness' => $cohort['snapshot_readiness'],
            'data_sources' => $records->pluck('data_source')->unique()->values()->all(),
            'scope_note' => 'Candidate evaluation only. It does not replace the saved estimator or its performance. Completed-project temporal validation does not establish real-time forecasting accuracy.',
            'cost_label_note' => 'Eligibility and reconciliation do not turn current-price historical inventory estimates into verified original invoices. Results remain conditional on the recorded cost labels.',
            'feature_catalog' => ProjectCostFeatureBuilder::catalog(),
        ];
        if ($report['eligible_projects'] < self::MINIMUM_REAL_SAMPLES) {
            return $report + ['status' => 'insufficient_projects', 'evaluation' => null];
        }
        $split = $this->selectChronologicalSplit($records, $cohort['feature_names']);

        $comparison = $this->compareTunedRegressionModels($split['training_data'], $split['test_data'], $split['selected']['feature_names']);

        return $report + [
            'status' => 'evaluated',
            'evaluation' => $split['selected']['evaluation'],
            'split' => $split['summary'],
            'training_project_ids' => $split['training_data']->pluck('project_id')->unique()->values()->all(),
            'holdout_project_ids' => $split['test_data']->pluck('project_id')->unique()->values()->all(),
            'cross_validation_scope' => 'training_partition_only_final_holdout_excluded',
            'cross_validation' => $comparison['models']['least_squares_linear_regression']['cross_validation'],
            'budget_baseline_comparison' => $this->budgetBaselineComparison($split['test_data'], $split['selected']['evaluation']),
            'feature_selection' => $split['selected']['feature_selection'] ?? null,
            'evaluated_feature_names' => $split['selected']['feature_names'],
            'model_comparison' => $comparison,
            'overrun_classifier' => $this->evaluateOverrunClassifier($split['training_data'], $split['test_data'],
                $split['selected']['feature_names'], $comparison['models']['least_squares_linear_regression']['cross_validation']['folds']),
            'stage_baselines' => $this->stageBaselines($split['test_data']),
            'fair_comparison' => $this->fairComparison($split['training_data'], $split['test_data'], $split['selected']['feature_names'], $comparison),
        ];
    }

    /** Identical held-out observations and spend floors for every approach. */
    protected function fairComparison(Collection $training, Collection $test, array $features, ?array $comparison = null): array
    {
        $comparison ??= $this->compareTunedRegressionModels($training, $test, $features);
        $models = $comparison['models'];
        $planningTraining = $training->map(function ($row) {
            $copy = clone $row;
            if (isset($copy->snapshot_id)) {
                $copy->actual_cost += (float) ($copy->fin_total_expense ?? 0);
                unset($copy->snapshot_id);
            }

            return $copy;
        })->unique('project_id')->values();
        try {
            [$planning, $transformer] = $this->buildLeastSquaresModel($planningTraining, self::PLANNING_FEATURE_NAMES);
            $predictions = $actuals = $budgets = [];
            foreach ($test as $row) {
                $estimate = (float) $planning->predict($this->transformFeatureVector(
                    $this->rowToFeatures($row, self::PLANNING_FEATURE_NAMES),
                    $transformer['selected_feature_indexes'], $transformer['ranges']
                ));
                $spent = isset($row->snapshot_id) ? (float) ($row->fin_total_expense ?? 0) : 0;
                $predictions[] = app(ProjectCostForecastCalculator::class)->calculate($estimate, $spent, false)['final_cost'];
                $actuals[] = (float) $row->actual_cost + $spent;
                $budgets[] = (float) $row->budget;
            }
            $models['refitted_planning'] = ['status' => 'evaluated',
                'note' => 'Refitted only on the same training projects; not the saved active estimator.',
                'evaluation' => $this->calculateMetrics($predictions, $actuals, $budgets, $test)];
        } catch (Throwable $exception) {
            $models['refitted_planning'] = ['status' => 'unavailable', 'evaluation' => null, 'message' => $exception->getMessage()];
        }
        $baseline = $this->budgetBaselineComparison($test, $comparison['models']['least_squares_linear_regression']['evaluation']);
        $models['recorded_budget'] = ['status' => 'evaluated', 'evaluation' => $baseline['baseline_evaluation']];
        foreach ($this->stageBaselines($test) ?? [] as $name => $result) {
            $models[$name] = ['status' => 'evaluated'] + $result;
        }
        $ids = $test->pluck('project_id')->unique()->values()->all();
        $previousIds = collect($this->getCandidateEvaluationReports()['reports'] ?? [])
            ->flatMap(fn ($report) => $report['holdout_project_ids'] ?? [])->unique();
        $seen = collect($ids)->intersect($previousIds)->values()->all();

        return ['models' => $models, 'training_project_ids' => $training->pluck('project_id')->unique()->values()->all(),
            'holdout_project_ids' => $ids, 'evaluated_observations' => $test->count(),
            'primary_overrun_definition' => 'any_overrun',
            'comparison_policy' => 'Same held-out projects, stage observations, actual costs, budgets, and recorded-spend floors.',
            'previously_evaluated_holdout_project_ids' => $seen,
            'holdout_status' => $seen === [] ? 'not_found_in_saved_reports' : 'previously_evaluated',
            'activation_evidence' => false,
            'note' => 'Diagnostic comparison only. A new independent holdout is required after development; absence from saved reports does not prove a project was never inspected.'];
    }

    /** Independent detector evaluation; no active classifier or cost artifact is written. */
    protected function evaluateOverrunClassifier(Collection $training, Collection $test, array $features, array $folds = []): array
    {
        $report = ['algorithm' => 'project_balanced_logistic_regression', 'definition' => 'any_overrun',
            'label_rule' => 'Final cost rounded to cents strictly exceeds the budget recorded for the observation.',
            'active' => false, 'activation_evidence' => false, 'threshold' => 0.5,
            'threshold_policy' => 'Fixed before evaluation; not selected from holdout results.',
            'feature_names' => $features, 'training_project_ids' => $training->pluck('project_id')->unique()->values()->all(),
            'holdout_project_ids' => $test->pluck('project_id')->unique()->values()->all(),
            'training_weighting' => 'Each project has equal total weight before balancing the two outcome classes.',
            'decision_rule' => 'Recorded spending already above budget is a confirmed overrun; otherwise model score >= 0.5.',
            'score_note' => 'Class-balanced score, not a calibrated probability.',
            'scope_note' => 'Separate diagnostic classifier; these results do not replace the active cost model or displayed detection metrics.'];
        try {
            $tuning = $this->tuneOverrunClassifier($training, $features, $folds);
            $features = $tuning['feature_names'];
            $threshold = $tuning['threshold'];
            $report['feature_names'] = $features;
            $report['threshold'] = $threshold;
            $report['threshold_policy'] = 'Selected using training-only temporal validation; final holdout excluded.';
            $report['decision_rule'] = 'Recorded spending above budget is confirmed; otherwise model score meets the selected threshold.';
            $model = $this->fitOverrunClassifier($training, $features);
            $outcomes = $this->classifierOutcomes($model, $test, $features, $threshold);
            $metrics = app(ProjectOverrunEvaluation::class)->evaluateClasses($outcomes['predictions'], $outcomes['actuals']);
            $latest = [];
            foreach ($test->values() as $i => $row) {
                $key = (string) $row->project_id;
                if (! isset($latest[$key]) || strcmp((string) ($row->captured_at ?? ''), (string) ($test->values()[$latest[$key]]->captured_at ?? '')) > 0) {
                    $latest[$key] = $i;
                }
            }
            $metrics['latest_observation_per_project'] = app(ProjectOverrunEvaluation::class)->evaluateClasses(
                array_map(fn ($i) => $outcomes['predictions'][$i], array_values($latest)),
                array_map(fn ($i) => $outcomes['actuals'][$i], array_values($latest)));
            $cv = [];
            foreach ($folds as $fold) {
                $foldTraining = $training->whereIn('project_id', $fold['training_project_ids'])->values();
                $foldTest = $training->whereIn('project_id', $fold['test_project_ids'])->values();
                try {
                    $foldModel = $this->fitOverrunClassifier($foldTraining, $features);
                    $foldOutcomes = $this->classifierOutcomes($foldModel, $foldTest, $features, $threshold);
                    $cv[] = ['status' => 'evaluated', 'fold' => $fold['fold'],
                        'training_project_ids' => $fold['training_project_ids'], 'test_project_ids' => $fold['test_project_ids'],
                        'latest_training_completion' => $fold['latest_training_completion'], 'earliest_test_completion' => $fold['earliest_test_completion'],
                        'evaluation' => app(ProjectOverrunEvaluation::class)->evaluateClasses($foldOutcomes['predictions'], $foldOutcomes['actuals'])];
                } catch (Throwable $exception) {
                    $cv[] = ['status' => 'unavailable', 'fold' => $fold['fold'], 'message' => $exception->getMessage()];
                }
            }

            return $report + ['status' => 'evaluated', 'evaluation' => $metrics,
                'confirmed_spend_overruns' => $outcomes['confirmed_spend_overruns'],
                'candidate_model' => array_replace($model->modelData(), ['threshold' => $threshold]),
                'tuning' => $tuning,
                'cross_validation' => ['scope' => 'training_partition_only_final_holdout_excluded',
                    'method' => 'expanding_window_grouped_temporal_cross_validation', 'folds' => $cv],
                'observation_weighting' => 'Held-out observations have equal weight; use latest-observation-per-project metrics to avoid repeated-stage counting.'];
        } catch (Throwable $exception) {
            return $report + ['status' => 'unavailable', 'message' => $exception->getMessage(), 'evaluation' => null];
        }
    }

    /** Freeze features and threshold before final evaluation; each project contributes once per validation fold. */
    protected function tuneOverrunClassifier(Collection $training, array $features, array $folds): array
    {
        $candidates = ['current_features' => $features];
        if ($training->contains(fn ($row) => isset($row->snapshot_id))) {
            $expanded = array_values(array_unique([...$features, 'remaining_work_fraction', 'remaining_budget_fraction',
                'required_cost_performance_index', 'required_cost_performance_available',
                'recent_burn_remaining_budget_days', 'budget_runway_available']));
            if ($expanded !== $features) {
                $candidates['remaining_work_features'] = $expanded;
            }
        }
        $trials = [];
        foreach ($candidates as $name => $names) {
            $scores = $labels = [];
            $successfulFolds = 0;
            foreach ($folds as $fold) {
                $fit = $training->whereIn('project_id', $fold['training_project_ids'])->values();
                $validation = $training->whereIn('project_id', $fold['test_project_ids'])->sortBy('captured_at')
                    ->groupBy('project_id')->map(fn ($rows) => $rows->last())->values();
                try {
                    $model = $this->fitOverrunClassifier($fit, $names);
                    $outcomes = $this->classifierOutcomes($model, $validation, $names);
                    $scores = [...$scores, ...$outcomes['scores']];
                    $labels = [...$labels, ...$outcomes['actuals']];
                    $successfulFolds++;
                } catch (Throwable $exception) {
                    // One-class early windows cannot fit a binary detector.
                }
            }
            if ($successfulFolds < 2 || ! in_array(true, $labels, true) || ! in_array(false, $labels, true)) {
                continue;
            }
            foreach ([0.3, 0.4, 0.5, 0.6, 0.7] as $threshold) {
                $metrics = app(ProjectOverrunEvaluation::class)->evaluateClasses(
                    array_map(fn ($score) => $score >= $threshold, $scores), $labels);
                $trials[] = ['candidate' => $name, 'feature_names' => $names, 'threshold' => $threshold,
                    'successful_folds' => $successfulFolds, 'evaluation' => $metrics];
            }
        }
        usort($trials, function ($a, $b) {
            foreach (['f1_score', 'balanced_accuracy', 'recall'] as $metric) {
                $order = ($b['evaluation'][$metric] ?? 0) <=> ($a['evaluation'][$metric] ?? 0);
                if ($order !== 0) {
                    return $order;
                }
            }

            return count($a['feature_names']) <=> count($b['feature_names'])
                ?: abs($a['threshold'] - 0.5) <=> abs($b['threshold'] - 0.5);
        });

        return ['status' => $trials === [] ? 'insufficient_validation_default_retained' : 'tuned',
            'feature_names' => $trials[0]['feature_names'] ?? $features,
            'threshold' => $trials[0]['threshold'] ?? 0.5,
            'scope' => 'training_partition_only_final_holdout_excluded',
            'selection_rule' => 'Highest validation F1, then balanced accuracy, then recall; ties prefer fewer features and threshold nearest 0.5.',
            'observation_policy' => 'Latest observation per project per validation fold.',
            'validation_note' => 'Selection scores are tuning evidence, not independent test performance.',
            'trials' => $trials];
    }

    protected function fitOverrunClassifier(Collection $records, array $features): BudgetOverrunClassifier
    {
        $labels = $records->map(function ($row) {
            $finalCost = (float) $row->actual_cost + (isset($row->snapshot_id) ? (float) ($row->fin_total_expense ?? 0) : 0.0);

            return app(ProjectOverrunPolicy::class)->classify($finalCost, (float) $row->budget)['any_overrun'];
        })->values()->all();
        $model = new BudgetOverrunClassifier;
        $model->train($records->map(fn ($row) => $this->rowToFeatures($row, $features))->values()->all(),
            $labels, $records->pluck('project_id')->values()->all());

        return $model;
    }

    protected function classifierOutcomes(BudgetOverrunClassifier $model, Collection $records, array $features, float $threshold = 0.5): array
    {
        $predictions = $actuals = $scores = [];
        $confirmed = 0;
        foreach ($records as $row) {
            $spent = isset($row->snapshot_id) ? (float) ($row->fin_total_expense ?? 0) : 0.0;
            $known = app(ProjectOverrunPolicy::class)->classify($spent, (float) $row->budget)['any_overrun'] === true;
            $actuals[] = app(ProjectOverrunPolicy::class)->classify((float) $row->actual_cost + $spent, (float) $row->budget)['any_overrun'];
            $scores[] = $known ? 1.0 : $model->score($this->rowToFeatures($row, $features));
            $predictions[] = end($scores) >= $threshold;
            $confirmed += (int) $known;
        }

        return ['predictions' => $predictions, 'actuals' => $actuals, 'scores' => $scores, 'confirmed_spend_overruns' => $confirmed];
    }

    /** Persist evaluation evidence independently of the active estimator. */
    public function saveCandidateEvaluationReport(array $report): void
    {
        if (($report['mode'] ?? null) !== 'read_only_candidate_evaluation'
            || ! in_array($report['cohort_policy'] ?? null, ['auto', 'planning', 'presentation_progress'], true)) {
            throw new InvalidArgumentException('A validated candidate evaluation report is required.');
        }
        $path = $this->modelPath.'.evaluation.json';
        $existing = $this->getCandidateEvaluationReports() ?? ['schema_version' => 1, 'reports' => []];
        $existing['generated_at'] = now()->toIso8601String();
        $existing['active_model_sha256'] = File::exists($this->modelPath) ? hash_file('sha256', $this->modelPath) : null;
        $existing['reports'][$report['cohort_policy']] = $report;
        File::ensureDirectoryExists(dirname($path));
        $temporary = $path.'.candidate.'.bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporary, json_encode($existing, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false
                || ! rename($temporary, $path)) {
                throw new RuntimeException('Candidate evaluation evidence could not be saved.');
            }
        } finally {
            File::delete($temporary);
        }
    }

    public function getCandidateEvaluationReports(): ?array
    {
        try {
            $path = $this->modelPath.'.evaluation.json';
            if (! File::exists($path)) {
                return null;
            }
            $report = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            return ($report['schema_version'] ?? null) === 1 && is_array($report['reports'] ?? null) ? $report : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Training metadata retains the provenance of presentation examples. */
    public function train(?string $cohortMode = null): bool
    {
        $cohortMode ??= $this->metadata['cohort_policy'] ?? 'auto';
        File::ensureDirectoryExists(dirname($this->modelPath));
        $lockHandle = fopen($this->modelPath.'.lock', 'c');
        if ($lockHandle === false) {
            throw new RuntimeException('Unable to create the model training lock.');
        }

        try {
            if (! flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire the model training lock.');
            }
            $cohortSelection = $this->selectTrainingCohort($cohortMode);
            $trainingCohort = $cohortSelection['records'];
            $featureNames = $cohortSelection['feature_names'];
            $strategy = $cohortSelection['strategy'];
            $sampleCount = $trainingCohort->where('data_source', 'company_inspired_sample')->count();
            $realSampleCount = $trainingCohort->count() - $sampleCount;
            $usesSampleData = $sampleCount > 0;

            if ($trainingCohort->pluck('project_id')->unique()->count() < self::MINIMUM_REAL_SAMPLES) {
                if ($cohortMode === 'presentation_progress') {
                    return false;
                }
                $this->createFallbackModel(
                    $realSampleCount,
                    'At least '.self::MINIMUM_REAL_SAMPLES.' eligible completed projects are required; '
                    .$trainingCohort->pluck('project_id')->unique()->count().' are available.'
                );

                return false;
            }

            $splitSelection = $this->selectChronologicalSplit($trainingCohort, $featureNames);
            $featureNames = $splitSelection['selected']['feature_names'];
            $trainingData = $splitSelection['training_data'];
            $testData = $splitSelection['test_data'];
            $featureSelection = $this->trainingFeatureSetMetadata($trainingData, $featureNames, $strategy);
            $evaluation = $splitSelection['selected']['evaluation'];
            $comparison = $this->compareRegressionModels($trainingData, $testData, $featureNames);
            $baselineComparison = $this->budgetBaselineComparison($testData, $evaluation);

            // A snapshot candidate must beat the simple recorded-budget estimate
            // on projects it never saw before replacing the established model.
            if ($strategy === 'progress_snapshot_model'
                && (! ($baselineComparison['model_outperforms_budget_baseline'] ?? false)
                    || ! ($comparison['production_model_is_best_option'] ?? false))) {
                if ($cohortMode === 'presentation_progress') {
                    Log::info('Presentation progress candidate rejected; saved estimator retained.', ['baseline' => $baselineComparison]);

                    return false;
                }
                $cohortSelection['snapshot_readiness']['note'] = 'Snapshot candidate did not pass independent holdout comparison; planning model retained.';
                $trainingCohort = $this->getTrainingData()
                    ->unique(fn ($row) => (string) $row->project_id)
                    ->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])->values();
                $featureNames = self::PLANNING_FEATURE_NAMES;
                $strategy = 'planning_only_baseline';
                if ($trainingCohort->count() < self::MINIMUM_REAL_SAMPLES) {
                    $this->createFallbackModel($trainingCohort->count(), 'Snapshot candidate failed holdout comparison and planning data are insufficient.');

                    return false;
                }
                $sampleCount = $trainingCohort->where('data_source', 'company_inspired_sample')->count();
                $realSampleCount = $trainingCohort->count() - $sampleCount;
                $usesSampleData = $sampleCount > 0;
                $splitSelection = $this->selectChronologicalSplit($trainingCohort, $featureNames);
                $trainingData = $splitSelection['training_data'];
                $testData = $splitSelection['test_data'];
                $featureSelection = $this->trainingFeatureSetMetadata($trainingData, $featureNames, $strategy);
                $evaluation = $splitSelection['selected']['evaluation'];
                $comparison = $this->compareRegressionModels($trainingData, $testData, $featureNames);
                $baselineComparison = $this->budgetBaselineComparison($testData, $evaluation);
            }

            // Deploy on all verified records after independent chronological evaluation.
            [$candidateModel, $productionTransformer] = $this->buildLeastSquaresModel($trainingCohort, $featureNames);
            $estimatedHistoricalPurchases = Schema::hasTable('fin_expense_tbl')
                && Schema::hasColumns('fin_expense_tbl', ['entry_kind', 'remarks'])
                ? DB::table('fin_expense_tbl')->where('entry_kind', 'inventory_purchase')
                    ->where('remarks', 'like', 'Historical item-price estimate:%')->count()
                : 0;
            $candidateMetadata = [
                'schema_version' => self::MODEL_SCHEMA_VERSION,
                'trained_at' => now()->toIso8601String(),
                'model_type' => 'least_squares_linear_regression',
                'model_source' => $usesSampleData ? 'sample_trained_model' : 'real_trained_model',
                'prediction_strategy' => $strategy,
                'cohort_policy' => $cohortMode,
                'evaluation_scope_label' => $usesSampleData ? 'Presentation dataset performance' : 'Recorded project performance',
                'training_projects' => $trainingCohort->pluck('project_id')->unique()->count(),
                'evaluation_training_project_ids' => $trainingData->pluck('project_id')->unique()->values()->all(),
                'evaluation_holdout_project_ids' => $testData->pluck('project_id')->unique()->values()->all(),
                'progress_feature_selection' => $splitSelection['selected']['feature_selection'] ?? null,
                'prediction_target' => $strategy === 'progress_snapshot_model'
                    ? 'remaining_cost_then_add_recorded_spend'
                    : 'final_cost',
                'uses_synthetic_data' => $usesSampleData,
                'estimated_historical_purchase_count' => $estimatedHistoricalPurchases,
                'real_samples_available' => $realSampleCount,
                'sample_samples_available' => $sampleCount,
                'samples_trained' => $trainingCohort->count(),
                'training_samples_evaluated' => $trainingData->count(),
                'test_samples' => $testData->count(),
                'evaluation_method' => $splitSelection['selected']['method'],
                'evaluation_protocol_version' => 3,
                'evaluation' => $evaluation,
                'split_selection' => $splitSelection['summary'],
                'cross_validation' => $featureSelection['cross_validation'],
                'feature_set' => $featureSelection,
                'feature_engineering_version' => ProjectCostFeatureBuilder::catalog()['formula_version'],
                'model_comparison' => $comparison,
                'budget_baseline_comparison' => $baselineComparison,
                'stage_baselines' => $this->stageBaselines($testData),
                'snapshot_readiness' => $cohortSelection['snapshot_readiness'],
                'data_capture_policy' => $this->dataCapturePolicy(),
                'retraining_policy' => $this->retrainingPolicy(),
                'risk_business_actions' => $this->riskBusinessActions(),
                'transformer' => $productionTransformer,
                'feature_ranges' => $this->featureRanges($trainingCohort, $featureNames),
                'sample_sufficiency' => $this->sampleSufficiency($trainingCohort->count(), $usesSampleData),
                'training_criteria' => [
                    'fixed_newest_20_percent_project_holdout', 'all_snapshots_for_a_project_stay_in_one_partition',
                    $strategy === 'progress_snapshot_model'
                        ? ($cohortMode === 'presentation_progress' ? 'internally_traceable_presentation_stage_scenarios' : 'genuine_timestamped_progress_snapshots')
                        : 'planning_inputs_only',
                    'status_is_completed', 'completion_is_100_percent',
                    'actual_end_date_is_present', 'start_date_is_not_after_actual_end_date',
                    'persisted_budget_and_final_actual_amount_are_positive',
                    'persisted_budget_history_is_not_claimed_as_immutable_initial_budget',
                    $usesSampleData ? 'company_inspired_sample_not_verified_company_performance' : 'operational_company_records',
                    ...($estimatedHistoricalPurchases > 0 ? ['historical_purchase_amounts_estimated_from_current_item_prices'] : []),
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
            if ($cohortMode === 'presentation_progress') {
                $this->restoreStoredModel();

                return false;
            }
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
            $allocations = $this->allocationAggregateQuery(false);
            $featureAllocations = $this->allocationAggregateQuery(true);
            $allocated = $allocations === null ? '0' : 'COALESCE(fin_allocations.material_cost, 0)';
            $featureAllocated = $featureAllocations === null ? '0' : 'COALESCE(fin_feature_allocations.material_cost, 0)';
            $direct = $actualExpenses === null ? '0' : 'COALESCE(fin_actuals.fin_total_expense, 0)';
            $actualCost = $allocations === null
                ? ($actualExpenses === null ? 'COALESCE(budgets_tbl.actual_amount, 0)' : 'COALESCE(NULLIF(fin_actuals.fin_total_expense, 0), budgets_tbl.actual_amount, 0)')
                : "{$direct} + {$allocated}";
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
            if ($allocations !== null) {
                $query->leftJoinSub($allocations, 'fin_allocations', fn ($join) => $join->on('project_tbl.project_id', '=', 'fin_allocations.project_id'));
                $query->whereRaw('COALESCE(fin_allocations.unvalued_count, 0) = 0');
            }
            if ($featureAllocations !== null) {
                $query->leftJoinSub($featureAllocations, 'fin_feature_allocations', fn ($join) => $join->on('project_tbl.project_id', '=', 'fin_feature_allocations.project_id'));
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
                    'budgets_tbl.budget_amount as budget', 'budgets_tbl.actual_amount as budget_actual', DB::raw("{$actualCost} as actual_cost"),
                    'budgets_tbl.budget_id',
                    DB::raw(($finExpenses === null ? '0' : 'COALESCE(fin_expenses.fin_material_expense, 0)')." + {$featureAllocated} as material_cost"),
                    DB::raw($finExpenses === null ? '0 as labor_cost' : 'COALESCE(fin_expenses.fin_labor_expense, 0) as labor_cost'),
                    DB::raw(($finExpenses === null ? '0' : 'COALESCE(fin_expenses.fin_total_expense, 0)')." + {$featureAllocated} as fin_total_expense"),
                    DB::raw(($finExpenses === null ? '0' : 'COALESCE(fin_expenses.fin_material_expense, 0)')." + {$featureAllocated} as fin_material_expense"),
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
                        $valid = Carbon::parse($row->completed_at)->startOfDay()
                            ->greaterThanOrEqualTo(Carbon::parse($row->start_date)->startOfDay())
                            && is_numeric($row->worker_count) && (float) $row->worker_count >= 1;
                        if (! $valid || ! Schema::hasTable('inventory_cost_allocation_tbl')) {
                            return $valid;
                        }
                        $cost = app(ProjectCostLedger::class)->forProject((int) $row->project_id);

                        return $cost['unvalued_count'] === 0
                            && abs((float) $row->actual_cost - $cost['total']) <= 0.01
                            && abs((float) $row->budget_actual - $cost['total']) <= 0.01
                            && app(ProjectCostDataQualityService::class)->inspect((int) $row->project_id)['eligible'];
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
                    $row->budget_context = app(BudgetHistoryService::class)->context((int) $row->project_id, (float) $row->budget, (int) $row->budget_id);

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
    protected function selectTrainingCohort(string $cohortMode = 'auto'): array
    {
        if (! in_array($cohortMode, ['auto', 'planning', 'presentation_progress'], true)) {
            throw new InvalidArgumentException('Unknown training cohort policy.');
        }
        $snapshots = $this->getSnapshotTrainingData();
        $operationalSnapshots = $snapshots->where('data_source', 'operational')
            ->reject(fn ($row) => ($row->capture_reason ?? null) === 'presentation_scenario')->values();
        $selectedSnapshots = $cohortMode === 'presentation_progress'
            ? $snapshots->where('data_source', 'company_inspired_sample')
                ->where('capture_reason', 'presentation_scenario')->where('capture_schema_version', 3)->values()
            : $operationalSnapshots;
        $stageProjectCounts = [];
        foreach (['early', 'middle', 'late'] as $stage) {
            $stageProjectCounts[$stage] = $selectedSnapshots
                ->filter(fn ($row) => $this->progressStage((float) $row->completion_percentage) === $stage)
                ->pluck('project_id')->unique()->count();
        }
        $snapshotProjects = $selectedSnapshots->pluck('project_id')->unique()->count();
        $ready = $snapshotProjects >= self::MINIMUM_REAL_SAMPLES
            && collect($stageProjectCounts)->every(fn ($count) => $count >= self::SNAPSHOT_MINIMUM_PROJECTS_PER_STAGE);
        $readiness = [
            'eligible' => $ready,
            'cohort_policy' => $cohortMode,
            'operational_finalized_projects' => $operationalSnapshots->pluck('project_id')->unique()->count(),
            'company_performance_validated' => false,
            'finalized_snapshot_rows' => $snapshots->count(),
            'finalized_projects' => $snapshotProjects,
            'minimum_projects_per_stage' => self::SNAPSHOT_MINIMUM_PROJECTS_PER_STAGE,
            'projects_by_stage' => $stageProjectCounts,
            'activation_rule' => 'At least 10 finalized projects represented in each early, middle, and late progress stage.',
            'note' => $cohortMode === 'presentation_progress'
                ? 'Explicit presentation-stage cohort; evaluation demonstrates the model and does not validate historical company forecasting.'
                : 'Only snapshots captured by the application at the time of a real data change are eligible; no history is reconstructed.',
        ];

        if (($ready && $cohortMode !== 'planning') || $cohortMode === 'presentation_progress') {
            $hasActivity = Schema::hasColumn('ml_project_cost_snapshots', 'direct_expense_count_30d');

            return [
                'records' => $ready ? $selectedSnapshots : collect(),
                'feature_names' => $hasActivity
                    ? [...self::SNAPSHOT_ACTIVITY_FEATURE_NAMES, ...ProjectCostFeatureBuilder::FEATURE_NAMES]
                    : self::SNAPSHOT_FEATURE_NAMES,
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

        $hasActivity = Schema::hasColumn('ml_project_cost_snapshots', 'direct_expense_count_30d');
        $query = DB::table('ml_project_cost_snapshots as snapshot')
            ->join('project_tbl as project', 'project.project_id', '=', 'snapshot.project_id')
            ->select(
                'snapshot.snapshot_id', 'snapshot.project_id', 'snapshot.captured_at',
                'project.actual_end_date as completed_at',
                DB::raw(Schema::hasColumn('ml_project_cost_snapshots', 'planned_start_date')
                    ? 'COALESCE(snapshot.planned_start_date, project.start_date) as planned_start_date'
                    : 'project.start_date as planned_start_date'), 'snapshot.planned_budget as budget',
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
                'project.project_name', 'snapshot.final_actual_cost as reconciled_final_cost'
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
            ->orderBy('project.actual_end_date')->orderBy('snapshot.project_id')->orderBy('snapshot.captured_at');
        if ($hasActivity) {
            $query->addSelect('snapshot.direct_expense_count_30d', 'snapshot.direct_expense_amount_30d',
                'snapshot.stock_out_count_30d', 'snapshot.stock_out_quantity_30d',
                'snapshot.unvalued_stock_out_count')
                ->whereNotNull('snapshot.direct_expense_count_30d')
                ->whereNotNull('snapshot.stock_out_count_30d');
        }
        if (Schema::hasColumn('ml_project_cost_snapshots', 'cost_coverage_complete')) {
            $query->addSelect('snapshot.planned_duration_days', 'snapshot.elapsed_days', 'snapshot.cost_coverage_complete');
            // Legacy rows keep their existing validation path; newly captured incomplete costs cannot train.
            $query->where(function ($query) {
                $query->whereNull('snapshot.capture_schema_version')->orWhere('snapshot.cost_coverage_complete', true);
            });
        }
        foreach (['direct_expense_count_7d', 'stock_out_count_7d', 'valued_stock_out_cost',
            'direct_expense_amount_7d', 'valued_stock_out_cost_7d', 'valued_stock_out_cost_30d',
            'capture_reason', 'capture_schema_version'] as $column) {
            if (Schema::hasColumn('ml_project_cost_snapshots', $column)) {
                $query->addSelect('snapshot.'.$column);
            }
        }

        $qualityByProject = [];

        return $query->get()
            ->map(function ($row) {
                foreach (app(ProjectCostFeatureBuilder::class)->build((array) $row)['values'] as $name => $value) {
                    $row->{$name} = $value;
                }
                $row->actual_cost = max(0.0, (float) $row->actual_cost - (float) $row->fin_total_expense);
                $row->project_type = $this->normalizeProjectType(null, $row->project_name ?? null);
                $row->project_type_source = 'normalized_project_name';
                $days = max(1, min(30, (int) Carbon::parse($row->planned_start_date)->startOfDay()
                    ->diffInDays(Carbon::parse($row->captured_at)->startOfDay()) + 1));
                $row->expense_frequency_30d = (float) ($row->direct_expense_count_30d ?? 0) / $days * 7;
                $row->stock_out_frequency_30d = (float) ($row->stock_out_count_30d ?? 0) / $days * 7;
                $row->expense_amount_per_day_30d = (float) ($row->direct_expense_amount_30d ?? 0) / $days;
                $row->has_unvalued_stock_out = (int) (($row->unvalued_stock_out_count ?? 0) > 0);

                return $row;
            })
            ->filter(function ($row) use (&$ledgerByProject, &$qualityByProject) {
                if (! Schema::hasTable('inventory_cost_allocation_tbl')) {
                    return true;
                }
                $ledgerByProject ??= [];
                $cost = $ledgerByProject[$row->project_id] ??= app(ProjectCostLedger::class)->forProject((int) $row->project_id);

                $budgetActual = DB::table('budgets_tbl')->where('project_id', $row->project_id)
                    ->orderByDesc('budget_id')->value('actual_amount');
                $qualityByProject[$row->project_id] ??= app(ProjectCostDataQualityService::class)->inspect((int) $row->project_id)['eligible'];

                return $cost['unvalued_count'] === 0
                    && abs((float) $row->reconciled_final_cost - $cost['total']) <= 0.01
                    && $budgetActual !== null && abs((float) $budgetActual - $cost['total']) <= 0.01
                    && $qualityByProject[$row->project_id];
            })->values();
    }

    public function getSnapshotReadiness(): array
    {
        $readiness = $this->selectTrainingCohort()['snapshot_readiness'];
        $readiness['projects_still_needed_by_stage'] = collect($readiness['projects_by_stage'])
            ->map(fn ($count) => max(0, $readiness['minimum_projects_per_stage'] - $count))->all();
        $readiness['collected_snapshot_rows'] = Schema::hasTable('ml_project_cost_snapshots')
            ? DB::table('ml_project_cost_snapshots')->count() : 0;
        if (Schema::hasColumn('ml_project_cost_snapshots', 'cost_coverage_complete')) {
            $snapshots = DB::table('ml_project_cost_snapshots');
            $readiness['capture_quality_rows'] = [
                'historical_quality_unknown' => (clone $snapshots)->whereNull('capture_schema_version')->count(),
                'complete_cost_coverage' => (clone $snapshots)->where('cost_coverage_complete', true)->count(),
                'incomplete_cost_coverage' => (clone $snapshots)->where('cost_coverage_complete', false)->count(),
            ];
        }
        $readiness['scope'] = 'Current snapshot readiness, separate from the saved model evaluation.';

        return $readiness;
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
        if (Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id')) {
            $query->whereNull('fin_expense.inventory_transaction_id');
        }

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

    protected function allocationAggregateQuery(bool $beforeProjectCompletion = false): mixed
    {
        if (! Schema::hasTable('inventory_cost_allocation_tbl') || ! Schema::hasTable('inventory_transaction_tbl')) {
            return null;
        }

        $query = DB::table('inventory_cost_allocation_tbl as allocation')
            ->join('inventory_transaction_tbl as withdrawal', 'withdrawal.inventory_transaction_id', '=', 'allocation.out_transaction_id')
            ->join('project_tbl as allocation_project', 'allocation_project.project_id', '=', 'allocation.project_id')
            ->select('allocation.project_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN allocation.valuation_status = 'valued' THEN allocation.allocated_amount ELSE 0 END), 0) as material_cost")
            ->selectRaw("SUM(CASE WHEN allocation.valuation_status = 'unvalued' THEN 1 ELSE 0 END) as unvalued_count")
            ->selectRaw('MAX(withdrawal.transaction_date) as allocation_as_of_date');
        if ($beforeProjectCompletion) {
            $query->whereColumn('withdrawal.transaction_date', '<', 'allocation_project.actual_end_date');
        }

        return $query->groupBy('allocation.project_id');
    }

    /** Current, incomplete projects whose model inputs can be derived from recorded system data. */
    public function getPredictionProjects(): Collection
    {
        $latestBudgetIds = DB::table('budgets_tbl')
            ->select('project_id', DB::raw('MAX(budget_id) as budget_id'))
            ->groupBy('project_id');
        $finance = $this->finExpenseAggregateQuery(false);
        $allocations = $this->allocationAggregateQuery(false);
        if ($finance !== null) {
            $finance->whereDate('fin_expense.expense_date', '<=', now()->toDateString());
        }

        $query = DB::table('project_tbl')
            ->joinSub($latestBudgetIds, 'latest_budget', fn ($join) => $join->on('project_tbl.project_id', '=', 'latest_budget.project_id'))
            ->join('budgets_tbl', 'latest_budget.budget_id', '=', 'budgets_tbl.budget_id');
        if ($finance !== null) {
            $query->leftJoinSub($finance, 'prediction_finance', fn ($join) => $join->on('project_tbl.project_id', '=', 'prediction_finance.project_id'));
        }
        if ($allocations !== null) {
            $query->leftJoinSub($allocations, 'prediction_allocations', fn ($join) => $join->on('project_tbl.project_id', '=', 'prediction_allocations.project_id'));
        }
        $allocated = $allocations === null ? '0' : 'COALESCE(prediction_allocations.material_cost, 0)';

        return $query->select(
            'project_tbl.project_id', 'project_tbl.project_name', 'project_tbl.status',
            'project_tbl.start_date', 'project_tbl.estimated_end_date',
            'project_tbl.worker_count', 'project_tbl.completion_percentage',
            'budgets_tbl.budget_amount as budget',
            'budgets_tbl.budget_id',
            DB::raw(($finance === null ? '0' : 'COALESCE(prediction_finance.fin_total_expense, 0)')." + {$allocated} as fin_total_expense"),
            DB::raw(($finance === null ? '0' : 'COALESCE(prediction_finance.fin_material_expense, 0)')." + {$allocated} as fin_material_expense"),
            DB::raw($finance === null ? '0 as fin_labor_expense' : 'COALESCE(prediction_finance.fin_labor_expense, 0) as fin_labor_expense'),
            DB::raw($finance === null ? '0 as fin_equipment_expense' : 'COALESCE(prediction_finance.fin_equipment_expense, 0) as fin_equipment_expense'),
            DB::raw($finance === null ? '0 as fin_other_expense' : 'COALESCE(prediction_finance.fin_other_expense, 0) as fin_other_expense'),
            DB::raw($finance === null && $allocations === null ? 'NULL as finance_as_of_date'
                : ($finance === null ? 'prediction_allocations.allocation_as_of_date as finance_as_of_date'
                    : ($allocations === null ? 'prediction_finance.finance_as_of_date'
                        : 'CASE WHEN prediction_finance.finance_as_of_date IS NULL THEN prediction_allocations.allocation_as_of_date WHEN prediction_allocations.allocation_as_of_date IS NULL THEN prediction_finance.finance_as_of_date WHEN prediction_finance.finance_as_of_date >= prediction_allocations.allocation_as_of_date THEN prediction_finance.finance_as_of_date ELSE prediction_allocations.allocation_as_of_date END as finance_as_of_date')))
        )
            ->whereNotNull('project_tbl.start_date')
            ->whereDate('project_tbl.start_date', '<=', today())
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
                $activity = app(ProjectCostSnapshotService::class)->forecastInputs((int) $project->project_id,
                    Carbon::parse($project->start_date)->startOfDay(), Carbon::parse($project->estimated_end_date)->startOfDay());
                foreach (['fin_total_expense', 'fin_material_expense', 'fin_labor_expense', 'fin_equipment_expense', 'fin_other_expense', 'finance_as_of_date'] as $name) {
                    $project->{$name} = $activity[$name];
                }
                $days = max(1, min(30, (int) Carbon::parse($project->start_date)->startOfDay()->diffInDays(now()->startOfDay()) + 1));

                return [
                    'project_id' => (int) $project->project_id,
                    'budget_context' => app(BudgetHistoryService::class)->context((int) $project->project_id, (float) $project->budget, (int) $project->budget_id),
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
                    'expense_frequency_30d' => $activity['direct_expense_count_30d'] / $days * 7,
                    'stock_out_frequency_30d' => $activity['stock_out_count_30d'] / $days * 7,
                    'expense_amount_per_day_30d' => $activity['direct_expense_amount_30d'] / $days,
                    'has_unvalued_stock_out' => $activity['unvalued_stock_out_count'] > 0 ? 1 : 0,
                    'forecast_feature_context' => $activity,
                    'feature_indicators' => app(ProjectCostFeatureBuilder::class)->build($activity + [
                        'budget' => (float) $project->budget, 'completion_percentage' => (float) $project->completion_percentage,
                    ])['indicators'],
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
                ? 'progress_features_selected_with_training_only_temporal_validation'
                : 'planning_only_features_selected_until_snapshot_stage_coverage_is_sufficient',
            'cross_validation' => $crossValidation,
            'cross_validation_scope' => 'training_partition_only_final_holdout_excluded',
            'leakage_controls' => [
                'Operational captures and explicitly requested presentation scenarios retain their source and capture policy.',
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
        $featureSelection = null;
        if ($trainingData->contains(fn ($row) => isset($row->snapshot_id))) {
            $featureSelection = $this->selectProgressFeatures($trainingData, $featureNames);
            $featureNames = $featureSelection['selected_feature_names'];
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
            'feature_selection' => $featureSelection,
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

    protected function kFoldCrossValidation(Collection $records, array $featureNames, string $algorithm = 'least_squares_linear_regression', array $parameters = []): array
    {
        $projectIds = $records->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])
            ->pluck('project_id')->map(fn ($id) => (string) $id)->unique()->values();
        $warmupCount = max(2, (int) ceil($projectIds->count() * 0.4));
        $foldCount = min(self::K_FOLD_COUNT, max(0, $projectIds->count() - $warmupCount));
        $folds = [];
        for ($fold = 0; $fold < $foldCount; $fold++) {
            $remaining = $projectIds->count() - $warmupCount;
            $offset = $warmupCount + (int) floor($fold * $remaining / $foldCount);
            $end = $warmupCount + (int) floor(($fold + 1) * $remaining / $foldCount);
            $testIds = $projectIds->slice($offset, $end - $offset)->flip()->all();
            $trainIds = $projectIds->slice(0, $offset)->flip()->all();
            $testData = $records->filter(fn ($row) => isset($testIds[(string) $row->project_id]))->values();
            $trainingData = $records->filter(fn ($row) => isset($trainIds[(string) $row->project_id]))->values();
            // Projects completed on the test boundary are not earlier training evidence.
            $cutoff = $testData->min('completed_at');
            $trainingData = $trainingData->groupBy('project_id')
                ->filter(fn (Collection $rows) => strcmp((string) $rows->max('completed_at'), (string) $cutoff) < 0)
                ->flatten(1)->values();
            if ($trainingData->count() < 2 || $testData->isEmpty()) {
                continue;
            }

            [$model, $transformer] = $this->buildComparisonModel($algorithm, $parameters, $trainingData, $featureNames);
            $evaluation = $this->evaluateModel($model, $transformer, $testData);
            $folds[] = [
                'fold' => $fold + 1,
                'training_samples' => $trainingData->count(),
                'test_samples' => $testData->count(),
                'training_project_ids' => $trainingData->pluck('project_id')->unique()->values()->all(),
                'test_project_ids' => $testData->pluck('project_id')->unique()->values()->all(),
                'latest_training_completion' => $trainingData->max('completed_at'),
                'earliest_test_completion' => $cutoff,
                'evaluation' => $evaluation,
            ];
        }

        $maes = array_values(array_filter(array_map(fn ($fold) => $fold['evaluation']['mean_absolute_error'] ?? null, $folds), 'is_numeric'));
        $mapes = array_values(array_filter(array_map(fn ($fold) => $fold['evaluation']['mean_absolute_percentage_error'] ?? null, $folds), 'is_numeric'));
        $f1s = array_values(array_filter(array_map(fn ($fold) => $fold['evaluation']['f1_score'] ?? null, $folds), 'is_numeric'));

        return [
            'method' => 'expanding_window_grouped_temporal_cross_validation',
            'algorithm' => $algorithm,
            'parameters' => $parameters,
            'folds_requested' => self::K_FOLD_COUNT,
            'folds_run' => count($folds),
            'folding' => 'earlier_completed_projects_train_later_completed_projects_test',
            'warmup_projects' => min($warmupCount, $projectIds->count()),
            'evaluation_scope' => 'Retrospective completed-project validation, not a simulation of labels available at each historical project start or snapshot.',
            'feature_names' => array_values($featureNames),
            'average_mean_absolute_error' => $maes === [] ? null : round(array_sum($maes) / count($maes), 2),
            'average_mean_absolute_percentage_error' => $mapes === [] ? null : round(array_sum($mapes) / count($mapes), 4),
            'average_f1_score' => $f1s === [] ? null : round(array_sum($f1s) / count($f1s), 2),
            'f1_averaging' => 'Mean across folds containing actual material overruns; not an overall detection score.',
            'folds_with_actual_material_overruns' => count($f1s),
            'folds' => $folds,
        ];
    }

    /** Choose complexity before inspecting the reserved holdout. */
    protected function selectProgressFeatures(Collection $trainingData, array $fullFeatures): array
    {
        $compact = array_values(array_intersect($fullFeatures, [
            'budget', 'duration_months', 'completion_percentage', 'fin_total_expense',
            'progress_eac_to_budget', 'budget_used_fraction', 'cost_performance_index',
            'material_cost_share', 'labor_cost_share', 'equipment_cost_share',
            'cost_burn_rate_30d', 'burn_rate_acceleration', 'inventory_cost_burn_rate_30d',
            'expense_frequency_7d', 'stock_out_frequency_7d', 'elapsed_time_fraction',
            'schedule_progress_gap', 'time_eac_to_budget',
        ]));
        $options = [];
        foreach (['compact_progress' => $compact, 'full_engineered_progress' => $fullFeatures] as $name => $features) {
            try {
                $cv = $this->kFoldCrossValidation($trainingData, $features);
                $options[$name] = ['status' => 'evaluated', 'feature_names' => $features, 'cross_validation' => $cv];
            } catch (Throwable $exception) {
                $options[$name] = ['status' => 'unavailable', 'feature_names' => $features, 'reason' => $exception->getMessage()];
            }
        }
        $compactMape = $options['compact_progress']['cross_validation']['average_mean_absolute_percentage_error'] ?? null;
        $fullMape = $options['full_engineered_progress']['cross_validation']['average_mean_absolute_percentage_error'] ?? null;
        $selected = is_numeric($fullMape) && (! is_numeric($compactMape) || $fullMape < $compactMape * 0.95)
            ? 'full_engineered_progress' : 'compact_progress';
        if (! is_numeric($options[$selected]['cross_validation']['average_mean_absolute_percentage_error'] ?? null)) {
            throw new RuntimeException('No progress feature candidate passed training-only validation.');
        }

        return ['selected_candidate' => $selected, 'selected_feature_names' => $options[$selected]['feature_names'],
            'scope' => 'training_partition_only_final_holdout_excluded',
            'selection_rule' => 'Prefer compact features unless the full catalog reduces training-only temporal CV MAPE by more than 5%.',
            'options' => $options];
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

    /** Diagnostic comparison only; the existing training/activation path stays unchanged until Step 5. */
    protected function compareTunedRegressionModels(Collection $trainingData, Collection $testData, array $featureNames): array
    {
        [$linearModel, $linearTransformer] = $this->buildLeastSquaresModel($trainingData, $featureNames);
        $linearEvaluation = $this->evaluateModel($linearModel, $linearTransformer, $testData);

        $comparison = [
            'production_model' => 'least_squares_linear_regression',
            'comparison_model' => 'support_vector_regression_rbf',
            'protocol_version' => 4,
            'selection_policy' => 'Tune ridge and SVR using training-only grouped temporal validation. Score the selected settings once on the same final holdout; do not activate any model.',
            'primary_tuning_metric' => 'mean_absolute_percentage_error',
            'tie_break_metric' => 'mean_absolute_error',
            'active_model_changed' => false,
            'models' => [
                'least_squares_linear_regression' => [
                    'status' => 'evaluated',
                    'is_production' => true,
                    'evaluation' => $linearEvaluation,
                    'persistence' => 'serialized_as_the_only_production_model',
                ],
            ],
        ];

        $linearCv = $this->kFoldCrossValidation($trainingData, $featureNames);
        $comparison['models']['least_squares_linear_regression']['cross_validation'] = $linearCv;
        $grids = ['ridge_linear_regression' => array_map(fn ($alpha) => ['alpha' => $alpha], [0.01, 0.1, 1.0, 10.0]),
            'support_vector_regression_rbf' => []];
        foreach ([1.0, 10.0] as $cost) {
            foreach ([0.1, 1.0] as $gamma) {
                foreach ([0.01, 0.1] as $epsilon) {
                    $grids['support_vector_regression_rbf'][] = compact('cost', 'gamma', 'epsilon');
                }
            }
        }
        foreach ($grids as $algorithm => $grid) {
            $trials = [];
            $selected = null;
            foreach ($grid as $parameters) {
                try {
                    $cv = $this->kFoldCrossValidation($trainingData, $featureNames, $algorithm, $parameters);
                    $mape = $cv['average_mean_absolute_percentage_error'];
                    $mae = $cv['average_mean_absolute_error'];
                    if ($cv['folds_run'] === 0 || ! is_numeric($mape) || ! is_numeric($mae)) {
                        throw new RuntimeException('No usable training-only temporal folds.');
                    }
                    $trials[] = ['status' => 'evaluated', 'parameters' => $parameters,
                        'training_cv_mape' => $mape, 'training_cv_mae' => $mae, 'folds_run' => $cv['folds_run']];
                    if ($selected === null || [$mape, $mae] < [$selected['mape'], $selected['mae']]) {
                        $selected = ['parameters' => $parameters, 'mape' => $mape, 'mae' => $mae, 'cv' => $cv];
                    }
                } catch (Throwable $exception) {
                    $trials[] = ['status' => 'unavailable', 'parameters' => $parameters, 'message' => $exception->getMessage()];
                }
            }
            try {
                if ($selected === null) {
                    throw new RuntimeException('No settings passed training-only validation.');
                }
                [$model, $transformer] = $this->buildComparisonModel($algorithm, $selected['parameters'], $trainingData, $featureNames);
                $comparison['models'][$algorithm] = ['status' => 'evaluated', 'is_production' => false,
                    'evaluation' => $this->evaluateModel($model, $transformer, $testData),
                    'selected_parameters' => $selected['parameters'], 'cross_validation' => $selected['cv'],
                    'transformer' => $transformer,
                    'tuning' => ['scope' => 'training_partition_only_final_holdout_excluded', 'trials' => $trials],
                    'persistence' => 'evaluated_in_memory_only_not_serialized_or_deployed'];
            } catch (Throwable $exception) {
                $comparison['models'][$algorithm] = ['status' => 'unavailable', 'is_production' => false,
                    'evaluation' => null, 'message' => $exception->getMessage(),
                    'tuning' => ['scope' => 'training_partition_only_final_holdout_excluded', 'trials' => $trials],
                    'persistence' => 'not_serialized_or_deployed'];
            }
        }
        $linearMae = $comparison['models']['least_squares_linear_regression']['evaluation']['mean_absolute_error'] ?? null;
        $svrMae = $comparison['models']['support_vector_regression_rbf']['evaluation']['mean_absolute_error'] ?? null;
        $svrOutperformed = is_numeric($linearMae) && is_numeric($svrMae) && (float) $svrMae < (float) $linearMae;
        $ranked = collect($comparison['models'])->filter(fn ($model) => $model['status'] === 'evaluated')
            ->sortBy(fn ($model) => $model['evaluation']['mean_absolute_error']);
        $comparison['lowest_holdout_mae_model'] = $ranked->keys()->first();
        $cvRanked = collect($comparison['models'])->filter(fn ($model) => is_numeric($model['cross_validation']['average_mean_absolute_percentage_error'] ?? null))
            ->sortBy(fn ($model) => $model['cross_validation']['average_mean_absolute_percentage_error']);
        $comparison['training_cv_recommended_model'] = $cvRanked->keys()->first();
        $comparison['recommendation_is_activation'] = false;
        $comparison['production_model_is_best_option'] = $comparison['lowest_holdout_mae_model'] === 'least_squares_linear_regression';
        $comparison['comparison_model_outperformed_linear'] = $svrOutperformed;
        $comparison['comparison_result'] = $comparison['production_model_is_best_option']
            ? 'linear_regression_was_equal_or_better_by_holdout_mae_or_comparison_unavailable'
            : 'comparison_model_had_lower_holdout_mae_but_was_not_deployed';

        return $comparison;
    }

    protected function buildComparisonModel(string $algorithm, array $parameters, Collection $records, array $features): array
    {
        return match ($algorithm) {
            'least_squares_linear_regression' => $this->buildLeastSquaresModel($records, $features),
            'ridge_linear_regression' => $this->buildRegressionModel(new RidgeRegression($parameters['alpha']), $records, $features),
            'support_vector_regression_rbf' => $this->buildSvrModel($records, $features, $parameters),
            default => throw new InvalidArgumentException('Unknown comparison algorithm.'),
        };
    }

    /** Build one portable candidate; this helper never saves or activates it. */
    protected function buildServableRegressionModel(string $algorithm, array $parameters, Collection $records, array $features): array
    {
        [$model, $transformer] = $this->buildComparisonModel($algorithm, $parameters, $records, $features);
        if ($model instanceof SVR) {
            $model = PortableRbfSvr::fromLibsvm($model->getModel(), count($transformer['selected_feature_indexes']));
        }

        return [$model, $transformer];
    }

    protected function budgetBaselineComparison(Collection $testData, array $modelEvaluation): array
    {
        $isRemainingCostTarget = $testData->contains(fn ($row) => isset($row->snapshot_id));
        $actuals = $testData->map(fn ($row) => (float) $row->actual_cost
            + ($isRemainingCostTarget ? (float) ($row->fin_total_expense ?? 0) : 0.0))->values()->all();
        $budgets = $testData->map(fn ($row) => (float) $row->budget)->values()->all();
        $baselinePredictions = $testData->map(fn ($row) => max((float) $row->budget,
            $isRemainingCostTarget ? (float) ($row->fin_total_expense ?? 0) : 0))->values()->all();
        $baseline = $this->calculateMetrics($baselinePredictions, $actuals, $budgets, $testData);
        $modelMape = $modelEvaluation['mean_absolute_percentage_error'] ?? null;
        $baselineMape = $baseline['mean_absolute_percentage_error'] ?? null;
        $improvementPoints = is_numeric($modelMape) && is_numeric($baselineMape)
            ? (float) $baselineMape - (float) $modelMape
            : null;
        $outperforms = is_numeric($modelMape) && is_numeric($baselineMape)
            && $improvementPoints >= self::MINIMUM_BASELINE_MAPE_IMPROVEMENT_POINTS;

        return [
            'baseline' => 'recorded_budget_as_final_cost_estimate',
            'forecast_policy' => 'recorded_budget_with_recorded_spend_floor',
            'untouched_holdout' => true,
            'baseline_evaluation' => $baseline,
            'model_evaluation' => $modelEvaluation,
            'minimum_mape_improvement_points' => self::MINIMUM_BASELINE_MAPE_IMPROVEMENT_POINTS,
            'mape_improvement_points' => $improvementPoints === null ? null : round($improvementPoints, 4),
            'model_outperforms_budget_baseline' => $outperforms,
            'serving_support' => $outperforms ? 'supported_by_holdout_comparison' : 'insufficient_evidence_over_budget_baseline',
        ];
    }

    protected function stageBaselines(Collection $rows): ?array
    {
        if (! $rows->contains(fn ($row) => isset($row->snapshot_id))) {
            return null;
        }
        $result = [];
        $actuals = $rows->map(fn ($row) => (float) $row->actual_cost + (float) ($row->fin_total_expense ?? 0))->all();
        $budgets = $rows->pluck('budget')->map(fn ($value) => (float) $value)->all();
        foreach (['progress_extrapolation' => 'progress_eac_to_budget', 'recent_cost_burn' => 'time_eac_to_budget'] as $name => $feature) {
            $available = $rows->filter(fn ($row) => (float) ($row->{$feature} ?? 0) > 0)->count();
            $predictions = $rows->map(fn ($row) => max((float) ($row->fin_total_expense ?? 0),
                ((float) ($row->{$feature} ?? 0) > 0 ? (float) $row->{$feature} : 1) * (float) $row->budget))->all();
            $result[$name] = ['available_observations' => $available,
                'missing_input_policy' => 'Use recorded budget with the recorded-spend floor when the formula is unavailable.',
                'evaluation' => $this->calculateMetrics($predictions, $actuals, $budgets, $rows)];
        }

        return $result;
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
            'recommendation' => 'Keep planned schedules, completion, direct expenses, and valued inventory withdrawals current. PFIMS records genuine append-only progress snapshots and never reconstructs historical progress.',
            'finance_fields_considered' => [
                'fin_expense_tbl.amount grouped by fin_expense_category_tbl category_code/category_name inference.',
                'Storage purchases are excluded from project cost until FIFO stock-out allocation assigns their value to a project; unvalued withdrawals cannot finalize a project-cost label.',
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

    protected function buildSvrModel(Collection $records, array $featureNames, array $parameters = []): array
    {
        return $this->buildRegressionModel(new SVR(Kernel::RBF, epsilon: $parameters['epsilon'] ?? 0.1,
            cost: $parameters['cost'] ?? 1.0, gamma: $parameters['gamma'] ?? null), $records, $featureNames, normalizeTargets: $parameters !== []);
    }

    protected function buildRegressionModel(Regression $model, Collection $records, array $featureNames, bool $normalizeTargets = false): array
    {
        if ($records->count() < 2) {
            throw new RuntimeException('At least two records are required to build a regression model.');
        }
        $rawSamples = $records->map(fn ($row) => $this->rowToFeatures($row, $featureNames))->values()->all();
        $remainingFraction = $records->every(fn ($row) => isset($row->snapshot_id) && (float) $row->budget > 0);
        $labels = $records->map(fn ($row) => (float) $row->actual_cost / ($remainingFraction ? (float) $row->budget : 1))->values()->all();
        $targetNormalization = null;
        if ($normalizeTargets) {
            $mean = array_sum($labels) / count($labels);
            $variance = array_sum(array_map(fn ($value) => ($value - $mean) ** 2, $labels)) / count($labels);
            $scale = max(sqrt($variance), 0.000000001);
            $labels = array_map(fn ($value) => ($value - $mean) / $scale, $labels);
            $targetNormalization = ['method' => 'training_only_standardization', 'mean' => $mean, 'scale' => $scale];
        }
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
            'target_scaling' => $remainingFraction ? 'remaining_cost_fraction_of_budget' : 'currency',
            'target_normalization' => $targetNormalization,
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
            'fin_equipment_expense', 'fin_other_expense', 'expense_frequency_30d',
            'stock_out_frequency_30d', 'expense_amount_per_day_30d',
            'has_unvalued_stock_out' => max(0, (float) ($row->{$name} ?? 0)),
            default => in_array($name, ProjectCostFeatureBuilder::FEATURE_NAMES, true) ? (float) ($row->{$name} ?? 0) : 0.0,
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
        $constrainedCount = 0;
        $featureNames = $transformer['feature_names'] ?? self::BASE_FEATURE_NAMES;
        foreach ($testData as $row) {
            $transformed = $this->transformFeatureVector(
                $this->rowToFeatures($row, $featureNames),
                $transformer['selected_feature_indexes'],
                $transformer['ranges']
            );
            $prediction = (float) $model->predict($transformed);
            if (isset($transformer['target_normalization'])) {
                $prediction = $prediction * $transformer['target_normalization']['scale'] + $transformer['target_normalization']['mean'];
            }
            if (($transformer['target_scaling'] ?? 'currency') === 'remaining_cost_fraction_of_budget') {
                $prediction *= (float) $row->budget;
            }
            if (! is_finite($prediction)) {
                throw new RuntimeException('Holdout evaluation produced a non-finite prediction.');
            }
            $recordedSpend = isset($row->snapshot_id) ? (float) ($row->fin_total_expense ?? 0) : 0.0;
            $calculation = app(ProjectCostForecastCalculator::class)->calculate($prediction, $recordedSpend, isset($row->snapshot_id));
            $predictions[] = $calculation['final_cost'];
            $constrainedCount += (int) $calculation['spend_floor_applied'];
            $actuals[] = (float) $row->actual_cost + $recordedSpend;
            $budgets[] = (float) $row->budget;
        }

        $latestIndexes = [];
        foreach ($testData->values() as $index => $row) {
            $key = (string) ($row->project_id ?? 'observation-'.$index);
            $previous = $latestIndexes[$key] ?? null;
            if ($previous === null || strcmp((string) ($row->captured_at ?? ''), (string) ($testData->values()[$previous]->captured_at ?? '')) > 0) {
                $latestIndexes[$key] = $index;
            }
        }
        $latest = array_values($latestIndexes);

        return $this->calculateMetrics($predictions, $actuals, $budgets, $testData) + [
            'spend_floor_count' => $constrainedCount,
            'evaluation_forecast_policy' => 'final_cost_with_recorded_spend_floor',
            'latest_observation_per_project' => $this->calculateMetrics(
                array_map(fn ($index) => $predictions[$index], $latest),
                array_map(fn ($index) => $actuals[$index], $latest),
                array_map(fn ($index) => $budgets[$index], $latest),
                collect($latest)->map(fn ($index) => $testData->values()[$index])->values()
            ),
        ];
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

        $detection = [];
        foreach (['any_overrun', 'material_overrun'] as $definition) {
            $detection[$definition] = app(ProjectOverrunEvaluation::class)->evaluate($predictions, $actuals, $budgets, $definition);
        }
        $material = $detection['material_overrun'];

        $metrics = [
            'accuracy' => $accuracy === null ? null : round($accuracy, 2),
            'mean_absolute_error' => round($mae, 2),
            'mean_absolute_percentage_error' => $mape === null ? null : round($mape, 2),
            'r_squared' => $rSquared === null ? null : round($rSquared, 4),
            'precision' => $material['precision'],
            'recall' => $material['recall'],
            'f1_score' => $material['f1_score'],
            'overrun_classification_accuracy' => $material['classification_accuracy'],
            'classification_counts' => $material['classification_counts'],
            'overrun_detection' => $detection,
            'evaluation_observations' => $count,
            'evaluation_projects' => $rows?->pluck('project_id')->filter()->unique()->count(),
            'observation_weighting' => 'Each held-out observation has equal weight; multiple progress snapshots from one project are correlated.',
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
            $groups[$segment]['projects'][(string) ($row->project_id ?? $index)] = true;
        }

        return collect($groups)->map(function (array $group) {
            $metrics = $this->calculateMetrics($group['predictions'], $group['actuals'], $group['budgets']);

            return [
                'samples' => count($group['predictions']),
                'projects' => count($group['projects']),
                'mean_absolute_error' => $metrics['mean_absolute_error'],
                'mean_absolute_percentage_error' => $metrics['mean_absolute_percentage_error'],
                'precision' => $metrics['precision'],
                'recall' => $metrics['recall'],
                'f1_score' => $metrics['f1_score'],
                'classification_counts' => $metrics['classification_counts'],
                'overrun_detection' => $metrics['overrun_detection'],
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

    public function predict(array $features, array $context = []): float
    {
        $features = $this->normalizePredictionFeatures($features);
        $this->validateFeatureVector($features);
        $this->lastEngineeredFeatures = app(ProjectCostFeatureBuilder::class)->build(array_replace($context,
            array_combine(self::FEATURE_NAMES, array_map('floatval', $features)),
            ['fin_total_expense' => $this->recordedSpend($features)]));
        $this->lastPredictionWarnings = $this->predictionWarnings($features);
        $this->lastPredictionWasConstrained = false;
        $this->lastForecastCalculation = [];
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
                $this->predictionFeatureVector($features, $featureNames, $this->lastEngineeredFeatures['values']),
                $transformer['selected_feature_indexes'],
                $transformer['ranges']
            );
            $prediction = (float) $this->model->predict($transformed);
            if (isset($transformer['target_normalization'])) {
                $prediction = $prediction * $transformer['target_normalization']['scale'] + $transformer['target_normalization']['mean'];
            }
            if (($transformer['target_scaling'] ?? 'currency') === 'remaining_cost_fraction_of_budget') {
                $prediction *= (float) $features[0];
            }
            $remainingCost = ($this->metadata['prediction_target'] ?? 'final_cost') === 'remaining_cost_then_add_recorded_spend';
            if (! is_finite($prediction)) {
                throw new RuntimeException('Model returned an invalid project cost.');
            }
            $this->lastPredictionSource = $this->metadata['model_source'] ?? 'trained_model';
            $this->lastForecastCalculation = app(ProjectCostForecastCalculator::class)->calculate($prediction, $this->recordedSpend($features), $remainingCost);
            $prediction = $this->lastForecastCalculation['raw_final_cost'];
        } catch (Throwable $exception) {
            Log::error('Trained-model prediction failed; applying the rule-based estimate.', ['message' => $exception->getMessage()]);
            $this->lastPredictionSource = 'rule_based_fallback';
            $this->lastPredictionWarnings[] = 'The trained estimator failed, so this result uses the rule-based fallback formula.';

            $prediction = $this->fallbackPrediction($features);
            $this->lastForecastCalculation = app(ProjectCostForecastCalculator::class)->calculate($prediction, $this->recordedSpend($features), false);
        }

        $recordedSpend = $this->recordedSpend($features);
        if ($prediction < $recordedSpend) {
            $prediction = $recordedSpend;
            $this->lastPredictionWasConstrained = true;
            $this->lastPredictionWarnings[] = 'The raw estimate was below recorded spending. The displayed value was raised to the recorded-spend floor and must not be treated as an on-track forecast.';
        }
        if ($prediction <= 0) {
            $this->lastPredictionWasConstrained = true;
            $this->lastPredictionWarnings[] = 'There is no positive final-cost estimate. This result cannot support a project budget decision.';
        }
        $this->lastPredictionSupport = $this->predictionSupport($features);
        $this->lastForecastCalculation['estimate_source'] = $this->lastPredictionSource;

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

    protected function predictionFeatureVector(array $features, array $featureNames, array $engineered = []): array
    {
        $byName = array_replace(array_combine(self::FEATURE_NAMES, array_map('floatval', $features)), $engineered);

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
        $finOtherExpense = 0,
        $expenseFrequency30d = 0,
        $stockOutFrequency30d = 0,
        $expenseAmountPerDay30d = 0,
        $hasUnvaluedStockOut = 0,
        array $context = []
    ): float {
        return $this->predict(array_map('floatval', [
            $budget, $durationMonths, $workerCount, $completionPercentage, $materialCost, $laborCost,
            $finTotalExpense, $finMaterialExpense, $finLaborExpense, $finEquipmentExpense, $finOtherExpense,
            $expenseFrequency30d, $stockOutFrequency30d, $expenseAmountPerDay30d, $hasUnvaluedStockOut,
        ]), $context);
    }

    public function getLastFeatureIndicators(): array
    {
        return $this->lastEngineeredFeatures['indicators'] ?? [];
    }

    public function getLastForecastCalculation(): array
    {
        return $this->lastForecastCalculation;
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
        if ($this->lastPredictionSource === 'rule_based_fallback') {
            return ['prediction_usable' => false, 'support_level' => 'fallback_only',
                'forecast_context' => $context, 'status_reason' => 'The trained estimator failed. This rule-based estimate has no validated model performance.'];
        }
        $baselineSupported = (bool) ($this->metadata['budget_baseline_comparison']['model_outperforms_budget_baseline'] ?? false);
        $productionModelIsBest = (bool) ($this->metadata['model_comparison']['production_model_is_best_option'] ?? false);
        $snapshotReady = (bool) ($this->metadata['snapshot_readiness']['eligible'] ?? false);
        if ($strategy === 'progress_snapshot_model' && ($this->lastEngineeredFeatures['values']['snapshot_cost_coverage_complete'] ?? 0) !== 1) {
            return ['prediction_usable' => false, 'support_level' => 'incomplete_cost_coverage',
                'forecast_context' => $context, 'status_reason' => 'Complete observed project costs are required for a progress forecast.'];
        }

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
        if (in_array('Project inventory withdrawals include unvalued material; known material cost is incomplete.', $this->lastPredictionWarnings, true)) {
            return [
                'prediction_usable' => false,
                'support_level' => 'unvalued_material',
                'forecast_context' => $context,
                'status_reason' => 'Project material cost is incomplete because one or more withdrawals are unvalued.',
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
                'status_reason' => 'The model has not outperformed the recorded-budget baseline on the untouched holdout.',
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
        if (($features[14] ?? 0) > 0) {
            $warnings[] = 'Project inventory withdrawals include unvalued material; known material cost is incomplete.';
        }
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
        $byName = array_replace(array_combine(self::FEATURE_NAMES, array_map('floatval', $features)), $this->lastEngineeredFeatures['values'] ?? []);
        foreach ($byName as $name => $value) {
            $range = $this->metadata['feature_ranges'][$name] ?? null;
            if (! is_array($range)) {
                continue;
            }
            $value = (float) $value;
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
        $estimatedHistoricalPurchases = (int) ($this->metadata['estimated_historical_purchase_count'] ?? 0);
        if ($estimatedHistoricalPurchases > 0) {
            $provenanceWarnings[] = "{$estimatedHistoricalPurchases} historical inventory purchases were priced using current item prices, not original invoices. Model metrics are conditional on these estimates.";
        }

        return [
            'status' => match ($source) {
                'real_trained_model' => $estimatedHistoricalPurchases > 0
                    ? 'Model is trained on completed projects with estimated historical inventory costs'
                    : 'Model is trained on verified completed projects',
                'sample_trained_model' => 'Model is trained on a company-inspired sample dataset',
                default => 'Synthetic fallback model is active',
            },
            'model_source' => $source,
            'model_type' => $this->model ? CostModelStore::algorithm($this->model) : null,
            'model_recovery_source' => $this->modelRecoverySource,
            'uses_synthetic_data' => (bool) ($this->metadata['uses_synthetic_data'] ?? false),
            'estimated_historical_purchase_count' => $estimatedHistoricalPurchases,
            'samples_trained' => (int) ($this->metadata['samples_trained'] ?? 0),
            'real_samples_available' => (int) ($this->metadata['real_samples_available'] ?? 0),
            'sample_samples_available' => (int) ($this->metadata['sample_samples_available'] ?? 0),
            'training_samples' => (int) ($this->metadata['training_samples_evaluated'] ?? 0),
            'test_samples' => (int) ($this->metadata['test_samples'] ?? 0),
            'evaluation_method' => $this->metadata['evaluation_method'] ?? 'unavailable',
            'evaluation_protocol_version' => $this->metadata['evaluation_protocol_version'] ?? 1,
            'accuracy' => $metrics['accuracy'] ?? null,
            'mean_absolute_error' => $mae,
            'mean_absolute_percentage_error' => $metrics['mean_absolute_percentage_error'] ?? null,
            'r_squared' => $metrics['r_squared'] ?? null,
            'precision' => $metrics['precision'] ?? null,
            'recall' => $metrics['recall'] ?? null,
            'f1_score' => $metrics['f1_score'] ?? null,
            'overrun_detection' => $metrics['overrun_detection'] ?? null,
            'evaluation_observations' => $metrics['evaluation_observations'] ?? null,
            'latest_observation_per_project' => $metrics['latest_observation_per_project'] ?? null,
            'evaluation_scope_label' => $this->metadata['evaluation_scope_label'] ?? null,
            'cohort_policy' => $this->metadata['cohort_policy'] ?? 'auto',
            'candidate_evaluations' => $this->getCandidateEvaluationReports(),
            'training_projects' => $this->metadata['training_projects'] ?? null,
            'progress_feature_selection' => $this->metadata['progress_feature_selection'] ?? null,
            'evaluation_projects' => $metrics['evaluation_projects'] ?? null,
            'observation_weighting' => $metrics['observation_weighting'] ?? null,
            'overrun_classification_accuracy' => $metrics['overrun_classification_accuracy'] ?? null,
            'mae_formatted' => $mae === null ? 'Unavailable' : '₱'.number_format((float) $mae, 2),
            'metric_scope' => match ($source) {
                'real_trained_model' => 'fixed newest-20-percent grouped chronological project holdout',
                'sample_trained_model' => 'company-inspired sample chronological holdout; sample evaluation only, not company-validated accuracy',
                default => 'unavailable for synthetic fallback data',
            },
            'warnings' => $provenanceWarnings,
            'classification_definition' => 'Precision, recall and F1 classify project-overrun risk when cost exceeds budget by more than 5%. Values are percentages and are unavailable when the holdout has no applicable positive cases.',
            'overrun_definitions' => [
                'budget_basis' => ($this->metadata['prediction_strategy'] ?? null) === 'progress_snapshot_model' ? 'budget_recorded_at_snapshot' : 'latest_recorded_budget',
                'any_overrun' => 'Final cost exceeds the recorded budget used for evaluation.',
                'material_overrun' => 'Final cost exceeds that budget by more than 5%. Exactly 5% is not a material overrun.',
                'original_budget' => 'Evaluated separately when an initial recorded budget is available; existing and imported budgets are not assumed to be original.',
            ],
            'monitoring_segments' => $metrics['monitoring_segments'] ?? null,
            'split_selection' => $this->metadata['split_selection'] ?? null,
            'cross_validation' => $this->metadata['cross_validation'] ?? null,
            'feature_set' => $this->metadata['feature_set'] ?? null,
            'feature_engineering' => $this->featureEngineeringMetadata(),
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
            'interpretation' => $this->getInterpretation($metrics, $sufficiency, $source)
                .($estimatedHistoricalPurchases > 0
                    ? ' Historical inventory costs use current-price estimates, not original invoices; treat these performance figures as provisional.'
                    : ''),
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
            'feature_engineering' => $this->featureEngineeringMetadata(),
            'budget_baseline_comparison' => null, 'snapshot_readiness' => null,
            'prediction_strategy' => 'unavailable', 'prediction_target' => 'unavailable',
            'data_capture_policy' => $this->dataCapturePolicy(),
            'retraining_policy' => $this->retrainingPolicy(),
            'risk_business_actions' => $this->riskBusinessActions(),
            'sample_sufficiency' => $this->sampleSufficiency(0), 'feature_ranges' => [],
            'trained_at' => null, 'fallback_reason' => null, 'interpretation' => $interpretation,
        ];
    }

    protected function featureEngineeringMetadata(): array
    {
        $active = array_values(array_intersect($this->metadata['transformer']['selected_feature_names'] ?? [],
            ProjectCostFeatureBuilder::FEATURE_NAMES));

        return ProjectCostFeatureBuilder::catalog() + [
            'active_feature_names' => $active,
            'active_model_formula_version' => $this->metadata['feature_engineering_version'] ?? null,
            'status' => $active === [] ? 'prepared_for_future_training' : 'active_in_saved_model',
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

    public function retrain(?string $cohortMode = null): array
    {
        $realModelTrained = $this->train($cohortMode);
        $metrics = $this->getModelMetrics();

        return [
            'message' => $realModelTrained
                ? ($metrics['model_source'] === 'sample_trained_model'
                    ? 'Model retrained on a company-inspired sample dataset; metrics are sample evaluation only.'
                    : (($metrics['estimated_historical_purchase_count'] ?? 0) > 0
                        ? 'Model retrained on completed projects; historical inventory costs use current-price estimates, not original invoices.'
                        : 'Model retrained on verified completed projects.'))
                : 'Candidate did not pass training gates; the previously saved estimator or fallback is retained.',
            'candidate_activated' => $realModelTrained,
            'model_source' => $metrics['model_source'], 'metrics' => $metrics,
        ];
    }

    public function getModelMetadata(): array
    {
        return $this->metadata;
    }

    protected function restoreStoredModel(): bool
    {
        try {
            $pair = (new CostModelStore($this->modelPath, self::MODEL_SCHEMA_VERSION))->load();
            if ($pair === null) {
                return false;
            }
            $this->model = $pair['model'];
            $this->metadata = $pair['metadata'];
            $this->modelRecoverySource = $pair['recovery_source'];
            if ($this->modelRecoverySource === 'previous') {
                Log::warning('The active cost model pair is unavailable; serving the verified previous model.');
            }

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
        $this->metadata = (new CostModelStore($this->modelPath, self::MODEL_SCHEMA_VERSION))
            ->save($this->model, $this->metadata);
        $this->modelRecoverySource = 'active';
    }

    public function analyzeBudgetVariance(): Collection
    {
        try {
            $expenseTotals = $this->financeActualExpenseTotalsQuery();
            $allocationTotals = $this->allocationAggregateQuery(false);
            $actual = $allocationTotals === null
                ? ($expenseTotals === null ? 'COALESCE(budgets_tbl.actual_amount, 0)' : 'COALESCE(expense_totals.actual_cost, budgets_tbl.actual_amount, 0)')
                : 'COALESCE(expense_totals.actual_cost, 0) + COALESCE(allocation_totals.material_cost, 0)';

            // Budgets is the source of truth for this comparison.  Start with
            // every budget row (rather than the latest row per project) and
            // left join its project so the comparison cannot silently omit a
            // budget that is present in the Budgets module.
            $query = DB::table('budgets_tbl')
                ->leftJoin('project_tbl', 'project_tbl.project_id', '=', 'budgets_tbl.project_id');

            if ($expenseTotals !== null) {
                $query->leftJoinSub($expenseTotals, 'expense_totals', fn ($join) => $join->on('project_tbl.project_id', '=', 'expense_totals.project_id'));
            }
            if ($allocationTotals !== null) {
                $query->leftJoinSub($allocationTotals, 'allocation_totals', fn ($join) => $join->on('project_tbl.project_id', '=', 'allocation_totals.project_id'));
            }

            return $query
                ->select(
                    'budgets_tbl.budget_id', 'budgets_tbl.project_id',
                    'project_tbl.project_name', 'project_tbl.start_date', 'budgets_tbl.budget_amount as budget', 'project_tbl.status',
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
            ->when(Schema::hasColumn('fin_expense_tbl', 'inventory_transaction_id'),
                fn ($query) => $query->whereNull('inventory_transaction_id'))
            ->groupBy('project_id');
    }
}
