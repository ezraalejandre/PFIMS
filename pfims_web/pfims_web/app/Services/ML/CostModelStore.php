<?php

namespace App\Services\ML;

use Illuminate\Support\Facades\File;
use Phpml\ModelManager;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\Regression;
use RuntimeException;
use Throwable;

/** Keep the active model with its details. Save the previous version for recovery. */
class CostModelStore
{
    // Set up the values this class needs.
    public function __construct(private string $path, private int $schemaVersion) {}

    // Get the algorithm.
    public static function algorithm(Regression $model): string
    {
        return match (true) {
            $model instanceof LeastSquares => 'least_squares_linear_regression',
            $model instanceof RidgeRegression => 'ridge_linear_regression',
            $model instanceof PortableRbfSvr => 'support_vector_regression_rbf',
            default => throw new RuntimeException('Unsupported served cost estimator.'),
        };
    }

    // Get the load.
    public function load(): ?array
    {
        return $this->locked(LOCK_SH, function () {
            // Process each suffix.
            foreach (['active' => '', 'previous' => '.previous'] as $source => $suffix) {
                // Try this action and handle any errors below.
                try {
                    $pair = $this->readPair($this->path.$suffix, $this->path.'.meta.json'.$suffix);
                    $pair['recovery_source'] = $source;

                    return $pair;
                } catch (Throwable) {
                    // Always use a model with the details saved for that same version.
                }
            }

            return null;
        });
    }

    // Read pair.
    private function readPair(string $modelPath, string $metadataPath): array
    {
        // Stop if the model path and saved details path checks match.
        if (! File::exists($modelPath) || ! File::exists($metadataPath)) {
            throw new RuntimeException('Cost model pair is incomplete.');
        }
        $metadata = json_decode(File::get($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        // Stop if the saved details schema version does not equal schema version.
        if (($metadata['schema_version'] ?? null) !== $this->schemaVersion) {
            throw new RuntimeException('Stored cost model schema is unsupported.');
        }
        // Stop if the saved details model sha256 and model path checks match.
        if (isset($metadata['model_sha256']) && ! hash_equals($metadata['model_sha256'], hash_file('sha256', $modelPath))) {
            throw new RuntimeException('Cost estimator and metadata do not match.');
        }
        $model = (new ModelManager)->restoreFromFile($modelPath);
        // Stop if the model check matches.
        if (! $model instanceof Regression) {
            throw new RuntimeException('Stored estimator is not a regression model.');
        }
        $algorithm = self::algorithm($model);
        // Read older version-10 LeastSquares models without training them again.
        if (($metadata['model_type'] ?? 'least_squares_linear_regression') !== $algorithm
            || (isset($metadata['estimator_class']) && $metadata['estimator_class'] !== $model::class)) {
            throw new RuntimeException('Stored cost algorithm identity does not match its estimator.');
        }
        $normalization = $metadata['transformer']['target_normalization'] ?? null;
        // Stop if the normalization and normalization mean checks match.
        if ($normalization !== null && (! is_numeric($normalization['mean'] ?? null)
            || ! is_finite((float) $normalization['mean']) || ! is_numeric($normalization['scale'] ?? null)
            || ! is_finite((float) $normalization['scale']) || $normalization['scale'] <= 0)) {
            throw new RuntimeException('Stored cost target normalization is invalid.');
        }

        return compact('model', 'metadata');
    }

    // Get the save.
    public function save(Regression $model, array $metadata): array
    {
        return $this->locked(LOCK_EX, function () use ($model, $metadata) {
            $suffix = '.candidate.'.bin2hex(random_bytes(8));
            $modelCandidate = $this->path.$suffix;
            $metadataCandidate = $this->path.'.meta.json'.$suffix;
            $backupModel = $this->path.'.backup.'.bin2hex(random_bytes(8));
            $backupMetadata = $this->path.'.meta.json.backup.'.bin2hex(random_bytes(8));
            $previousModel = $this->path.'.previous';
            $previousMetadata = $this->path.'.meta.json.previous';
            $previousModelCandidate = $previousModel.$suffix;
            $previousMetadataCandidate = $previousMetadata.$suffix;
            $hadModel = File::exists($this->path);
            $hadMetadata = File::exists($this->path.'.meta.json');
            // Try this action and handle any errors below.
            try {
                $metadata['model_type'] = self::algorithm($model);
                $metadata['estimator_class'] = $model::class;
                $metadata['schema_version'] = $this->schemaVersion;
                (new ModelManager)->saveToFile($model, $modelCandidate);
                $metadata['model_sha256'] = hash_file('sha256', $modelCandidate);
                // Stop if the saved details is false.
                if (file_put_contents($metadataCandidate, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    throw new RuntimeException('Unable to save candidate metadata.');
                }
                $this->readPair($modelCandidate, $metadataCandidate);
                $validCurrent = false;
                // Try this action and handle any errors below.
                try {
                    $this->readPair($this->path, $this->path.'.meta.json');
                    $validCurrent = true;
                } catch (Throwable) {
                    // A corrupt current pair must not overwrite a valid previous pair.
                }
                // Run this step if the valid current is set.
                if ($validCurrent) {
                    // Stop if the previous model candidate and previous saved details candidate checks match.
                    if (! copy($this->path, $previousModelCandidate) || ! copy($this->path.'.meta.json', $previousMetadataCandidate)) {
                        throw new RuntimeException('Unable to preserve the previous cost model.');
                    }
                    $this->readPair($previousModelCandidate, $previousMetadataCandidate);
                    // Stop if the previous model candidate and previous model checks match.
                    if (! rename($previousModelCandidate, $previousModel) || ! rename($previousMetadataCandidate, $previousMetadata)) {
                        throw new RuntimeException('Unable to publish the previous cost model backup.');
                    }
                }
                // Stop if the had model and backup model checks match.
                if ($hadModel && ! rename($this->path, $backupModel)) {
                    throw new RuntimeException('Unable to back up the active cost model.');
                }
                // Run this step if the had saved details and backup saved details checks match.
                if ($hadMetadata && ! rename($this->path.'.meta.json', $backupMetadata)) {
                    // Run this step if the had model is set.
                    if ($hadModel) {
                        rename($backupModel, $this->path);
                    }
                    throw new RuntimeException('Unable to back up active cost metadata.');
                }
                // Run this step if the model candidate and saved details candidate checks match.
                if (! rename($modelCandidate, $this->path) || ! rename($metadataCandidate, $this->path.'.meta.json')) {
                    File::delete([$this->path, $this->path.'.meta.json']);
                    // Run this step if the had model is set.
                    if ($hadModel) {
                        rename($backupModel, $this->path);
                    }
                    // Run this step if the had saved details is set.
                    if ($hadMetadata) {
                        rename($backupMetadata, $this->path.'.meta.json');
                    }
                    throw new RuntimeException('Unable to publish the complete cost model pair.');
                }
                File::delete([$backupModel, $backupMetadata]);

                return $metadata;
            } finally {
                File::delete([$modelCandidate, $metadataCandidate, $previousModelCandidate, $previousMetadataCandidate]);
            }
        });
    }

    // Get the locked.
    private function locked(int $mode, callable $operation): mixed
    {
        File::ensureDirectoryExists(dirname($this->path));
        $handle = fopen($this->path.'.store.lock', 'c');
        // Stop if the handle is false.
        if ($handle === false) {
            throw new RuntimeException('Cost model store lock is unavailable.');
        }
        // Try this action and handle any errors below.
        try {
            // Stop if the mode check matches.
            if (! flock($handle, $mode)) {
                throw new RuntimeException('Cost model store lock could not be acquired.');
            }

            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
