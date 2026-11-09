<?php

namespace App\Services\ML;

use Carbon\CarbonImmutable;
use RuntimeException;

/** Read the generated data in parts. Check each event, linked record, cost, and record count. */
class ProjectCostAugmentationAudit
{
    // Get the audit.
    public function audit(string $directory): array
    {
        $manifest = (new ProjectCostAugmentationDataset($directory))->summary();
        $projects = [];
        // Process each project.
        foreach (ProjectCostAugmentationDataset::read($directory.'/projects.jsonl.gz') as $project) {
            $id = $project['project_id'];
            // Reject invalid generated completed project.
            if (isset($projects[$id]) || $project['status'] !== 'Completed' || $project['completion_percentage'] !== 100
                || $project['start_date'] >= $project['actual_end_date'] || $project['actual_end_date'] > today()->toDateString()
                || ! in_array($project['donor_project_id'], $manifest['database_training_project_ids'])) {
                throw new RuntimeException('Invalid generated completed project.');
            }
            $projects[$id] = $project + ['expense_count' => 0, 'movement_count' => 0, 'direct_cents' => 0, 'material_cents' => 0];
            // Run this step if the manifest method equals rule based construction ledgers v1.
            if (($manifest['method'] ?? '') === 'rule_based_construction_ledgers_v1') {
                $start = CarbonImmutable::parse($project['start_date']);
                // Stop if the year and project actual end date checks match.
                if ($start->year < 2019 || $project['actual_end_date'] > $start->addMonthsNoOverflow(7)->toDateString()
                    || $project['estimated_end_date'] > $start->addMonthsNoOverflow(7)->toDateString()) {
                    throw new RuntimeException('Rule-based project violates its calendar or duration limit.');
                }
                $ratio = $project['actual_cost'] / $project['budget'];
                $overrunScenario = $project['generation_rules']['cost_scenario'] === 'delay_and_rework';
                // Stop if the overrun scenario and ratio checks match.
                if (($overrunScenario && ($ratio < 1.01 - .000001 || $ratio > 1.18 + .000001))
                    || (! $overrunScenario && ($ratio < .85 - .000001 || $ratio > .995 + .000001))) {
                    throw new RuntimeException('Project ledger violates its declared business cost scenario.');
                }
            }
        }
        $items = [];
        // Process each item.
        foreach (ProjectCostAugmentationDataset::read($directory.'/items.jsonl.gz') as $item) {
            // Stop if the item item id is set.
            if (isset($items[$item['item_id']])) {
                throw new RuntimeException('Duplicate catalogue item.');
            }
            $items[$item['item_id']] = $item;
        }
        // Process each event.
        foreach (ProjectCostAugmentationDataset::read($directory.'/expenses.jsonl.gz') as $event) {
            $id = $event['project_id'];
            // Reject invalid expense amount, date or project reference.
            if (! isset($projects[$id]) || $event['amount'] <= 0 || $event['expense_date'] < $projects[$id]['start_date'] || $event['expense_date'] > $projects[$id]['actual_end_date']) {
                throw new RuntimeException('Invalid expense amount, date or project reference.');
            }
            $projects[$id]['expense_count']++;
            $projects[$id]['direct_cents'] += (int) round($event['amount'] * 100);
        }
        $current = null;
        $lots = [];
        $seen = [];
        $lastDate = '';
        // Process each event.
        foreach (ProjectCostAugmentationDataset::read($directory.'/inventory-transactions.jsonl.gz') as $event) {
            $id = $event['project_id'];
            // Run this step if the current does not equal id.
            if ($current !== $id) {
                // Stop if the lots and id checks match.
                if ($lots !== [] || isset($seen[$id])) {
                    throw new RuntimeException('Unclosed inventory lots or interleaved project rows.');
                }
                $current = $id;
                $seen[$id] = true;
                $lastDate = '';
            }
            // Reject invalid inventory reference, valuation or chronological order.
            if (! isset($projects[$id],$items[$event['item_id']]) || $event['quantity'] <= 0 || $event['amount'] <= 0
                || $event['transaction_date'] < $lastDate || $event['transaction_date'] < $projects[$id]['start_date'] || $event['transaction_date'] > $projects[$id]['actual_end_date']
                || abs($event['quantity'] * $event['unit_cost'] - $event['amount']) > .01) {
                throw new RuntimeException('Invalid inventory reference, valuation or chronological order.');
            }
            $lastDate = $event['transaction_date'];
            // Run this step if the manifest business rules version is at least 2.
            if (($manifest['business_rules_version'] ?? 0) >= 2) {
                $name = $items[$event['item_id']]['item_name'];
                $bulk = preg_match('/sand|gravel/i', $name) === 1;
                $step = $bulk ? .25 : 1;
                $expectedPrice = (float) $items[$event['item_id']]['unit_price'] * $projects[$id]['generation_rules']['year_factor'];
                // Stop if the event quantity and step checks match.
                if (abs($event['quantity'] / $step - round($event['quantity'] / $step)) > .000001
                    || $event['unit_cost'] < $expectedPrice * .95 - .01 || $event['unit_cost'] > $expectedPrice * 1.05 + .01
                    || preg_match('/hammer|mallet|pliers|tape measure|drill bit/i', $name)) {
                    throw new RuntimeException('Inventory package, catalogue price or durable-tool rule failed.');
                }
                $kind = $projects[$id]['project_kind'];
                $bounds = $kind === 'interior' ? [0, .25, .5, 1] : [0, $kind === 'warehouse' ? .65 : .55, .75, 1];
                $phaseIndex = array_search($event['construction_phase'], ['structure', 'services', 'finishing'], true);
                // Stop if the phase index is false.
                if ($phaseIndex === false) {
                    throw new RuntimeException('Unknown construction phase.');
                }
                // Run this step if the event transaction type equals out.
                if ($event['transaction_type'] === 'OUT') {
                    $start = CarbonImmutable::parse($projects[$id]['start_date']);
                    $days = max(1, (int) $start->diffInDays(CarbonImmutable::parse($projects[$id]['actual_end_date'])));
                    $elapsed = (int) $start->diffInDays(CarbonImmutable::parse($event['transaction_date']));
                    // Stop if the elapsed and days checks match.
                    if ($elapsed < floor($days * $bounds[$phaseIndex]) || $elapsed > ceil($days * $bounds[$phaseIndex + 1])) {
                        throw new RuntimeException('Material consumption falls outside its construction phase.');
                    }
                }
            }
            $lot = $event['lot_id'];
            // Run this step if the event transaction type equals in.
            if ($event['transaction_type'] === 'IN') {
                // Stop if the lot is set.
                if (isset($lots[$lot])) {
                    throw new RuntimeException('Duplicate receipt lot.');
                }
                $lots[$lot] = $event;
            } elseif ($event['transaction_type'] === 'OUT') {
                // Stop if the lot and event item id checks match.
                if (! isset($lots[$lot]) || $lots[$lot]['item_id'] !== $event['item_id'] || $lots[$lot]['quantity'] !== $event['quantity'] || $lots[$lot]['amount'] !== $event['amount']) {
                    throw new RuntimeException('Withdrawal does not match an earlier valued receipt.');
                }
                unset($lots[$lot]);
                $projects[$id]['material_cents'] += (int) round($event['amount'] * 100);
            } else {
                throw new RuntimeException('Unknown inventory movement type.');
            }
            $projects[$id]['movement_count']++;
        }
        // Stop if the lots is not empty.
        if ($lots !== []) {
            throw new RuntimeException('Unclosed final inventory lots.');
        }
        // Process each project.
        foreach ($projects as $project) {
            // Stop if the project expense count and manifest expenses per project checks match.
            if ($project['expense_count'] !== $manifest['expenses_per_project'] || $project['movement_count'] !== $manifest['inventory_transactions_per_project']
                || (int) round($project['actual_cost'] * 100) !== $project['direct_cents'] + $project['material_cents']) {
                throw new RuntimeException('Per-project event counts or final-cost reconciliation failed.');
            }
        }
        // Process each row.
        foreach (ProjectCostAugmentationDataset::read($directory.'/training-observations.jsonl.gz') as $row) {
            $project = $projects[$row['project_id']] ?? null;
            $final = (float) $row['actual_cost'] + (isset($row['snapshot_id']) ? (float) $row['fin_total_expense'] : 0);
            // Stop if the project and final checks match.
            if ($project === null || abs($final - $project['actual_cost']) > .01 || abs((float) $row['budget'] - $project['budget']) > .01
                || $row['donor_project_id'] !== $project['donor_project_id']) {
                throw new RuntimeException('Training label or donor does not match its generated ledger.');
            }
        }
        // Stop if the projects and manifest dummy projects checks match.
        if (count($projects) !== $manifest['dummy_projects'] || count($items) !== $manifest['item_records']) {
            throw new RuntimeException('Dataset coverage differs from manifest.');
        }

        return ['valid' => true, 'projects' => count($projects), 'items' => count($items),
            'expenses' => array_sum(array_column($projects, 'expense_count')), 'inventory_transactions' => array_sum(array_column($projects, 'movement_count')),
            'all_project_costs_reconciled' => true, 'chronological_inventory_verified' => true, 'database_only_holdout_projects' => count($manifest['database_test_project_ids'])];
    }
}
