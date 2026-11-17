<?php

namespace App\Services\ML;

use App\Services\MLService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Revalidation of the reviewed training and evaluation cohorts. */
class MergedDatasetRevalidationService
{
    private function trainingEnvironment(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') return [];
        // Web SAPIs may omit SystemRoot from the environment passed to Python.
        // Windows needs it when loading the providers used by asyncio/joblib.
        $root = getenv('SystemRoot') ?: getenv('WINDIR') ?: ((getenv('SystemDrive') ?: 'C:').'\\Windows');
        if (!is_dir($root.'\\System32')) throw new RuntimeException('The Windows system directory is unavailable for model training.');
        return ['SystemRoot' => $root, 'WINDIR' => $root, 'TEMP' => sys_get_temp_dir(), 'TMP' => sys_get_temp_dir()];
    }
    public static function enabled(): bool
    {
        $path = storage_path('app/ml-revalidation-20261117/enabled.json');
        if (!is_file($path)) return false;
        if (app()->environment('local')) return true;
        $approval = json_decode(File::get($path), true);
        return app()->environment('production')
            && ($approval['deployment_approved'] ?? false) === true
            && ($approval['application_url'] ?? null) === rtrim((string) config('app.url'), '/');
    }

    private static function assertAuthorized(): void
    {
        if (app()->environment('local')) {
            LocalImprovementWorkbench::assertLocal();
        } elseif (!self::enabled()) {
            throw new RuntimeException('This training and evaluation workflow has not been approved for this deployment.');
        }
    }

    public static function forestActivationEnabled(): bool
    {
        if (!self::enabled()) return false;
        $approval = json_decode(File::get(storage_path('app/ml-revalidation-20261117/enabled.json')), true);
        return ($approval['activate_random_forest'] ?? false) === true;
    }

