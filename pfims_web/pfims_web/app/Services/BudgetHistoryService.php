<?php

namespace App\Services;

use App\Models\Budget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BudgetHistoryService
{
    public function create(array $attributes, string $reason, bool $originalKnown = false): Budget
    {
        return DB::transaction(function () use ($attributes, $reason, $originalKnown) {
            $projectId = (int) $attributes['project_id'];
            DB::table('project_tbl')->where('project_id', $projectId)->lockForUpdate()->first();
            if (Budget::where('project_id', $projectId)->exists()) {
                throw new \DomainException('This project already has a budget.');
            }
            $hasHistory = Schema::hasTable('project_budget_history')
                && DB::table('project_budget_history')->where('project_id', $projectId)->exists();
            $budget = Budget::create($attributes);
            $this->append($budget, $originalKnown && ! $hasHistory ? 'initial_recorded' : 'observed_created', $reason);

            return $budget;
        });
    }

    public function revise(Budget $budget, array $attributes, string $reason): Budget
    {
        return DB::transaction(function () use ($budget, $attributes, $reason) {
            $projectId = (int) ($attributes['project_id'] ?? $budget->project_id);
            DB::table('project_tbl')->whereIn('project_id', [$budget->project_id, $projectId])
                ->orderBy('project_id')->lockForUpdate()->get();
            $current = Budget::whereKey($budget->budget_id)->lockForUpdate()->firstOrFail();
            if ((int) $current->project_id !== (int) $budget->project_id) {
                throw ValidationException::withMessages(['project_id' => 'This budget changed. Reload it before saving.']);
            }
            $this->ensureOpening($current);
            $moved = $projectId !== (int) $current->project_id;
            if ($moved) {
                if (Budget::where('project_id', $projectId)->whereKeyNot($current->budget_id)->exists()) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project already has a budget.']);
                }
                $cost = app(ProjectCostLedger::class)->forProject((int) $current->project_id);
                if ($cost['total'] > 0 || $cost['unvalued_count'] > 0) {
                    throw ValidationException::withMessages(['project_id' => 'A budget with recorded expenses cannot be moved to another project.']);
                }
                $this->append($current, 'reassigned_from', $reason, null);
                $attributes['actual_amount'] = app(ProjectCostLedger::class)->forProject($projectId)['total'];
            }
            $changed = array_key_exists('budget_amount', $attributes)
                && round((float) $attributes['budget_amount'], 2) !== round((float) $current->budget_amount, 2);
            $current->fill($attributes)->save();
            if ($moved || $changed) {
                $this->append($current, $moved ? 'reassigned_to' : 'revised', $reason);
            }
            $budget->setRawAttributes($current->getAttributes(), true);

            return $budget;
        });
    }

    public function remove(Budget $budget, string $reason): void
    {
        DB::transaction(function () use ($budget, $reason) {
            DB::table('project_tbl')->where('project_id', $budget->project_id)->lockForUpdate()->first();
            $current = Budget::whereKey($budget->budget_id)->lockForUpdate()->firstOrFail();
            if ((int) $current->project_id !== (int) $budget->project_id) {
                throw ValidationException::withMessages(['project_id' => 'This budget changed. Reload it before deleting.']);
            }
            $cost = app(ProjectCostLedger::class)->forProject((int) $current->project_id);
            if ($cost['total'] > 0 || $cost['unvalued_count'] > 0) {
                throw new \DomainException('A budget with recorded expenses cannot be deleted.');
            }
            $this->ensureOpening($current);
            $this->append($current, 'removed', $reason, null);
            $current->delete();
        });
    }

    public function context(int $projectId, ?float $amount = null, ?int $budgetId = null): array
    {
        $budget = $amount === null || $budgetId === null
            ? DB::table('budgets_tbl')->where('project_id', $projectId)->orderByDesc('budget_id')->first()
            : null;
        $amount ??= $budget ? (float) $budget->budget_amount : null;
        $budgetId ??= $budget ? (int) $budget->budget_id : null;
        $original = $version = null;
        if (Schema::hasTable('project_budget_history')) {
            $original = DB::table('project_budget_history')->where('project_id', $projectId)
                ->where('event_type', 'initial_recorded')->orderBy('history_id')->first();
            $version = DB::table('project_budget_history')->where('project_id', $projectId)
                ->where('budget_id', $budgetId)->orderByDesc('history_id')->first();
            // A direct database correction may not yet have a recorded version.
            if (! $version || $version->budget_amount === null || $amount === null
                || round((float) $version->budget_amount, 2) !== round($amount, 2)) {
                $version = null;
            }
        }
        $originalAmount = $original && (float) $original->budget_amount > 0 ? (float) $original->budget_amount : null;

        return [
            'budget_basis' => 'latest_recorded_budget',
            'current_budget_id' => $budgetId,
            'current_budget_amount' => $amount,
            'current_budget_version_id' => $version ? (int) $version->history_id : null,
            'budget_recorded_at' => $version?->recorded_at,
            'original_budget_amount' => $originalAmount,
            'original_budget_status' => $originalAmount === null ? 'unavailable' : 'initial_recorded',
        ];
    }

    private function ensureOpening(Budget $budget): void
    {
        if (! Schema::hasTable('project_budget_history')) {
            return;
        }
        $latest = DB::table('project_budget_history')->where('project_id', $budget->project_id)
            ->where('budget_id', $budget->budget_id)->orderByDesc('history_id')->first();
        if (! $latest) {
            $this->append($budget, 'opening_observed', 'Existing budget first observed; original approval date and amount are unknown.');
        } elseif ($latest->budget_amount === null || round((float) $latest->budget_amount, 2) !== round((float) $budget->budget_amount, 2)) {
            $this->append($budget, 'observed_correction', 'A budget change outside the application was observed; its effective date is unknown.');
        }
    }

    private function append(Budget $budget, string $event, string $reason, mixed ...$amountOverride): void
    {
        if (! Schema::hasTable('project_budget_history')) {
            return;
        }
        $recordedAt = now();
        DB::table('project_budget_history')->insert([
            'project_id' => $budget->project_id,
            'budget_id' => $budget->budget_id,
            'budget_amount' => $amountOverride === [] ? $budget->budget_amount : $amountOverride[0],
            'event_type' => $event,
            'effective_at' => in_array($event, ['opening_observed', 'observed_correction'], true) ? null : $recordedAt,
            'recorded_at' => $recordedAt,
            'reason' => mb_substr($reason, 0, 500),
            'recorded_by' => auth()->id(),
        ]);
    }
}
