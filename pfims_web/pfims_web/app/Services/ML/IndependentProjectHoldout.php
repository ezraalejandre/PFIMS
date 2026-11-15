<?php

namespace App\Services\ML;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use RuntimeException;

/** Reserve incomplete projects prospectively; never relabel inspected completed data as fresh. */
class IndependentProjectHoldout
{
    public static function ids(): array
    {
        return Schema::hasTable('ml_validation_reservations')
            ? DB::table('ml_validation_reservations')->pluck('project_id')->map(fn ($id) => (int) $id)->all() : [];
    }

    public function reserve(int $projectId): array
    {
        LocalImprovementWorkbench::assertLocal();
        return DB::transaction(function () use ($projectId) {
            $project = DB::table('project_tbl')->where('project_id', $projectId)->lockForUpdate()->first();
            if (! $project || strtolower($project->status) === 'completed' || $project->actual_end_date !== null
                || ($project->data_source ?? 'operational') !== 'operational') {
                throw new RuntimeException('Reserve an incomplete operational project before its outcome is known.');
            }
            foreach (File::glob(storage_path('app/ml_model*.json*')) as $path) {
                $report = json_decode(File::get($path), true);
                $seen = false;
                $walk = function ($value, $key = '') use (&$walk, &$seen, $projectId) {
                    if (! is_array($value)) return;
                    if (str_ends_with($key, 'project_ids') && in_array($projectId, array_map(fn ($id) => is_scalar($id) ? (int) $id : -1, $value), true)) $seen = true;
                    foreach ($value as $k => $v) if (is_array($v)) $walk($v, (string) $k);
                };
                $walk($report);
                if ($seen) throw new RuntimeException('This project appears in saved development evidence and cannot be reserved as fresh.');
            }
            DB::table('ml_validation_reservations')->insertOrIgnore(['project_id' => $projectId,
                'reserved_at' => now(), 'purpose' => 'Prospective independent evaluation; excluded from training and tuning.']);
            return ['project_id' => $projectId, 'reserved' => true, 'fresh_outcome_available' => false];
        });
    }

    public function reserveProspective(): array
    {
        LocalImprovementWorkbench::assertLocal();
        $candidates = DB::table('project_tbl')->where('data_source', 'operational')
            ->where('status', '<>', 'Completed')->whereNull('actual_end_date')->orderBy('project_id')->pluck('project_id');
        $count = (int) ceil($candidates->count() * .2);
        $chosen = $candidates->sortBy(fn ($id) => hash('sha256', 'prospective-validation-20261115-'.$id))->take($count);
        return $chosen->map(fn ($id) => $this->reserve((int) $id))->values()->all();
    }
}
