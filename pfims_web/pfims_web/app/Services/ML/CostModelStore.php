<?php

namespace App\Services\ML;

use Illuminate\Support\Facades\File;
use Phpml\ModelManager;
use Phpml\Regression\LeastSquares;
use Phpml\Regression\Regression;
use RuntimeException;
use Throwable;

/** One served estimator, paired with its metadata, plus one recoverable previous version. */
class CostModelStore
{
    public function __construct(private string $path, private int $schemaVersion) {}

    public static function algorithm(Regression $model): string
    {
        return match (true) {
            $model instanceof LeastSquares => 'least_squares_linear_regression',
            $model instanceof RidgeRegression => 'ridge_linear_regression',
            $model instanceof PortableRbfSvr => 'support_vector_regression_rbf',
            default => throw new RuntimeException('Unsupported served cost estimator.'),
        };
    }

    public function load(): ?array
    {
        return $this->locked(LOCK_SH, function () {
            foreach (['active' => '', 'previous' => '.previous'] as $source => $suffix) {
                try {
                    $pair = $this->readPair($this->path.$suffix, $this->path.'.meta.json'.$suffix);
                    $pair['recovery_source'] = $source;

                    return $pair;
                } catch (Throwable) {
                    // Never combine one estimator with another version's metadata.
                }
            }

            return null;
        });
    }

    private function readPair(string $modelPath, string $metadataPath): array
    {
        if (! File::exists($modelPath) || ! File::exists($metadataPath)) {
            throw new RuntimeException('Cost model pair is incomplete.');
        }
        $metadata = json_decode(File::get($metadataPath), true, 512, JSON_THROW_ON_ERROR);
        if (($metadata['schema_version'] ?? null) !== $this->schemaVersion) {
            throw new RuntimeException('Stored cost model schema is unsupported.');
        }
        if (isset($metadata['model_sha256']) && ! hash_equals($metadata['model_sha256'], hash_file('sha256', $modelPath))) {
            throw new RuntimeException('Cost estimator and metadata do not match.');
        }
        $model = (new ModelManager)->restoreFromFile($modelPath);
        if (! $model instanceof Regression) {
            throw new RuntimeException('Stored estimator is not a regression model.');
        }
        $algorithm = self::algorithm($model);
        // Existing schema-10 LeastSquares artifacts remain readable without retraining.
        if (($metadata['model_type'] ?? 'least_squares_linear_regression') !== $algorithm
            || (isset($metadata['estimator_class']) && $metadata['estimator_class'] !== $model::class)) {
            throw new RuntimeException('Stored cost algorithm identity does not match its estimator.');
        }
        $normalization = $metadata['transformer']['target_normalization'] ?? null;
        if ($normalization !== null && (! is_numeric($normalization['mean'] ?? null)
            || ! is_finite((float) $normalization['mean']) || ! is_numeric($normalization['scale'] ?? null)
            || ! is_finite((float) $normalization['scale']) || $normalization['scale'] <= 0)) {
            throw new RuntimeException('Stored cost target normalization is invalid.');
        }

        return compact('model', 'metadata');
    }

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
            try {
                $metadata['model_type'] = self::algorithm($model);
                $metadata['estimator_class'] = $model::class;
                $metadata['schema_version'] = $this->schemaVersion;
                (new ModelManager)->saveToFile($model, $modelCandidate);
                $metadata['model_sha256'] = hash_file('sha256', $modelCandidate);
                if (file_put_contents($metadataCandidate, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    throw new RuntimeException('Unable to save candidate metadata.');
                }
                $this->readPair($modelCandidate, $metadataCandidate);
                $validCurrent = false;
                try {
                    $this->readPair($this->path, $this->path.'.meta.json');
                    $validCurrent = true;
                } catch (Throwable) {
                    // A corrupt current pair must not overwrite a valid previous pair.
                }
                if ($validCurrent) {
                    if (! copy($this->path, $previousModelCandidate) || ! copy($this->path.'.meta.json', $previousMetadataCandidate)) {
                        throw new RuntimeException('Unable to preserve the previous cost model.');
                    }
                    $this->readPair($previousModelCandidate, $previousMetadataCandidate);
                    if (! rename($previousModelCandidate, $previousModel) || ! rename($previousMetadataCandidate, $previousMetadata)) {
                        throw new RuntimeException('Unable to publish the previous cost model backup.');
                    }
                }
                if ($hadModel && ! rename($this->path, $backupModel)) {
                    throw new RuntimeException('Unable to back up the active cost model.');
                }
                if ($hadMetadata && ! rename($this->path.'.meta.json', $backupMetadata)) {
                    if ($hadModel) {
                        rename($backupModel, $this->path);
                    }
                    throw new RuntimeException('Unable to back up active cost metadata.');
                }
                if (! rename($modelCandidate, $this->path) || ! rename($metadataCandidate, $this->path.'.meta.json')) {
                    File::delete([$this->path, $this->path.'.meta.json']);
                    if ($hadModel) {
                        rename($backupModel, $this->path);
                    }
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

    private function locked(int $mode, callable $operation): mixed
    {
        File::ensureDirectoryExists(dirname($this->path));
        $handle = fopen($this->path.'.store.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('Cost model store lock is unavailable.');
        }
        try {
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
