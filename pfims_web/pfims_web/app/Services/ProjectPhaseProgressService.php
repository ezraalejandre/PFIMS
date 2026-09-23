<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectPhaseProgressService
{
    public function phases(): Collection
    {
        return DB::table('project_phase_tbl')
            ->orderBy('stage_order')
            ->orderBy('phase_id')
            ->get(['phase_id', 'phase_name', 'stage_order']);
    }

    public function firstPhaseName(): ?string
    {
        return $this->phases()->first()?->phase_name;
    }

    public function canonicalPhaseName(string $phaseName): ?string
    {
        return $this->phases()->first(
            fn ($item) => mb_strtolower(trim((string) $item->phase_name)) === mb_strtolower(trim($phaseName))
        )?->phase_name;
    }

    public function completionForPhase(string $phaseName): float
    {
        $phases = $this->phases();
        $phase = $phases->first(
            fn ($item) => mb_strtolower(trim((string) $item->phase_name)) === mb_strtolower(trim($phaseName))
        );

        if (! $phase || $phases->isEmpty()) {
            return 0.0;
        }

        return round(((int) $phase->stage_order / $phases->count()) * 100, 2);
    }

    public function syncAllProjects(): void
    {
        $phases = $this->phases();
        $total = $phases->count();
        if ($total === 0) {
            DB::table('project_tbl')->update(['completion_percentage' => 0]);
            return;
        }

        foreach ($phases as $phase) {
            DB::table('project_tbl')
                ->whereRaw('LOWER(TRIM(phase)) = ?', [mb_strtolower(trim((string) $phase->phase_name))])
                ->update(['completion_percentage' => round(((int) $phase->stage_order / $total) * 100, 2)]);
        }
    }

    public function normalizeStageOrder(): void
    {
        $this->phases()->values()->each(function ($phase, int $index) {
            DB::table('project_phase_tbl')->where('phase_id', $phase->phase_id)
                ->update(['stage_order' => $index + 1]);
        });
    }
}
