<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

// Group the project cost presentation actions.
class ProjectCostPresentationService
{
    public const BATCH_KEY = 'cost-overrun-presentation-20261002-v1';

    public const KEYS = [
        'inventory_item_tbl' => 'item_id',
        'project_tbl' => 'project_id', 'budgets_tbl' => 'budget_id',
        'project_budget_history' => 'history_id', 'fin_expense_tbl' => 'fin_expense_id',
        'inventory_transaction_tbl' => 'inventory_transaction_id',
        'inventory_cost_allocation_tbl' => 'allocation_id', 'ml_project_cost_snapshots' => 'snapshot_id',
    ];

    // Get the prepare.
    public function prepare(int $count = 48): array
    {
        $references = DB::table('project_tbl as project')->join('budgets_tbl as budget', 'budget.project_id', '=', 'project.project_id')
            ->where('project.status', 'Completed')->where('project.data_source', 'operational')
            ->orderBy('project.project_id')->get(['project.*', 'budget.budget_amount'])
            ->filter(fn ($project) => app(ProjectCostDataQualityService::class)->inspect((int) $project->project_id)['eligible'])
            ->map(fn ($project) => (array) $project)->values()->all();
        $items = DB::table('inventory_item_tbl')->where('unit_price', '>', 0)->where('unit_price', '<=', 1450)
            ->whereIn('inventory_category_id', DB::table('inventory_item_tbl')->where('item_name', 'like', '%Cement%')->pluck('inventory_category_id'))
            ->where('item_name', 'not like', '% - Site Supply')
            ->orderBy('item_id')->limit(12)->get(['item_id', 'item_name', 'unit_price', 'inventory_category_id', 'supplier_id', 'unit_id'])
            ->map(fn ($item) => (array) $item)->all();

        return app(ProjectCostPresentationPlan::class)->build($references, $items, $count);
    }

