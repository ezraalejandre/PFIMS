<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutomaticModelRetraining
{
    /** Queue one retraining pass after the response and coalesce rapid data writes. */
    public function afterDataChange(array|int|null $projectIds = null): void
    {
        $ids = collect(is_array($projectIds) ? $projectIds : [$projectIds])
            ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0)
            ->map(fn ($id) => (int) $id)->unique()->values();

        foreach ($ids as $projectId) {
            app(ProjectCostSnapshotService::class)->captureAfterCommit($projectId, 'data_change');
        }

        if (! Cache::add('pfims:ml-retrain-pending', true, now()->addSeconds(30))) {
            return;
        }

        defer(function (): void {
            try {
                app(MLService::class)->retrain();
            } catch (Throwable $exception) {
                Log::error('Automatic model retraining failed.', ['message' => $exception->getMessage()]);
            } finally {
                Cache::forget('pfims:ml-retrain-pending');
            }
        })->name('pfims-automatic-model-retraining');
    }
}
