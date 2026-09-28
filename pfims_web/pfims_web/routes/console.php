<?php

use App\Services\InventoryHistoryReconciler;
use App\Services\MLService;
use App\Services\ProjectCostSnapshotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ml:retrain {--scheduled : Mark this run as scheduler-triggered}', function (MLService $ml) {
    $result = $ml->retrain();
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