    // Get the apply.
    public function apply(array $plan, string $batchKey = self::BATCH_KEY): array
    {
        // Stop if the plan source details and plan projects checks match.
        if (! Schema::hasTable('ml_presentation_batches') || ($plan['provenance'] ?? '') !== 'company_inspired_sample'
            || empty($plan['projects']) || strlen($batchKey) > 80) {
            throw new RuntimeException('Presentation provenance, non-empty plan and batch tracking are required.');
        }
        $hash = hash('sha256', json_encode($plan, JSON_THROW_ON_ERROR));
        $categories = $this->categories();

        // Run these database steps together. Undo changes if a step fails.
        return DB::transaction(function () use ($plan, $batchKey, $hash, $categories) {
            $existing = DB::table('ml_presentation_batches')->where('batch_key', $batchKey)->lockForUpdate()->first();
            // Run this step if the existing is set.
            if ($existing) {
                // Stop if the plan sha256 does not equal hash.
                if ($existing->plan_sha256 !== $hash) {
                    throw new RuntimeException('This batch key already belongs to a different plan.');
                }
                $manifest = json_decode($existing->manifest, true, 512, JSON_THROW_ON_ERROR);
                $this->verifyManifest($manifest);

                return $manifest + ['already_applied' => true];
            }
            // The unique batch key also prevents two concurrent imports committing duplicates.
            DB::table('ml_presentation_batches')->insert(['batch_key' => $batchKey, 'plan_sha256' => $hash,
                'manifest' => '{}', 'created_at' => now()]);
            $ids = array_fill_keys(array_keys(self::KEYS), []);
            $timestamp = now()->format('Y-m-d H:i:s');
            $stockBefore = DB::table('inventory_item_tbl')->orderBy('item_id')->get()->toJson();
            $existingItemIds = DB::table('inventory_item_tbl')->pluck('item_id')->all();
            $itemMap = [];
            // Process each material.
            foreach ($plan['materials'] as $material) {
                $referenceId = $material['reference_item_id'];
                unset($material['reference_item_id']);
                // Stop if the exists item name check matches.
                if (DB::table('inventory_item_tbl')->where('item_name', $material['item_name'])->exists()) {
                    throw new RuntimeException('A conflicting material name prevents importing the batch.');
                }
                $itemMap[$referenceId] = $this->insert('inventory_item_tbl', $material, $ids);
            }
            $counts = ['within_budget' => 0, 'small_overrun' => 0, 'material_overrun' => 0];
            // Process each scenario.
            foreach ($plan['projects'] as $scenario) {
                $project = $scenario['project'];
                // Stop if the project data source and project status checks match.
                if ($project['data_source'] !== 'company_inspired_sample' || $project['status'] !== 'Completed'
                    || (float) $project['completion_percentage'] !== 100.0
                    || $project['actual_end_date'] > today()->toDateString()
                    || $project['start_date'] > $project['actual_end_date']
                    || DB::table('project_tbl')->where('project_name', $project['project_name'])->exists()) {
                    throw new RuntimeException('An invalid scenario or conflicting project name prevents importing the batch.');
                }
                $projectId = $this->insert('project_tbl', $project, $ids);
                $budgetId = $this->insert('budgets_tbl', ['project_id' => $projectId,
                    'budget_amount' => $scenario['budget_amount'], 'actual_amount' => $scenario['final_cost']], $ids);
                $historyId = $this->insert('project_budget_history', ['project_id' => $projectId, 'budget_id' => $budgetId,
                    'budget_amount' => $scenario['budget_amount'], 'event_type' => 'initial_recorded',
                    'effective_at' => $project['start_date'].' 08:00:00', 'recorded_at' => $timestamp,
                    'reason' => 'Presentation scenario planned budget; batch '.$batchKey, 'recorded_by' => null], $ids);
                // Process each wave.
                foreach ($scenario['waves'] as $waveIndex => $wave) {
                    // Stop if the wave receipt date and project start date checks match.
                    if ($wave['receipt_date'] < $project['start_date'] || $wave['receipt_date'] > $wave['date']
                        || $wave['date'] > $project['actual_end_date']) {
                        throw new RuntimeException('Scenario transactions are outside their chronological project bounds.');
                    }
                    $receiptId = $this->insert('inventory_transaction_tbl', ['item_id' => $itemMap[$wave['item_id']], 'project_id' => null,
                        'transaction_type' => 'IN', 'quantity' => $wave['quantity'], 'transaction_date' => $wave['receipt_date'],
                        'movement_reason' => 'purchase', 'recorded_at' => $timestamp], $ids);
                    $withdrawalId = $this->insert('inventory_transaction_tbl', ['item_id' => $itemMap[$wave['item_id']], 'project_id' => $projectId,
                        'transaction_type' => 'OUT', 'quantity' => $wave['quantity'], 'transaction_date' => $wave['date'],
                        'movement_reason' => null, 'recorded_at' => $timestamp], $ids);
                    $this->insert('fin_expense_tbl', ['project_id' => null, 'project_cost_component' => 'material',
                        'fin_category_id' => $categories['material'], 'expense_description' => $wave['item_name'].' delivery',
                        'inventory_transaction_id' => $receiptId, 'amount' => $wave['inventory_cost'],
                        'expense_date' => $wave['receipt_date'], 'remarks' => 'Material delivery for scheduled site requirements.',
                        'entry_kind' => 'inventory_purchase', 'created_at' => $timestamp, 'updated_at' => $timestamp], $ids);
                    $this->insert('inventory_cost_allocation_tbl', ['in_transaction_id' => $receiptId,
                        'out_transaction_id' => $withdrawalId, 'project_id' => $projectId, 'quantity' => $wave['quantity'],
                        'valuation_status' => 'valued', 'unit_cost' => $wave['unit_cost'],
                        'allocated_amount' => $wave['inventory_cost'], 'allocated_at' => $timestamp], $ids);
                    // Process each amount.
                    foreach ($wave['direct'] as $component => $amount) {
                        $description = ['material' => 'Additional site supplies', 'labor' => 'Site payroll and labor services',
                            'equipment' => 'Equipment rental and operating costs', 'other' => 'Transport and site support'][$component];
                        $this->insert('fin_expense_tbl', ['project_id' => $projectId, 'project_cost_component' => $component,
                            'fin_category_id' => $categories[$component], 'expense_description' => $description.' - work period '.($waveIndex + 1),
                            'amount' => $amount, 'expense_date' => $wave['date'], 'remarks' => null,
                            'entry_kind' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp], $ids);
                    }
                }
                // Process each snapshot.
                foreach ($scenario['snapshots'] as $snapshot) {
                    $asOf = substr($snapshot['captured_at'], 0, 10);
                    $observed = array_filter($scenario['waves'], fn ($wave) => $wave['date'] <= $asOf);
                    $observedCost = round(array_sum(array_map(fn ($wave) => array_sum($wave['direct']) + $wave['inventory_cost'], $observed)), 2);
                    // Stop if the snapshot data source and snapshot capture reason checks match.
                    if ($snapshot['data_source'] !== 'company_inspired_sample' || $snapshot['capture_reason'] !== 'presentation_scenario'
                        || $snapshot['capture_schema_version'] !== 3 || $asOf < $project['start_date'] || $asOf >= $project['actual_end_date']
                        || $snapshot['finance_as_of_date'] !== $asOf
                        || $snapshot['planned_budget'] !== $scenario['budget_amount'] || $snapshot['final_actual_cost'] !== $scenario['final_cost']
                        || abs($snapshot['cumulative_total_expense'] - $observedCost) > 0.01) {
                        throw new RuntimeException('Presentation observations cannot be imported as operational history.');
                    }
                    $this->insert('ml_project_cost_snapshots', $snapshot + ['project_id' => $projectId,
                        'budget_history_id' => $historyId, 'finalized_at' => $timestamp], $ids);
                }
                $quality = app(ProjectCostDataQualityService::class)->inspect($projectId);
                // Stop if the quality eligible and quality final cost checks match.
                if (! $quality['eligible'] || abs($quality['final_cost'] - $scenario['final_cost']) > 0.01) {
                    throw new RuntimeException('Final-cost reconciliation failed: '.json_encode($quality['errors']));
                }
                $outcome = $quality['overrun_outcomes']['current_budget'];
                $counts[$outcome['material_overrun'] ? 'material_overrun' : ($outcome['any_overrun'] ? 'small_overrun' : 'within_budget')]++;
            }
            // Stop if the to json does not equal stock before.
            if (DB::table('inventory_item_tbl')->whereIn('item_id', $existingItemIds)->orderBy('item_id')->get()->toJson() !== $stockBefore) {
                throw new RuntimeException('Existing inventory items or current stock changed.');
            }
            $records = [];
            // Process each key.
            foreach (self::KEYS as $table => $key) {
                $rows = DB::table($table)->whereIn($key, $ids[$table])->orderBy($key)->get()->toArray();
                $records[$table] = ['ids' => $ids[$table], 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
            }
            $manifest = ['batch_key' => $batchKey, 'plan_sha256' => $hash, 'project_count' => count($ids['project_tbl']),
                'outcome_counts' => $counts, 'snapshot_count' => count($ids['ml_project_cost_snapshots']),
                'records' => $records, 'stock_net_change' => 0, 'existing_items_unchanged' => true,
                'provenance' => $plan['provenance'], 'observation_policy' => $plan['observation_policy']];
            DB::table('ml_presentation_batches')->where('batch_key', $batchKey)
                ->update(['manifest' => json_encode($manifest, JSON_THROW_ON_ERROR)]);

            return $manifest + ['already_applied' => false];
        });
    }

    // Get the verify manifest.
    public function verifyManifest(array $manifest): void
    {
        // Process each key.
        foreach (self::KEYS as $table => $key) {
            $record = $manifest['records'][$table];
            $rows = DB::table($table)->whereIn($key, $record['ids'])->orderBy($key)->get()->toArray();
            // Stop if the rows and record ids checks match.
            if (count($rows) !== count($record['ids'])
                || hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)) !== $record['sha256']) {
                throw new RuntimeException('Batch records have changed; review before any retry or rollback: '.$table);
            }
        }
    }

    // Get the insert.
    private function insert(string $table, array $row, array &$ids): int
    {
        $id = (int) DB::table($table)->insertGetId($row, self::KEYS[$table]);
        $ids[$table][] = $id;

        return $id;
    }

    // Get the categories.
    private function categories(): array
    {
        $codes = ['material' => ['CONSTRUCTION_SUPPLY', 'CONST_SUPPLY'], 'labor' => ['SALARIES_WAGES'],
            'equipment' => ['EQUIPMENT_RENTAL', 'OTHERS_SOS_ETC'], 'other' => ['TRANSPORTATION_EXPENSES', 'OTHERS_SOS_ETC']];
        $categories = [];
        // Process each options.
        foreach ($codes as $component => $options) {
            $id = null;
            // Process each code.
            foreach ($options as $code) {
                $id = DB::table('fin_expense_category_tbl')->where('category_code', $code)
                    ->where('classification', 'direct')->where('is_active', true)->value('fin_category_id');
                // Run this step if the id is not null.
                if ($id !== null) {
                    break;
                }
            }
            // Stop if the id is null.
            if ($id === null) {
                throw new RuntimeException('A configured direct expense category is required for '.$component.'.');
            }
            $categories[$component] = (int) $id;
        }

        return $categories;
    }
}
