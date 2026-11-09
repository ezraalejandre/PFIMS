<?php

namespace App\Services\ML;

use Illuminate\Support\Facades\File;
use Phpml\Regression\Regression;
use RuntimeException;
use Throwable;

/** Activate models through this service. Web requests cannot supply their own approval records. */
class ModelPromotionService
{
    // Get the promote cost.
    public function promoteCost(string $path, Regression $model, array $metadata, array $evidence, bool $reviewedDevelopmentUse = false): array
    {
        $checks = (new ModelActivationPolicy)->assessCost($metadata['activation_primary_evaluation'] ?? $metadata['evaluation'] ?? [], $evidence);
        // Return early if the checks eligible and reviewed development use checks match.
        if (! $checks['eligible'] && ! ($reviewedDevelopmentUse && $this->developmentRequirementsMet($checks))) {
            return ['activated' => false, 'policy' => $checks];
        }
        $metadata['activation_policy'] = $checks;
        $metadata['activation_evidence'] = $evidence;
        // Run this step if the checks eligible check matches.
        if (! $checks['eligible']) {
            $metadata['development_activation'] = $this->developmentApproval($checks);
        }
        $stored = (new CostModelStore($path, 10))->save($model, $metadata);

        return ['activated' => true, 'policy' => $checks, 'metadata' => $stored];
    }

    // Get the promote detector.
    public function promoteDetector(string $path, BudgetOverrunClassifier $model, array $metadata, array $evidence, bool $reviewedDevelopmentUse = false): array
    {
        $checks = (new ModelActivationPolicy)->assessDetector($metadata['evaluation'] ?? [], $evidence);
        // Return early if the checks eligible and reviewed development use checks match.
        if (! $checks['eligible'] && ! ($reviewedDevelopmentUse && $this->developmentRequirementsMet($checks))) {
            return ['activated' => false, 'policy' => $checks];
        }
        $data = $model->modelData();
        $names = $metadata['feature_names'] ?? [];
        $threshold = $metadata['threshold'] ?? null;
        // Stop if the names and data ranges checks match.
        if (count($names) !== count($data['ranges']) || count(array_unique($names)) !== count($names)
            || ! is_numeric($threshold) || ! is_finite((float) $threshold) || $threshold <= 0 || $threshold >= 1
            || ($metadata['evaluation']['definition'] ?? null) !== 'any_overrun') {
            throw new RuntimeException('Detector metadata does not match the trained model.');
        }
        $metadata['activation_policy'] = $checks;
        $metadata['activation_evidence'] = $evidence;
        // Run this step if the checks eligible check matches.
        if (! $checks['eligible']) {
            $metadata['development_activation'] = $this->developmentApproval($checks);
        }
        $payload = json_encode(['schema_version' => 1, 'model' => $data, 'metadata' => $metadata], JSON_THROW_ON_ERROR);
        File::ensureDirectoryExists(dirname($path));
        $lock = fopen($path.'.lock', 'c');
        // Stop if the lock checks match.
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the detector store.');
        }
        $temporary = $path.'.candidate.'.bin2hex(random_bytes(8));
        // Try this action and handle any errors below.
        try {
            // Stop if the payload is false.
            if (file_put_contents($temporary, $payload) === false) {
                throw new RuntimeException('Unable to write the detector candidate.');
            }
            // Stop if the path checks match.
            if (is_file($path) && ! copy($path, $path.'.previous')) {
                throw new RuntimeException('Unable to preserve the previous detector.');
            }
            // Stop if the path check matches.
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Unable to publish the detector.');
            }
        } finally {
            // Run this step if the temporary file exists.
            if (is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['activated' => true, 'policy' => $checks];
    }

    // Load detector.
    public function loadDetector(string $path, ?string $servedCostHash = null): ?array
    {
        // Process each file.
        foreach (['active' => $path, 'previous' => $path.'.previous'] as $source => $file) {
            // Try this action and handle any errors below.
            try {
                // Skip this item if the file file is missing.
                if (! is_file($file)) {
                    continue;
                }
                $stored = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
                $metadata = $stored['metadata'] ?? [];
                $checks = (new ModelActivationPolicy)->assessDetector($metadata['evaluation'] ?? [], $metadata['activation_evidence'] ?? []);
                $development = ($metadata['development_activation']['mode'] ?? null) === 'explicit_user_requested_development'
                    && ($metadata['development_activation']['waived_requirements'] ?? null) === $checks['failed_requirements']
                    && $this->developmentRequirementsMet($checks);
                $costPath = substr($path, 0, -strlen('.detector.json'));
                $pairedCost = $metadata['cost_model_sha256'] ?? null;
                $expectedCost = $servedCostHash ?? (is_file($costPath) ? hash_file('sha256', $costPath) : null);
                // Skip this item if the stored schema version and checks eligible checks match.
                if (($stored['schema_version'] ?? null) !== 1
                    || (! $checks['eligible'] && ! $development)
                    || ($pairedCost !== null && ($expectedCost === null || ! hash_equals($pairedCost, $expectedCost)))
                    || ($metadata['evaluation']['definition'] ?? null) !== 'any_overrun'
                    || count($metadata['feature_names'] ?? []) !== count($stored['model']['ranges'] ?? [])
                    || ! is_numeric($metadata['threshold'] ?? null) || $metadata['threshold'] <= 0 || $metadata['threshold'] >= 1) {
                    continue;
                }

                return ['model' => BudgetOverrunClassifier::fromModelData($stored['model']), 'metadata' => $metadata, 'recovery_source' => $source];
            } catch (Throwable) {
                // Skip invalid risk model files. Keep the active cost model working.
            }
        }

        return null;
    }

    /** A reviewer may accept development results. Data and score checks must still pass. */
    private function developmentRequirementsMet(array $checks): bool
    {
        return array_diff($checks['failed_requirements'], ['fresh_independent_holdout', 'same_holdout_active_comparison',
            'no_worse_than_active_mape', 'no_worse_than_active_mae', 'no_worse_than_active_f1_score',
            'no_worse_than_active_balanced_accuracy', 'no_worse_than_active_recall']) === [];
    }

    // Get the development approval.
    private function developmentApproval(array $checks): array
    {
        return ['mode' => 'explicit_user_requested_development', 'activated_at' => now()->toIso8601String(),
            'waived_requirements' => $checks['failed_requirements'],
            'note' => 'Activated at the user request after review of the development results. Independent validation and a reliable active-model comparison remain pending. Automatic promotion requirements are unchanged.'];
    }
}
