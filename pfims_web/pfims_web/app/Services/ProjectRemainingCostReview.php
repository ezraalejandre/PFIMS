<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Immutable current-time estimates. Recorded spending is never counted as remaining work. */
class ProjectRemainingCostReview
{
    public function normalize(array $input): array
    {
        $data = Validator::make($input, [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.kind' => ['required', Rule::in(['committed', 'uncommitted'])],
            'items.*.resource' => ['required', Rule::in(['material', 'labor', 'equipment', 'subcontract', 'other'])],
            'items.*.remaining_quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.reference' => ['nullable', 'string', 'max:100'],
            'items.*.variation_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'excludes_recorded_costs' => ['required', 'accepted'],
        ])->validate();
        $seen = []; $committed = $uncommitted = 0;
        foreach ($data['items'] as $index => &$item) {
            if ($item['kind'] === 'committed' && empty(trim($item['reference'] ?? ''))) {
                throw ValidationException::withMessages(["items.$index.reference" => 'Enter the purchase order or contract reference for committed work.']);
            }
            $identity = mb_strtolower(trim($item['description']).'|'.trim($item['reference'] ?? ''));
            if (isset($seen[$identity])) {
                throw ValidationException::withMessages(["items.$index.description" => 'This work item is already included in the review.']);
            }
            $seen[$identity] = true;
            $item['amount'] = round((float) $item['remaining_quantity'] * (float) $item['unit_cost'], 2);
            if (! is_finite($item['amount']) || $item['amount'] > 9999999999.99) {
                throw ValidationException::withMessages(["items.$index.unit_cost" => 'The remaining cost is too large.']);
            }
            if ($item['kind'] === 'committed') $committed += $item['amount']; else $uncommitted += $item['amount'];
        }
        unset($item);
        if ($committed + $uncommitted > 9999999999.99) {
            throw ValidationException::withMessages(['items' => 'The total remaining cost is too large.']);
        }
        return ['items' => $data['items'], 'committed_remaining' => round($committed, 2),
            'uncommitted_remaining' => round($uncommitted, 2), 'notes' => $data['notes'] ?? null];
    }

    public function save(int $projectId, array $input, ?int $userId): array
    {
        $data = $this->normalize($input);
        return DB::transaction(function () use ($projectId, $data, $userId) {
            $project = DB::table('project_tbl')->where('project_id', $projectId)->lockForUpdate()->first();
            if (! $project || strtolower($project->status) === 'completed') {
                throw ValidationException::withMessages(['project_id' => 'Select an incomplete project.']);
            }
            $budget = DB::table('budgets_tbl')->where('project_id', $projectId)->orderByDesc('budget_id')->lockForUpdate()->first();
            $cost = app(ProjectCostLedger::class)->forProject($projectId);
            if (! $budget || $budget->budget_amount <= 0 || $cost['unvalued_count'] > 0) {
                throw ValidationException::withMessages(['project_id' => 'A positive budget and complete material valuation are required before recording this review.']);
            }
            $start = \Carbon\Carbon::parse($project->start_date);
            $end = \Carbon\Carbon::parse($project->estimated_end_date);
            if (! $project->start_date || ! $project->estimated_end_date || $end->lte($start)) {
                throw ValidationException::withMessages(['project_id' => 'A valid planned start and completion date are required.']);
            }
            $inputs = app(\App\Services\ML\PlanningSpendingCohort::class)->forecastInputs($projectId, $start, $end);
            if (! $inputs['cost_coverage_complete'] || abs((float) $inputs['fin_total_expense'] - (float) $cost['total']) > .01) {
                throw ValidationException::withMessages(['project_id' => 'Reconcile future-dated costs and material valuation before recording a dated review.']);
            }
            $inputs['budget'] = (float) $budget->budget_amount;
            $inputs['worker_count'] = (int) $project->worker_count;
            $inputs['duration_months'] = max(1, (int) $start->diffInMonths($end));
            $inputs = array_replace($inputs, \App\Services\ML\PlanningSpendingFeatures::build($inputs));
            DB::table('project_remaining_cost_reviews')->insert(['project_id' => $projectId,
                'recorded_at' => now(), 'recorded_by' => $userId, 'recorded_spend' => $cost['total'],
                'budget_amount' => $budget->budget_amount, 'committed_remaining' => $data['committed_remaining'],
                'uncommitted_remaining' => $data['uncommitted_remaining'], 'items_json' => json_encode($data['items'], JSON_THROW_ON_ERROR),
                'planning_inputs_json' => json_encode($inputs, JSON_THROW_ON_ERROR), 'phase' => $project->phase,
                'completion_percentage' => $project->completion_percentage,
                'notes' => $data['notes']]);
            return $this->latest($projectId);
        });
    }

    public function latest(int $projectId): ?array
    {
        if (! Schema::hasTable('project_remaining_cost_reviews')) return null;
        $row = DB::table('project_remaining_cost_reviews')->where('project_id', $projectId)->orderByDesc('review_id')->first();
        if (! $row) return null;
        $cost = app(ProjectCostLedger::class)->forProject($projectId);
        $budget = (float) DB::table('budgets_tbl')->where('project_id', $projectId)->orderByDesc('budget_id')->value('budget_amount');
        $changed = abs($cost['total'] - (float) $row->recorded_spend) > .01
            || abs($budget - (float) $row->budget_amount) > .01 || $cost['unvalued_count'] > 0;
        $old = \Carbon\Carbon::parse($row->recorded_at)->lt(now()->subDays(14));
        return ['review_id' => $row->review_id, 'project_id' => $projectId, 'recorded_at' => $row->recorded_at,
            'recorded_spend' => (float) $row->recorded_spend, 'committed_remaining' => (float) $row->committed_remaining,
            'uncommitted_remaining' => (float) $row->uncommitted_remaining, 'budget_amount' => (float) $row->budget_amount,
            'items' => json_decode($row->items_json, true, 512, JSON_THROW_ON_ERROR), 'notes' => $row->notes,
            'needs_refresh' => $changed || $old,
            'review_final_cost' => $changed || $old ? null : round($row->recorded_spend + $row->committed_remaining + $row->uncommitted_remaining, 2),
            'basis' => 'Recorded spending plus reviewed unpaid commitments plus other remaining work. A management estimate, separate from ML performance.'];
    }
}
