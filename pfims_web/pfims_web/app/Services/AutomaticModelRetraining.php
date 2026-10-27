<?php

namespace App\Services;


class AutomaticModelRetraining
{
    /** Preserve observations on writes; model training runs on the weekly schedule. */
    public function afterDataChange(array|int|null $projectIds = null): void
    {
        $ids = collect(is_array($projectIds) ? $projectIds : [$projectIds])
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id) => (int) $id)->unique()->values();

        foreach ($ids as $projectId) {
            app(ProjectCostSnapshotService::class)->captureAfterCommit($projectId, 'data_change');
        }

    }
}
