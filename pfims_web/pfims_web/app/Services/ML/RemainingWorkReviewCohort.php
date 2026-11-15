<?php

namespace App\Services\ML;

use App\Services\ProjectCostDataQualityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Join genuine dated reviews to reconciled outcomes only after project completion. */
class RemainingWorkReviewCohort
{
    public function build(): array
    {
        $resources = ['material', 'labor', 'equipment', 'subcontract', 'other'];
        $features = [...PlanningSpendingFeatures::FEATURES, 'committed_remaining', 'uncommitted_remaining',
            ...array_map(fn ($resource) => 'reviewed_'.$resource.'_remaining', $resources)];
        $records = []; $excluded = [];
        if (! Schema::hasTable('project_remaining_cost_reviews')) return ['feature_names' => $features, 'records' => [], 'excluded' => []];
        $reserved = IndependentProjectHoldout::ids();
        $reviews = DB::table('project_remaining_cost_reviews as r')->join('project_tbl as p', 'p.project_id', '=', 'r.project_id')
            ->where('p.status', 'Completed')->whereNotIn('p.project_id', $reserved)
            ->select('r.*', 'p.actual_end_date', 'p.data_source')->orderBy('r.recorded_at')->get();
        $quality = [];
        foreach ($reviews as $review) {
            $quality[$review->project_id] ??= app(ProjectCostDataQualityService::class)->inspect($review->project_id);
            $outcome = $quality[$review->project_id];
            $input = json_decode($review->planning_inputs_json ?? '{}', true);
            if (! $outcome['eligible'] || substr($review->recorded_at, 0, 10) >= $review->actual_end_date
                || array_diff(PlanningSpendingFeatures::FEATURES, array_keys($input ?? [])) !== []
                || (float) $outcome['final_cost'] < (float) $review->recorded_spend) {
                $excluded[] = $review->review_id;
                continue;
            }
            $totals = array_fill_keys(array_map(fn ($resource) => 'reviewed_'.$resource.'_remaining', $resources), 0.0);
            foreach (json_decode($review->items_json, true, 512, JSON_THROW_ON_ERROR) as $item) {
                $key = 'reviewed_'.$item['resource'].'_remaining';
                if (isset($totals[$key])) $totals[$key] += (float) $item['amount'];
            }
            $records[] = array_replace($input, $totals, ['project_id' => $review->project_id, 'snapshot_id' => 'remaining-review-'.$review->review_id,
                'captured_at' => $review->recorded_at, 'completed_at' => $review->actual_end_date,
                'actual_cost' => round($outcome['final_cost'] - $review->recorded_spend, 2),
                'fin_total_expense' => (float) $review->recorded_spend, 'budget' => (float) $review->budget_amount,
                'committed_remaining' => (float) $review->committed_remaining, 'uncommitted_remaining' => (float) $review->uncommitted_remaining,
                'data_source' => $review->data_source, 'budget_basis' => 'contemporaneous_remaining_work_review']);
        }
        return ['feature_names' => $features, 'records' => $records, 'excluded_review_ids' => $excluded,
            'note' => 'Genuine dated review inputs with finalized reconciled labels; reserved independent projects excluded.'];
    }
}
