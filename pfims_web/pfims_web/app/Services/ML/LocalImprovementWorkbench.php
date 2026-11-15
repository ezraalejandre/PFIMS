<?php

namespace App\Services\ML;

use App\Services\ProjectCostDataQualityService;
use App\Services\ProjectCostLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class LocalImprovementWorkbench
{
    public static function assertLocal(): void
    {
        $host = DB::connection()->getConfig('host');
        if (DB::connection()->getDriverName() !== 'sqlite' && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('This workbench only operates on a local database connection.');
        }
    }

    public function prepare(bool $repair = false): array
    {
        self::assertLocal();
        $directory = storage_path('app/ml-improvement');
        File::ensureDirectoryExists($directory);
        $audit = app(ProjectCostDataQualityService::class)->report();
        $repairs = [];
        foreach ($audit['projects'] as $project) {
            if ($repair && $project['errors'] === ['budget_actual_does_not_match_ledger']
                && ! in_array('estimated_historical_prices', $project['warnings'], true)) {
                DB::transaction(function () use ($project, &$repairs) {
                    DB::table('project_tbl')->where('project_id', $project['project_id'])->lockForUpdate()->first();
                    $rechecked = app(ProjectCostDataQualityService::class)->inspect($project['project_id']);
                    if ($rechecked['errors'] !== ['budget_actual_does_not_match_ledger']) return;
                    app(ProjectCostLedger::class)->syncBudget($project['project_id']);
                    $repairs[] = $project['project_id'];
                });
            }
        }
        $cohort = app(PlanningSpendingCohort::class)->database();
        $reserved = IndependentProjectHoldout::ids();
        $records = $cohort['records']->reject(fn ($row) => in_array((int) $row->project_id, $reserved, true))->values();
        PlanningSpendingFeatures::validate(array_replace($cohort, ['records' => $records]));
        $split = ProjectCostAugmentationDataset::split($records);
        $test = $records->whereIn('project_id', $split['test'])->values();
        $baseline = (new class(loadModel: false) extends \App\Services\MLService {
            public function score($rows): array
            {
                $result = $this->evaluateSavedActiveEstimator($rows);
                $stored = app(ModelPromotionService::class)->loadDetector($this->modelPath.'.detector.json');
                if ($stored !== null) {
                    $outcomes = $this->classifierOutcomes($stored['model'], $rows, $stored['metadata']['feature_names'], $stored['metadata']['threshold']);
                    $result['independent_classifier'] = app(\App\Services\ProjectOverrunEvaluation::class)->evaluateClasses($outcomes['predictions'], $outcomes['actuals']);
                }
                return $result;
            }
        });
        $payload = ['generated_at' => now()->toIso8601String(), 'source' => 'local_database',
            'features' => PlanningSpendingFeatures::FEATURES, 'records' => $records->all(),
            'data_audit' => $cohort['snapshot_readiness'], 'reserved_project_ids' => $reserved,
            'active_baseline_all_checkpoints' => $baseline->score($test),
            'active_baseline_latest_checkpoint' => $baseline->score($test->sortBy('captured_at')->groupBy('project_id')->map(fn ($rows) => $rows->last())->values()),
            'repaired_budget_cache_project_ids' => $repairs,
            'quality_summary' => array_diff_key($audit, ['projects' => true]),
            'remaining_cost_review_count' => DB::getSchemaBuilder()->hasTable('project_remaining_cost_reviews')
                ? DB::table('project_remaining_cost_reviews')->count() : 0];
        File::put($directory.'/source.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::put($directory.'/data-quality.json', json_encode($audit, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::put($directory.'/remaining-work-cohort.json', json_encode(app(RemainingWorkReviewCohort::class)->build(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return ['source_file' => $directory.'/source.json', 'projects' => $records->pluck('project_id')->unique()->count(),
            'observations' => $records->count(), 'repairs' => $repairs, 'quality_summary' => $payload['quality_summary']];
    }
}
