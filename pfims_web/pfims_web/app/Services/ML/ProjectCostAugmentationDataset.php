<?php

namespace App\Services\ML;

use Illuminate\Support\Collection;
use RuntimeException;

/** File-backed training augmentation. Business tables and test records are never modified. */
class ProjectCostAugmentationDataset
{
    private array $manifest;

    private Collection $rows;

    public function __construct(private string $directory, private ?float $dummyToRealRatio = null)
    {
        if ($dummyToRealRatio !== null && (! is_finite($dummyToRealRatio) || $dummyToRealRatio < 0)) {
            throw new RuntimeException('Dummy influence must be a finite nonnegative ratio.');
        }
        if (! is_file($directory.'/manifest.json')) {
            throw new RuntimeException('Augmentation dataset is unavailable. Generate a verified live-source dataset first.');
        }
        $this->manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($this->manifest['schema_version'] ?? null) !== 1
            || array_intersect($this->manifest['database_training_project_ids'], $this->manifest['database_test_project_ids']) !== []) {
            throw new RuntimeException('Invalid augmentation partition manifest.');
        }
        foreach (['projects.jsonl.gz', 'expenses.jsonl.gz', 'inventory-transactions.jsonl.gz', 'items.jsonl.gz', 'training-observations.jsonl.gz'] as $name) {
            if (! is_file($directory.'/'.$name) || ! hash_equals($this->manifest['files'][$name]['sha256'] ?? '', hash_file('sha256', $directory.'/'.$name))) {
                throw new RuntimeException('Augmentation file is missing or changed: '.$name);
            }
        }
        $this->rows = collect(iterator_to_array(self::read($directory.'/training-observations.jsonl.gz')))->map(fn ($row) => (object) $row);
        $allowed = array_fill_keys(array_map('strval', $this->manifest['database_training_project_ids']), true);
        foreach ($this->rows as $row) {
            if (! isset($allowed[(string) $row->donor_project_id]) || ! str_starts_with((string) $row->project_id, 'dummy-')) {
                throw new RuntimeException('Dummy lineage includes a non-training project.');
            }
        }
        if ($this->rows->pluck('project_id')->unique()->count() !== $this->manifest['dummy_projects']) {
            throw new RuntimeException('Dummy observation coverage does not match the manifest.');
        }
    }

    public static function split(Collection $records): array
    {
        $ids = $records->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])->pluck('project_id')->unique()->values();
        if ($ids->count() < 3) {
            throw new RuntimeException('At least three eligible completed database projects are required.');
        }
        $test = $ids->slice($ids->count() - (int) ceil($ids->count() * 0.2))->values()->all();

        return ['training' => $ids->reject(fn ($id) => in_array($id, $test))->values()->all(), 'test' => $test];
    }

    public static function fingerprint(Collection $records): string
    {
        $canonical = $records->map(function ($row) {
            $row = (array) $row;
            ksort($row);

            return $row;
        })->sortBy(fn ($row) => sprintf('%012d', $row['project_id']).'|'.($row['snapshot_id'] ?? ''))->values()->all();

        // JSON exports can turn 10.0 into 10; that does not represent a data change.
        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    public function assertDatabaseMatches(Collection $records): void
    {
        $split = self::split($records);
        if ($split['training'] !== $this->manifest['database_training_project_ids']
            || $split['test'] !== $this->manifest['database_test_project_ids']
            || ! hash_equals($this->manifest['database_fingerprint'], self::fingerprint($records))) {
            throw new RuntimeException('Database training records changed. Regenerate the augmentation dataset before retraining.');
        }
    }

    /** Fold-specific donor filtering also protects feature selection and classifier tuning. */
    public function augment(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }
        $ids = $records->pluck('project_id')->map('strval')->unique()->all();
        if (array_diff($ids, array_map('strval', $this->manifest['database_training_project_ids'])) !== []) {
            throw new RuntimeException('Only database training projects may be augmented.');
        }
        $dummy = $this->rows->filter(fn ($row) => in_array((string) $row->donor_project_id, $ids, true))->values();
        if (! $records->contains(fn ($row) => isset($row->snapshot_id))) {
            $dummy = $dummy->groupBy('project_id')->map(function ($rows) {
                $row = clone $rows->last();
                if (isset($row->snapshot_id)) {
                    $row->actual_cost = $row->reconciled_final_cost;
                    unset($row->snapshot_id);
                }

                return $row;
            })->values();
        }

        if ($this->dummyToRealRatio !== null) {
            // Stable, donor-balanced project subsampling. Keep every stage of a chosen
            // project together; no validation donor can enter this pool.
            $groups = $dummy->groupBy('donor_project_id')->map(fn ($rows) => $rows->pluck('project_id')->unique()
                ->sortBy(fn ($id) => hash('sha256', 'augmentation-influence-v1|'.$id))->values()->all());
            $chosen = [];
            $limit = (int) floor($records->pluck('project_id')->unique()->count() * $this->dummyToRealRatio);
            $round = 0;
            do {
                $added = false;
                foreach ($groups as $ids) {
                    if (count($chosen) >= $limit) {
                        break;
                    }
                    if (isset($ids[$round])) {
                        $chosen[] = $ids[$round];
                        $added = true;
                    }
                }
                $round++;
            } while ($added && count($chosen) < $limit);
            $dummy = $dummy->whereIn('project_id', $chosen)->values();
        }

        return $records->concat($dummy)->values();
    }

    public function summary(): array
    {
        return array_diff_key($this->manifest, ['files' => true, 'database_fingerprint' => true])
            + ['dummy_to_real_project_ratio' => $this->dummyToRealRatio, 'influence_method' => 'deterministic_donor_balanced_project_subsampling'];
    }

    public static function read(string $path): \Generator
    {
        $handle = gzopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read dataset.');
        }
        try {
            while (($line = gzgets($handle)) !== false) {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            gzclose($handle);
        }
    }
}
