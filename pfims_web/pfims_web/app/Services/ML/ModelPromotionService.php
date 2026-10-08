<?php

namespace App\Services\ML;

use Illuminate\Support\Facades\File;
use Phpml\Regression\Regression;
use RuntimeException;
use Throwable;

/** Internal promotion boundary; no route accepts user-supplied approval evidence. */
class ModelPromotionService
{
    public function promoteCost(string $path, Regression $model, array $metadata, array $evidence): array
    {
        $checks = (new ModelActivationPolicy)->assessCost($metadata['activation_primary_evaluation'] ?? $metadata['evaluation'] ?? [], $evidence);
        if (! $checks['eligible']) {
            return ['activated' => false, 'policy' => $checks];
        }
        $metadata['activation_policy'] = $checks;
        $metadata['activation_evidence'] = $evidence;
        $stored = (new CostModelStore($path, 10))->save($model, $metadata);

        return ['activated' => true, 'policy' => $checks, 'metadata' => $stored];
    }

    public function promoteDetector(string $path, BudgetOverrunClassifier $model, array $metadata, array $evidence): array
    {
        $checks = (new ModelActivationPolicy)->assessDetector($metadata['evaluation'] ?? [], $evidence);
        if (! $checks['eligible']) {
            return ['activated' => false, 'policy' => $checks];
        }
        $data = $model->modelData();
        $names = $metadata['feature_names'] ?? [];
        $threshold = $metadata['threshold'] ?? null;
        if (count($names) !== count($data['ranges']) || count(array_unique($names)) !== count($names)
            || ! is_numeric($threshold) || ! is_finite((float) $threshold) || $threshold <= 0 || $threshold >= 1
            || ($metadata['evaluation']['definition'] ?? null) !== 'any_overrun') {
            throw new RuntimeException('Detector metadata does not match the trained model.');
        }
        $metadata['activation_policy'] = $checks;
        $metadata['activation_evidence'] = $evidence;
        $payload = json_encode(['schema_version' => 1, 'model' => $data, 'metadata' => $metadata], JSON_THROW_ON_ERROR);
        File::ensureDirectoryExists(dirname($path));
        $lock = fopen($path.'.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the detector store.');
        }
        $temporary = $path.'.candidate.'.bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporary, $payload) === false) {
                throw new RuntimeException('Unable to write the detector candidate.');
            }
            if (is_file($path) && ! copy($path, $path.'.previous')) {
                throw new RuntimeException('Unable to preserve the previous detector.');
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Unable to publish the detector.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return ['activated' => true, 'policy' => $checks];
    }

    public function loadDetector(string $path): ?array
    {
        foreach (['active' => $path, 'previous' => $path.'.previous'] as $source => $file) {
            try {
                if (! is_file($file)) {
                    continue;
                }
                $stored = json_decode(File::get($file), true, 512, JSON_THROW_ON_ERROR);
                $metadata = $stored['metadata'] ?? [];
                if (($stored['schema_version'] ?? null) !== 1
                    || ! (new ModelActivationPolicy)->assessDetector($metadata['evaluation'] ?? [], $metadata['activation_evidence'] ?? [])['eligible']
                    || ($metadata['evaluation']['definition'] ?? null) !== 'any_overrun'
                    || count($metadata['feature_names'] ?? []) !== count($stored['model']['ranges'] ?? [])
                    || ! is_numeric($metadata['threshold'] ?? null) || $metadata['threshold'] <= 0 || $metadata['threshold'] >= 1) {
                    continue;
                }

                return ['model' => BudgetOverrunClassifier::fromModelData($stored['model']), 'metadata' => $metadata, 'recovery_source' => $source];
            } catch (Throwable) {
                // Invalid detector artifacts never interfere with the active cost estimator.
            }
        }

        return null;
    }
}
