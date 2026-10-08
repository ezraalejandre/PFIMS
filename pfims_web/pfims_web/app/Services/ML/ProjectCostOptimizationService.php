<?php

namespace App\Services\ML;

use App\Services\MLService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/** Training-only model selection; real outcomes and the held-out partition are immutable. */
class ProjectCostOptimizationService extends MLService
{
    private array $validationCache = [];

    private ?float $ratio = null;

    public function __construct(?string $modelPath = null)
    {
        parent::__construct($modelPath, false);
    }

    public function run(?string $sourcePath = null, ?callable $progress = null): array
    {
        $lock = fopen($this->modelPath.'.optimization.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another optimization is running.');
        }
        try {
            return $this->optimize($sourcePath, $progress ?? static fn ($message) => null);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    protected function sourceCohort(?string $path): array
    {
        if ($path === null) {
            return $this->databaseTrainingCohort();
        }
        $source = json_decode(gzdecode(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        if (($source['source']['database'] ?? null) !== 'u822802132_pfims'
            || ($source['source']['host'] ?? null) !== 'srv603.hstgr.io') {
            throw new RuntimeException('A verified live PFIMS source export is required.');
        }
        $cohort = $source['cohort'];
        $cohort['records'] = collect($cohort['records'])->map(fn ($row) => (object) $row);
        $cohort['source_capture'] = $source['source']['captured_at'];
        $cohort['source_sha256'] = hash_file('sha256', $path);

        return $cohort;
    }

    private function setInfluence(?float $ratio): void
    {
        $this->ratio = $ratio;
        $this->augmentation = new ProjectCostAugmentationDataset(config('ml.augmentation_directory'), $ratio);
    }

    private function trial(Collection $training, array $features, string $algorithm, array $parameters = []): array
    {
        $key = json_encode([$this->ratio, $this->useBudgetRatioTarget, $features, $algorithm, $parameters]);
        if (isset($this->validationCache[$key])) {
            return $this->validationCache[$key];
        }
        $trial = ['algorithm' => $algorithm, 'parameters' => $parameters, 'feature_names' => $features,
            'dummy_to_real_ratio' => $this->ratio, 'budget_ratio_target' => $this->useBudgetRatioTarget];
        try {
            $cv = $this->kFoldCrossValidation($training, $features, $algorithm, $parameters);
            if ($cv['folds_run'] < 2 || ! is_numeric($cv['average_mean_absolute_error'] ?? null)) {
                throw new RuntimeException('At least two usable training validation windows are required.');
            }
            $trial += ['status' => 'evaluated', 'cv_mae' => $cv['average_mean_absolute_error'],
                'cv_mape' => $cv['average_mean_absolute_percentage_error'], 'cross_validation' => $cv];
        } catch (Throwable $exception) {
            $trial += ['status' => 'unavailable', 'message' => $exception->getMessage()];
        }

        return $this->validationCache[$key] = $trial;
    }

    private function best(array $trials): array
    {
        $usable = array_values(array_filter($trials, fn ($trial) => $trial['status'] === 'evaluated'));
        usort($usable, fn ($a, $b) => [$a['cv_mae'], $a['cv_mape'], count($a['feature_names'])]
            <=> [$b['cv_mae'], $b['cv_mape'], count($b['feature_names'])]);
        if ($usable === []) {
            throw new RuntimeException('No candidate passed training-only validation.');
        }

        return $usable[0];
    }

    private function optimize(?string $sourcePath, callable $progress): array
    {
        $this->validationCache = [];
        $this->useBudgetRatioTarget = false;
        $cohort = $this->sourceCohort($sourcePath);
        $this->setInfluence(null);
        $this->augmentation->assertDatabaseMatches($cohort['records']);
        $split = ProjectCostAugmentationDataset::split($cohort['records']);
        $training = $cohort['records']->whereIn('project_id', $split['training'])->values();
        $test = $cohort['records']->whereIn('project_id', $split['test'])->values();
        $features = $cohort['feature_names'];
        $report = ['schema_version' => 1, 'generated_at' => now()->toIso8601String(),
            'source_capture' => $cohort['source_capture'] ?? now()->toIso8601String(),
            'source_sha256' => $cohort['source_sha256'] ?? null,
            'source_fingerprint' => ProjectCostAugmentationDataset::fingerprint($cohort['records']),
            'mode' => 'read_only_candidate_evaluation', 'cohort_policy' => 'database_augmented',
            'prediction_strategy' => $cohort['strategy'], 'status' => 'evaluated', 'active_model_changed' => false,
            'selection_rule' => 'Lowest training-only temporal validation MAE, then MAPE. Final test never selects settings.',
            'training_project_ids' => $split['training'], 'holdout_project_ids' => $split['test'],
            'holdout_status' => 'Previously inspected development holdout; not fresh independent release evidence.',
            'steps' => []];
        $progress('Step 1: compare real-only, reduced dummy influence, and the full dummy dataset.');
        $trials = [];
        foreach ([0.0, 0.5, 1.0, 3.0, null] as $ratio) {
            $this->setInfluence($ratio);
            $trials[] = $this->trial($training, $features, 'least_squares_linear_regression');
        }
        $best = $this->best($trials);
        $report['steps']['1_augmentation'] = ['selected_ratio' => $best['dummy_to_real_ratio'], 'trials' => $trials];
        $this->setInfluence($best['dummy_to_real_ratio']);

        $progress('Step 2: compare linear, regularized linear, and locally trained SVR.');
        $models = [$best];
        foreach ([0.01, 0.1, 1.0, 10.0] as $alpha) {
            $models[] = $this->trial($training, $features, 'ridge_linear_regression', compact('alpha'));
        }
        foreach ([1.0, 10.0, 100.0] as $cost) {
            foreach ([0.1, 1.0] as $gamma) {
                foreach ([0.01, 0.1] as $epsilon) {
                    $models[] = $this->trial($training, $features, 'support_vector_regression_rbf', compact('cost', 'gamma', 'epsilon'));
                }
            }
        }
        $best = $this->best($models);
        $report['steps']['2_models'] = ['selected_algorithm' => $best['algorithm'], 'trials' => $models];

        $progress('Step 3: validate available features and currency versus budget-normalized targets.');
        $sets = ['existing' => $features];
        if ($cohort['strategy'] === 'planning_only_baseline') {
            $sets['budget_only'] = ['budget'];
            $sets['planning_engineered'] = ['budget', 'duration_months', 'log_budget', 'planned_budget_per_month', 'planned_duration_squared'];
        } else {
            $selected = $this->selectProgressFeatures($training, $features);
            $sets['validated_progress'] = $selected['selected_feature_names'];
        }
        $featureTrials = [];
        $finalists = collect($models)->where('status', 'evaluated')->groupBy('algorithm')->map(fn ($items) => $this->best($items->all()));
        foreach ($sets as $setName => $names) {
            foreach ([false, true] as $budgetRatio) {
                $this->useBudgetRatioTarget = $budgetRatio;
                $progress('Validating '.$setName.' inputs with '.($budgetRatio ? 'budget-normalized' : 'currency').' targets.');
                foreach ($finalists as $candidate) {
                    $featureTrials[] = $this->trial($training, $names, $candidate['algorithm'], $candidate['parameters']);
                }
            }
        }
        $best = $this->best($featureTrials);
        // Recheck influence for the selected feature/target combination on training only.
        foreach ([0.0, 0.5, 1.0, 3.0, null] as $ratio) {
            $this->setInfluence($ratio);
            $this->useBudgetRatioTarget = $best['budget_ratio_target'];
            $featureTrials[] = $this->trial($training, $best['feature_names'], $best['algorithm'], $best['parameters']);
        }
        $best = $this->best($featureTrials);
        $report['steps']['3_features'] = ['selected' => $best, 'trials' => $featureTrials,
            'availability_note' => $cohort['strategy'] === 'planning_only_baseline'
                ? 'Budget and planned duration transformations only. Insufficient genuine historical progress coverage; no reconstructed progress is used.'
                : 'Existing captured progress, spending, burn, valued inventory, schedule and frequency features retained.',
            'deferred' => ['commitments', 'variations', 'productivity', 'price_changes']];

        $progress('Step 4: tune any-overrun classifier influence and alert threshold on training validation.');
        $detectorTrials = [];
        foreach ([0.0, 0.5, 1.0, 3.0] as $ratio) {
            $this->setInfluence($ratio);
            $tuning = $this->tuneOverrunClassifier($training, $best['feature_names'], $best['cross_validation']['folds']);
            if ($tuning['status'] === 'tuned') {
                $detectorTrials[] = ['ratio' => $ratio, 'tuning' => $tuning];
            }
        }
        usort($detectorTrials, fn ($a, $b) => [
            $b['tuning']['trials'][0]['evaluation']['f1_score'] ?? 0,
            $b['tuning']['trials'][0]['evaluation']['recall'] ?? 0,
        ] <=> [
            $a['tuning']['trials'][0]['evaluation']['f1_score'] ?? 0,
            $a['tuning']['trials'][0]['evaluation']['recall'] ?? 0,
        ]);
        $detectorRatio = $detectorTrials[0]['ratio'] ?? 0.0;
        $report['steps']['4_detection'] = ['selected_ratio' => $detectorRatio, 'trials' => $detectorTrials];

        $progress('Step 5: freeze selected settings and verify disjoint temporal validation windows.');
        foreach ($best['cross_validation']['folds'] as $fold) {
            if (array_intersect($fold['training_project_ids'], $fold['test_project_ids']) !== []
                || array_diff([...$fold['training_project_ids'], ...$fold['test_project_ids']], $split['training']) !== []
                || $fold['latest_training_completion'] >= $fold['earliest_test_completion']) {
                throw new RuntimeException('Invalid project-grouped temporal fold.');
            }
        }
        $report['steps']['5_validation'] = ['passed' => true, 'test_used_for_selection' => false,
            'folds_run' => $best['cross_validation']['folds_run'], 'settings_frozen_before_test' => true];

        $progress('Step 6: evaluate frozen candidates on the real-only test partition and check activation requirements.');
        $this->setInfluence($best['dummy_to_real_ratio']);
        $this->useBudgetRatioTarget = $best['budget_ratio_target'];
        [$model, $transformer] = $this->buildServableRegressionModel($best['algorithm'], $best['parameters'], $training, $best['feature_names']);
        $evaluation = $this->evaluateModel($model, $transformer, $test);
        $primaryTest = $test->sortBy('captured_at')->groupBy('project_id')->map(fn ($rows) => $rows->last())->values();
        $primary = $evaluation['latest_observation_per_project'];
        $active = $this->evaluateSavedActiveEstimator($primaryTest);
        $baseline = $this->budgetBaselineComparison($primaryTest, $primary);
        $mixed = $this->augmentation->augment($training);
        $genuine = $cohort['records']->every(fn ($row) => ($row->data_source ?? null) === 'operational');
        $metadata = ['schema_version' => 10, 'trained_at' => $report['generated_at'], 'model_type' => $best['algorithm'],
            'model_source' => $genuine ? 'real_trained_model' : 'sample_trained_model', 'uses_synthetic_data' => $mixed->count() > $training->count(),
            'prediction_strategy' => $cohort['strategy'], 'cohort_policy' => 'database_augmented',
            'prediction_target' => $cohort['strategy'] === 'progress_snapshot_model' ? 'remaining_cost_then_add_recorded_spend' : 'final_cost',
            'evaluation_scope_label' => 'Database-only development evaluation', 'evaluation' => $evaluation,
            'activation_primary_evaluation' => $primary, 'transformer' => $transformer,
            'training_projects' => $mixed->pluck('project_id')->unique()->count(), 'samples_trained' => $mixed->count(),
            'test_samples' => $test->count(), 'evaluation_training_project_ids' => $split['training'],
            'evaluation_holdout_project_ids' => $split['test'], 'feature_set' => ['selected_feature_names' => $best['feature_names']],
            'cross_validation' => $best['cross_validation'], 'augmentation' => $this->augmentation->summary(),
            'budget_baseline_comparison' => $baseline, 'evaluation_method' => 'grouped_chronological_80_20_database_only_holdout'];
        $candidatePath = $this->modelPath.'.optimized-candidate';
        $store = new CostModelStore($candidatePath, 10);
        $store->save($model, $metadata);
        $restored = $store->load();
        if ($restored === null) {
            throw new RuntimeException('Optimized candidate could not be restored.');
        }
        $restoredEvaluation = $this->evaluateModel($restored['model'], $restored['metadata']['transformer'], $test);
        if (abs($restoredEvaluation['mean_absolute_error'] - $evaluation['mean_absolute_error']) > 0.01
            || $restoredEvaluation['overrun_detection'] !== $evaluation['overrun_detection']) {
            throw new RuntimeException('Restored candidate does not reproduce evaluation.');
        }
        $evidence = ['verified_genuine_source_records' => $cohort['records']->every(fn ($row) => ($row->data_source ?? null) === 'operational'),
            'fresh_independent_holdout' => false, 'training_project_ids' => $split['training'], 'holdout_project_ids' => $split['test'],
            'training_only_tuning' => true, 'same_holdout_active_comparison' => ($active['status'] ?? null) === 'evaluated',
            'project_level_primary_evaluation' => true, 'active_model_evaluation' => $active['evaluation'] ?? [],
            'budget_baseline_mape' => $baseline['baseline_evaluation']['mean_absolute_percentage_error'] ?? null];
        $policy = app(ModelActivationPolicy::class);
        $costPolicy = $policy->assessCost($primary, $evidence);
        $this->setInfluence($detectorRatio);
        $detector = $this->evaluateOverrunClassifier($training, $test, $best['feature_names'], $best['cross_validation']['folds']);
        $detectorMetrics = $detector['evaluation']['latest_observation_per_project'] ?? [];
        $activeDetection = $active['evaluation']['overrun_detection']['any_overrun'] ?? [];
        $detectorPolicy = $policy->assessDetector($detectorMetrics, array_replace($evidence, ['active_model_evaluation' => $activeDetection]));
        $report += ['selected_candidate' => $best, 'evaluation' => $evaluation, 'overrun_classifier' => $detector,
            'activation_checks' => ['cost_model' => $costPolicy, 'overrun_detector' => $detectorPolicy],
            'candidate_path' => $candidatePath, 'restored_evaluation_verified' => true,
            'training_counts' => ['database_projects' => count($split['training']),
                'dummy_projects_used' => $mixed->pluck('project_id')->unique()->count() - count($split['training']),
                'dummy_projects_available' => $this->augmentation->summary()['dummy_projects'], 'test_database_projects' => count($split['test'])],
            'active_baseline' => $active, 'budget_baseline_comparison' => $baseline];
        $report['steps']['6_evaluation'] = ['completed' => true, 'active_model_changed' => false,
            'note' => 'Previously inspected test data cannot establish fresh independent activation evidence.'];
        $report['steps']['7_display'] = ['ready' => true, 'active_metrics_preserved' => true];
        $path = $this->modelPath.'.optimization.json';
        File::put($path.'.tmp', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::move($path.'.tmp', $path);
        $progress('Evaluation saved; active model and real business records are unchanged.');

        return $report;
    }
}
