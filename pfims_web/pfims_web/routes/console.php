<?php

use App\Services\InventoryHistoryReconciler;
use App\Services\LegacyInventoryPriceBackfill;
use App\Services\MLService;
use App\Services\ProjectCostDataQualityService;
use App\Services\ProjectCostPresentationService;
use App\Services\ProjectCostSnapshotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('ml:presentation-records {--apply : Insert the verified batch atomically} {--count=48}', function (ProjectCostPresentationService $service) {
    $plan = $service->prepare((int) $this->option('count'));
    $report = $this->option('apply') ? $service->apply($plan) : [
        'mode' => 'preview_only', 'project_count' => count($plan['projects']),
        'outcome_counts' => array_count_values(array_column($plan['projects'], 'expected_outcome')),
        'plan_sha256' => hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR)),
        'database_changed' => false,
    ];
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Preview or explicitly import an internally traceable presentation batch without retraining');

Artisan::command('ml:evaluate {--cohort=auto : auto, planning, or presentation_progress} {--save-report : Save evidence separately without activating a model}', function () {
    $service = new MLService(null, false);
    $report = $service->evaluateCandidate((string) $this->option('cohort'));
    if ($this->option('save-report')) {
        $service->saveCandidateEvaluationReport($report);
    }
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Evaluate a candidate in memory without replacing the saved model or changing project records');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ml:audit-data', function (ProjectCostDataQualityService $quality) {
    $this->line(json_encode($quality->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Read-only project final-cost eligibility and exclusion audit; never retrains or changes records');

Artisan::command('ml:audit-snapshots', function () {
    $ml = new MLService(loadModel: false);
    $this->line(json_encode($ml->getSnapshotReadiness(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Report progress-stage coverage and the completed-project observations still needed');

Artisan::command('ml:training-readiness', function (ProjectCostDataQualityService $quality) {
    $this->line(json_encode($quality->trainingReadiness(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Audit genuine training outcomes and independent-test candidates without changing records or training');

Artisan::command('ml:retrain {--scheduled : Mark this run as scheduler-triggered} {--cohort= : Explicit training cohort policy}', function (MLService $ml) {
    $result = $ml->retrain($this->option('cohort') ?: null);
    $this->info($result['message']);
    $this->line('Model source: '.$result['model_source']);
    $this->line('Evaluation method: '.($result['metrics']['evaluation_method'] ?? 'unavailable'));
    $this->line('Samples trained: '.($result['metrics']['samples_trained'] ?? 0));

    return 0;
})->purpose('Retrain the project cost model on the newest verified completed projects');

Schedule::command('ml:retrain --scheduled')
    ->weeklyOn(1, '02:00')
    ->withoutOverlapping();

Artisan::command('ml:capture-weekly', function (ProjectCostSnapshotService $snapshots) {
    if (! Schema::hasTable('ml_project_cost_snapshots')) {
        $this->warn('Snapshot storage is unavailable.');

        return 1;
    }
    $weekStart = now()->startOfWeek()->toDateString();
    $captured = 0;
    $projects = DB::table('project_tbl as project')
        ->whereRaw("LOWER(COALESCE(project.status, '')) <> 'completed'")
        ->whereExists(function ($query) {
            $query->selectRaw('1')->from('budgets_tbl as budget')
                ->whereColumn('budget.project_id', 'project.project_id');
        })->pluck('project.project_id');
    foreach ($projects as $projectId) {
        $hasThisWeek = DB::table('ml_project_cost_snapshots')->where('project_id', $projectId)
            ->whereDate('captured_at', '>=', $weekStart)->exists();
        if (! $hasThisWeek && $snapshots->capture((int) $projectId, 'weekly_schedule')) {
            $captured++;
        }
    }
    $this->info("Captured {$captured} genuine weekly project snapshots.");

    return 0;
})->purpose('Capture current project states without reconstructing historical progress');

Schedule::command('ml:capture-weekly')->weeklyOn(1, '01:30')->withoutOverlapping();

Artisan::command('inventory:audit-history {--apply-safe : Classify only one-to-one proven receipts; never price or reassign ambiguous rows} {--allocate-safe : Replay FIFO for withdrawals with unambiguous earlier receipts}', function (InventoryHistoryReconciler $reconciler) {
    $report = $this->option('apply-safe') ? $reconciler->classifySafeReceipts() : $reconciler->report();
    if ($this->option('allocate-safe')) {
        $report['fifo_replay'] = $reconciler->allocateSafeWithdrawals();
    }
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $this->info($this->option('apply-safe') || $this->option('allocate-safe')
        ? 'Only explicitly selected safe reconciliation actions were applied; review every remaining ambiguity.'
        : 'Read-only audit; no records changed.');

    return 0;
})->purpose('Audit legacy inventory valuation and classify only explicitly approved safe receipts');

Artisan::command('inventory:price-legacy {--apply : Create linked purchase expenses from current item prices}', function (LegacyInventoryPriceBackfill $backfill) {
    if (! $this->option('apply')) {
        $candidates = $backfill->candidates();
        $this->line('Dry run: '.count($candidates).' unpriced legacy purchase receipts; '
            .collect($candidates)->filter(fn ($candidate) => $candidate['receipt']->project_id !== null)->count().' project-linked.');
        $this->warn('Amounts are estimates based on current item prices, not historical invoices. No records changed.');

        return 0;
    }

    $result = $backfill->apply();
    $this->info("Created {$result['storage_purchases']} storage and {$result['project_purchases']} project-linked purchase expenses.");
    $this->warn('These historical amounts are estimates based on current item prices.');

    return 0;
})->purpose('Backfill legacy purchase amounts from quantity times current item Unit Price');