    public function activateForest(): void
    {
        self::assertAuthorized();
        if (!self::forestActivationEnabled()) throw new RuntimeException('Forest activation has not been approved for this installation.');
        $directory = storage_path('app/ml-revalidation-20261117');
        $data = json_decode(File::get($directory.'/random-forest-portable.json'), true, 512, JSON_THROW_ON_ERROR);
        $model = PortableRandomForestClassifier::fromModelData($data);
        $parity = json_decode(File::get($directory.'/random-forest-parity.json'), true, 512, JSON_THROW_ON_ERROR);
        $maximum = 0.0;
        foreach ($parity['features'] as $i => $features) $maximum = max($maximum, abs($model->score($features) - $parity['scores'][$i]));
        if ($maximum > 1e-10) throw new RuntimeException('Forest PHP/Python parity check failed.');
        $report = json_decode(File::get($directory.'/results.json'), true, 512, JSON_THROW_ON_ERROR);
        $selected = $report['classification']['random_forest'];
        $m = $selected['tests']['database_combined']['all_checkpoints'];
        $evaluation = ['definition' => 'any_overrun', 'classification_accuracy' => $m['accuracy'],
            'precision' => $m['precision'], 'recall' => $m['recall'], 'f1_score' => $m['f1'],
            'balanced_accuracy' => $m['balanced_accuracy'], 'classification_counts' => $m['counts'],
            'evaluated_observations' => $m['observations'], 'actual_overruns' => $m['counts']['tp'] + $m['counts']['fn'],
            'actual_non_overruns' => $m['counts']['tn'] + $m['counts']['fp']];
        $path = storage_path('app/ml_model.phpml.detector.json');
        $costHash = hash_file('sha256', storage_path('app/ml_model.phpml'));
        $payload = ['schema_version' => 1, 'model' => $data, 'metadata' => [
            'model_type' => 'random_forest_classifier', 'trained_at' => now()->toIso8601String(),
            'feature_names' => json_decode(File::get($directory.'/source.json'), true)['features'],
            'threshold' => $selected['threshold'], 'parameters' => $selected['parameters'],
            'training_projects' => $report['counts']['fit_projects'], 'training_checkpoints' => $report['counts']['fit_checkpoints'],
            'evaluation' => $evaluation, 'evaluation_unit' => 'all_precompletion_checkpoints',
            'historical_evaluation' => $selected['tests']['historical_only']['all_checkpoints'],
            'chronological_validation' => $selected['chronological_validation'],
            'cost_model_sha256' => $costHash, 'portable_model_sha256' => hash('sha256', json_encode($data, JSON_THROW_ON_ERROR)),
            'development_activation' => ['mode' => 'explicit_user_requested_local_random_forest',
                'scope' => app()->environment('local') ? 'local_development' : 'user_approved_deployment', 'uses_generated_data' => true,
                'note' => 'Explicitly selected by user; these evaluations do not establish independent company validation.'],
            'parity_maximum_difference' => $maximum]];
        $lock = fopen($path.'.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Unable to lock detector activation.');
        try {
            if (is_file($path)) {
                if (!is_file($directory.'/before-random-forest-activation.json')) File::copy($path, $directory.'/before-random-forest-activation.json');
                File::copy($path, $path.'.previous');
            }
            File::put($path.'.tmp', json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            rename($path.'.tmp', $path);
        } finally { flock($lock, LOCK_UN); fclose($lock); }
        $loaded = app(ModelPromotionService::class)->loadDetector($path, $costHash);
        if (!$loaded || !$loaded['model'] instanceof PortableRandomForestClassifier || $loaded['recovery_source'] !== 'active') throw new RuntimeException('Activated forest could not be loaded.');
    }

    public function export(): array
    {
        self::assertAuthorized();
        $cohort = (new MLService(null, false))->databaseTrainingCohort();
        PlanningSpendingFeatures::validate($cohort);
        $directory = storage_path('app/ml-revalidation-20261117');
        File::ensureDirectoryExists($directory);
        $names = DB::table('project_tbl')->pluck('project_name', 'project_id')->all();
        $imported = array_keys(array_filter($names, fn ($name) => str_starts_with($name, 'Illustrative held-out project ')));
        $hashes = [];
        foreach (['ml_model.phpml', 'ml_model.phpml.meta.json', 'ml_model.phpml.detector.json'] as $name) {
            $path = storage_path('app/'.$name);
            if (is_file($path)) $hashes[$name] = hash_file('sha256', $path);
        }
        $payload = ['features' => $cohort['feature_names'], 'records' => $cohort['records']->values()->all(),
            'readiness' => $cohort['snapshot_readiness'], 'total_database_projects' => count($names),
            'imported_project_ids' => $imported, 'protected_hashes' => $hashes];
        File::put($directory.'/source.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $payload;
    }

    public function run(): array
    {
        self::assertAuthorized();
        $directory = storage_path('app/ml-revalidation-20261117');
        File::ensureDirectoryExists($directory);
        $lock = fopen($directory.'/training.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Merged dataset revalidation is already running.');
        try {
            $source = $this->export();
            $python = storage_path('app/ml-experiments/venv/'.(PHP_OS_FAMILY === 'Windows' ? 'Scripts/python.exe' : 'bin/python'));
            if (!is_file($python)) throw new RuntimeException('The model training environment is unavailable.');
            $process = new Process([$python, base_path('scripts/ml/refine_merged_dataset.py'), '--refit'], base_path(), $this->trainingEnvironment());
            $process->setTimeout(1800);
            $process->mustRun();
            File::put($directory.'/training.log', $process->getOutput());
            foreach ($source['protected_hashes'] as $name => $hash) {
                if (!hash_equals($hash, hash_file('sha256', storage_path('app/'.$name)))) throw new RuntimeException('Active model changed during candidate evaluation.');
            }
            if (self::forestActivationEnabled()) $this->activateForest();
            $this->publish();
            $report = json_decode(File::get($directory.'/results.json'), true, 512, JSON_THROW_ON_ERROR);
            return ['message' => self::forestActivationEnabled() ? 'Random forest retrained and activated; SVR cost predictor retained.' : 'Merged dataset training and revalidation completed; active models retained.',
                'candidate_activated' => false, 'detector_activated' => self::forestActivationEnabled(), 'training_performed' => true,
                'candidate_evaluation' => $report, 'model_source' => 'development_comparison',
                'metrics' => (new MLService)->getModelMetrics()];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function publish(): void
    {
        self::assertAuthorized();
        $directory = storage_path('app/ml-revalidation-20261117');
        $report = json_decode(File::get($directory.'/results.json'), true, 512, JSON_THROW_ON_ERROR);
        // Keep the established cost family; linear and ridge remain comparisons.
        $kind = 'svr';
        $m = $report['cost'][$kind]['generated_validation'];
        $chronological = $report['cost'][$kind]['chronological_validation'];
        $classifier = $report['validation_selected_winners']['classification'];
        $report['display_selection'] = ['cost' => $kind, 'classification' => $classifier,
            'reason' => 'User-finalized display: generated-validation MAE/MAPE; chronological historical validation R squared; combined-test refined random forest classification.',
            'evaluation_scopes' => ['mae_mape' => 'generated_validation', 'r_squared' => 'historical_chronological_validation', 'classification' => 'database_combined_test']];
        $costPrimary = $report['cost'][$kind]['tests']['historical_only']['all_checkpoints'];
        $classPrimary = $report['classification'][$classifier]['tests']['historical_only']['all_checkpoints'];
        $fitIds = $report['training_project_ids'];
        $testIds = $report['holdout_project_ids'];
        $evidence = ['verified_genuine_source_records' => false, 'fresh_independent_holdout' => false,
            'training_project_ids' => $fitIds, 'holdout_project_ids' => $testIds,
            'training_only_tuning' => true, 'same_holdout_active_comparison' => false,
            'project_level_primary_evaluation' => false];
        $policy = new ModelActivationPolicy;
        $report['activation_checks'] = [
            'cost' => $policy->assessCost(['mean_absolute_error' => $costPrimary['mae'],
                'mean_absolute_percentage_error' => $costPrimary['mape'], 'r_squared' => $costPrimary['r_squared'],
                'target_median' => $costPrimary['median_final_cost']], $evidence),
            'classifier' => $policy->assessDetector(['classification_accuracy' => $classPrimary['accuracy'],
                'precision' => $classPrimary['precision'], 'recall' => $classPrimary['recall'], 'f1_score' => $classPrimary['f1'],
                'balanced_accuracy' => $classPrimary['balanced_accuracy'],
                'actual_overruns' => $classPrimary['counts']['tp'] + $classPrimary['counts']['fn'],
                'actual_non_overruns' => $classPrimary['counts']['tn'] + $classPrimary['counts']['fp']], $evidence),
            'pipeline_algorithms_retained' => !self::forestActivationEnabled(), 'active_models_changed' => self::forestActivationEnabled(),
            'note' => 'Generated outcomes and reused development holdouts cannot establish independent operational performance. Existing activation requirements are unchanged.'];
        File::put($directory.'/results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $display = ['mode' => 'merged_development_evaluation', 'model_type' => $kind,
            'model_label' => ['svr' => 'SVR (RBF)', 'linear' => 'Linear regression', 'ridge' => 'Ridge regression'][$kind],
            'classification_model' => $classifier, 'generated_at' => now()->toIso8601String(),
            'scope' => '1,867 training projects (5,601 checkpoints); 600 generated-validation checkpoints; 120 historical chronological-validation checkpoints.',
            'training_projects' => $report['counts']['fit_projects'], 'training_checkpoints' => $report['counts']['fit_checkpoints'],
            'test_projects' => $report['counts']['database_test_projects'], 'test_checkpoints' => $report['counts']['database_test_checkpoints'],
            'generated_test_checkpoints' => $report['counts']['imported_generated_test_projects'] * 3,
            'classifier_active' => self::forestActivationEnabled(),
            'cost_scopes' => ['errors' => 'generated_validation', 'r_squared' => 'historical_chronological_validation'],
            'metrics' => ['mean_absolute_error' => $m['mae'], 'mean_absolute_percentage_error' => $m['mape'], 'r_squared' => $chronological['r_squared']],
            'classification' => $report['classification'][$classifier]['tests']['database_combined']['all_checkpoints']];
        $path = storage_path('app/ml-performance-display.json');
        if (is_file($path) && !is_file($directory.'/previous-performance-display.json')) File::copy($path, $directory.'/previous-performance-display.json');
        File::put($path.'.tmp', json_encode($display, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        rename($path.'.tmp', $path);
    }

    public static function display(): ?array
    {
        if (!self::enabled()) return null;
        try {
            $display = json_decode(File::get(storage_path('app/ml-performance-display.json')), true, 512, JSON_THROW_ON_ERROR);
            return ($display['mode'] ?? '') === 'merged_development_evaluation' ? $display : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
