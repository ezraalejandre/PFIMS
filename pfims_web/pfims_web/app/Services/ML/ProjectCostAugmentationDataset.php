<?php

namespace App\Services\ML;

use Illuminate\Support\Collection;
use RuntimeException;

/** Load extra training data from files. Leave business data and test records unchanged. */
class ProjectCostAugmentationDataset
{
    private array $manifest;

    private Collection $rows;

    // Set up the values this class needs.
    public function __construct(private string $directory, private ?float $dummyToRealRatio = null)
    {
        // Stop if the dummy to real ratio checks match.
        if ($dummyToRealRatio !== null && (! is_finite($dummyToRealRatio) || $dummyToRealRatio < 0)) {
            throw new RuntimeException('Ingested-data influence must be a finite nonnegative ratio.');
        }
        // Stop if the directory file is missing.
        if (! is_file($directory.'/manifest.json')) {
            throw new RuntimeException('Augmentation dataset is unavailable. Generate a verified live-source dataset first.');
        }
        $this->manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        // Reject invalid augmentation partition manifest.
        if (($this->manifest['schema_version'] ?? null) !== 1
            || array_intersect($this->manifest['database_training_project_ids'], $this->manifest['database_test_project_ids']) !== []) {
            throw new RuntimeException('Invalid augmentation partition manifest.');
        }
        // Process each name.
        foreach (['projects.jsonl.gz', 'expenses.jsonl.gz', 'inventory-transactions.jsonl.gz', 'items.jsonl.gz', 'training-observations.jsonl.gz'] as $name) {
            // Stop if the directory and name checks match.
            if (! is_file($directory.'/'.$name) || ! hash_equals($this->manifest['files'][$name]['sha256'] ?? '', hash_file('sha256', $directory.'/'.$name))) {
                throw new RuntimeException('Augmentation file is missing or changed: '.$name);
            }
        }
        $this->rows = collect(iterator_to_array(self::read($directory.'/training-observations.jsonl.gz')))->map(fn ($row) => (object) $row);
        $allowed = array_fill_keys(array_map('strval', $this->manifest['database_training_project_ids']), true);
        // Process each row.
        foreach ($this->rows as $row) {
            // Stop if the donor project id and project id checks match.
            if (! isset($allowed[(string) $row->donor_project_id]) || ! str_starts_with((string) $row->project_id, $this->manifest['project_id_prefix'] ?? 'dummy-')) {
                throw new RuntimeException('Ingested-data lineage includes a non-training project.');
            }
        }
        // Stop if the count does not equal manifest dummy projects.
        if ($this->rows->pluck('project_id')->unique()->count() !== $this->manifest['dummy_projects']) {
            throw new RuntimeException('Ingested-data observation coverage does not match the manifest.');
        }
    }

    // Get the split.
    public static function split(Collection $records, float $testRatio = .20): array
    {
        $ids = $records->sortBy([['completed_at', 'asc'], ['project_id', 'asc']])->pluck('project_id')->unique()->values();
        // Stop if the count is below 3.
        if ($ids->count() < 3) {
            throw new RuntimeException('At least three eligible completed database projects are required.');
        }
        $test = $ids->slice($ids->count() - (int) ceil($ids->count() * $testRatio))->values()->all();

        return ['training' => $ids->reject(fn ($id) => in_array($id, $test))->values()->all(), 'test' => $test];
    }

    // Get the fingerprint.
    public static function fingerprint(Collection $records): string
    {
        // Build a result for each record.
        $canonical = $records->map(function ($row) {
            $row = (array) $row;
            ksort($row);

            return $row;
        })->sortBy(fn ($row) => sprintf('%012d', $row['project_id']).'|'.($row['snapshot_id'] ?? ''))->values()->all();

        // JSON exports can turn 10.0 into 10; that does not represent a data change.
        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
    }

    // Check database matches.
    public function assertDatabaseMatches(Collection $records): void
    {
        $split = self::split($records, (float) ($this->manifest['database_test_ratio'] ?? .20));
        // Stop if the split training and split test checks match.
        if ($split['training'] !== $this->manifest['database_training_project_ids']
            || $split['test'] !== $this->manifest['database_test_project_ids']
            || ! hash_equals($this->manifest['database_fingerprint'], self::fingerprint($records))) {
            throw new RuntimeException('Database training records changed. Regenerate the augmentation dataset before retraining.');
        }
    }

    /** Use only source projects in the current training group when choosing inputs and risk settings. */
    public function augment(Collection $records): Collection
    {
        // Return early if the is empty check matches.
        if ($records->isEmpty()) {
            return $records;
        }
        $ids = $records->pluck('project_id')->map('strval')->unique()->all();
        // Stop if the manifest database training project ids is not empty.
        if (array_diff($ids, array_map('strval', $this->manifest['database_training_project_ids'])) !== []) {
            throw new RuntimeException('Only database training projects may be augmented.');
        }
        $dummy = $this->rows->filter(fn ($row) => in_array((string) $row->donor_project_id, $ids, true))->values();
        // Run this step if the snapshot id check matches.
        if (! $records->contains(fn ($row) => isset($row->snapshot_id))) {
            // Build a result for each record.
            $dummy = $dummy->groupBy('project_id')->map(function ($rows) {
                $row = clone $rows->last();
                // Run this step if the snapshot id is set.
                if (isset($row->snapshot_id)) {
                    $row->actual_cost = $row->reconciled_final_cost;
                    unset($row->snapshot_id);
                }

                return $row;
            })->values();
        }

        // Run this step if the dummy to real ratio is not null.
        if ($this->dummyToRealRatio !== null) {
            // Stable, donor-balanced project sampling. Keep every stage of a chosen
            // project together; no validation donor can enter this pool.
            $groups = $dummy->groupBy('donor_project_id')->map(fn ($rows) => $rows->pluck('project_id')->unique()
                ->sortBy(fn ($id) => hash('sha256', 'augmentation-influence-v1|'.$id))->values()->all());
            $chosen = [];
            $limit = (int) floor($records->pluck('project_id')->unique()->count() * $this->dummyToRealRatio);
            $round = 0;
            do {
                $added = false;
                // Process each ids.
                foreach ($groups as $ids) {
                    // Run this step if the number of chosen is at least limit.
                    if (count($chosen) >= $limit) {
                        break;
                    }
                    // Run this step if the round is set.
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

    // Get the summary.
    public function summary(): array
    {
        return array_diff_key($this->manifest, ['files' => true, 'database_fingerprint' => true])
            + ['dummy_to_real_project_ratio' => $this->dummyToRealRatio, 'influence_method' => 'deterministic_donor_balanced_project_subsampling'];
    }

    // Get the read.
    public static function read(string $path): \Generator
    {
        $handle = gzopen($path, 'rb');
        // Stop if the handle is false.
        if ($handle === false) {
            throw new RuntimeException('Unable to read dataset.');
        }
        // Try this action and handle any errors below.
        try {
            // Repeat while the handle is not false.
            while (($line = gzgets($handle)) !== false) {
                yield json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            gzclose($handle);
        }
    }
}
