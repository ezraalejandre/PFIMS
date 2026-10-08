<?php

namespace App\Services\ML;

use Carbon\CarbonImmutable;
use RuntimeException;

/** Independent streamed checks of every event, reference, cost and requested count. */
class ProjectCostAugmentationAudit
{
    public function audit(string $directory): array
    {
        $manifest = (new ProjectCostAugmentationDataset($directory))->summary();
        $projects = [];
        foreach (ProjectCostAugmentationDataset::read($directory.'/projects.jsonl.gz') as $project) {
            $id = $project['project_id'];
            if (isset($projects[$id]) || $project['status'] !== 'Completed' || $project['completion_percentage'] !== 100
                || $project['start_date'] >= $project['actual_end_date'] || $project['actual_end_date'] > today()->toDateString()
                || ! in_array($project['donor_project_id'], $manifest['database_training_project_ids'])) {
                throw new RuntimeException('Invalid generated completed project.');
            }
            $projects[$id] = $project + ['expense_count' => 0, 'movement_count' => 0, 'direct_cents' => 0, 'material_cents' => 0];
            if (($manifest['method'] ?? '') === 'rule_based_construction_ledgers_v1') {
                $start = CarbonImmutable::parse($project['start_date']);
                if ($start->year < 2019 || $project['actual_end_date'] > $start->addMonthsNoOverflow(7)->toDateString()
                    || $project['estimated_end_date'] > $start->addMonthsNoOverflow(7)->toDateString()) {
                    throw new RuntimeException('Rule-based project violates its calendar or duration limit.');
                }
                $ratio = $project['actual_cost'] / $project['budget'];
                $overrunScenario = $project['generation_rules']['cost_scenario'] === 'delay_and_rework';
                if (($overrunScenario && ($ratio < 1.01 - .000001 || $ratio > 1.18 + .000001))
                    || (! $overrunScenario && ($ratio < .85 - .000001 || $ratio > .995 + .000001))) {
                    throw new RuntimeException('Project ledger violates its declared business cost scenario.');
                }
            }
        }
        $items = [];
        foreach (ProjectCostAugmentationDataset::read($directory.'/items.jsonl.gz') as $item) {
            if (isset($items[$item['item_id']])) {
                throw new RuntimeException('Duplicate catalogue item.');
            }
            $items[$item['item_id']] = $item;
        }
        foreach (ProjectCostAugmentationDataset::read($directory.'/expenses.jsonl.gz') as $event) {
            $id = $event['project_id'];
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
        foreach (ProjectCostAugmentationDataset::read($directory.'/inventory-transactions.jsonl.gz') as $event) {
            $id = $event['project_id'];
            if ($current !== $id) {
                if ($lots !== [] || isset($seen[$id])) {
                    throw new RuntimeException('Unclosed inventory lots or interleaved project rows.');
                }
                $current = $id;
                $seen[$id] = true;
                $lastDate = '';
            }
            if (! isset($projects[$id],$items[$event['item_id']]) || $event['quantity'] <= 0 || $event['amount'] <= 0
                || $event['transaction_date'] < $lastDate || $event['transaction_date'] < $projects[$id]['start_date'] || $event['transaction_date'] > $projects[$id]['actual_end_date']
                || abs($event['quantity'] * $event['unit_cost'] - $event['amount']) > .01) {
                throw new RuntimeException('Invalid inventory reference, valuation or chronological order.');
            }
            $lastDate = $event['transaction_date'];
            if (($manifest['business_rules_version'] ?? 0) >= 2) {
                $name = $items[$event['item_id']]['item_name'];
                $bulk = preg_match('/sand|gravel/i', $name) === 1;
                $step = $bulk ? .25 : 1;
                $expectedPrice = (float) $items[$event['item_id']]['unit_price'] * $projects[$id]['generation_rules']['year_factor'];
                if (abs($event['quantity'] / $step - round($event['quantity'] / $step)) > .000001
                    || $event['unit_cost'] < $expectedPrice * .95 - .01 || $event['unit_cost'] > $expectedPrice * 1.05 + .01
                    || preg_match('/hammer|mallet|pliers|tape measure|drill bit/i', $name)) {
                    throw new RuntimeException('Inventory package, catalogue price or durable-tool rule failed.');
                }
                $kind = $projects[$id]['project_kind'];
                $bounds = $kind === 'interior' ? [0, .25, .5, 1] : [0, $kind === 'warehouse' ? .65 : .55, .75, 1];
                $phaseIndex = array_search($event['construction_phase'], ['structure', 'services', 'finishing'], true);
                if ($phaseIndex === false) {
                    throw new RuntimeException('Unknown construction phase.');
                }
                if ($event['transaction_type'] === 'OUT') {
                    $start = CarbonImmutable::parse($projects[$id]['start_date']);
                    $days = max(1, (int) $start->diffInDays(CarbonImmutable::parse($projects[$id]['actual_end_date'])));
                    $elapsed = (int) $start->diffInDays(CarbonImmutable::parse($event['transaction_date']));
                    if ($elapsed < floor($days * $bounds[$phaseIndex]) || $elapsed > ceil($days * $bounds[$phaseIndex + 1])) {
                        throw new RuntimeException('Material consumption falls outside its construction phase.');
                    }
                }
            }
            $lot = $event['lot_id'];
            if ($event['transaction_type'] === 'IN') {
                if (isset($lots[$lot])) {
                    throw new RuntimeException('Duplicate receipt lot.');
                }
                $lots[$lot] = $event;
            } elseif ($event['transaction_type'] === 'OUT') {
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
        if ($lots !== []) {
            throw new RuntimeException('Unclosed final inventory lots.');
        }
        foreach ($projects as $project) {
            if ($project['expense_count'] !== $manifest['expenses_per_project'] || $project['movement_count'] !== $manifest['inventory_transactions_per_project']
                || (int) round($project['actual_cost'] * 100) !== $project['direct_cents'] + $project['material_cents']) {
                throw new RuntimeException('Per-project event counts or final-cost reconciliation failed.');
            }
        }
        foreach (ProjectCostAugmentationDataset::read($directory.'/training-observations.jsonl.gz') as $row) {
            $project = $projects[$row['project_id']] ?? null;
            $final = (float) $row['actual_cost'] + (isset($row['snapshot_id']) ? (float) $row['fin_total_expense'] : 0);
            if ($project === null || abs($final - $project['actual_cost']) > .01 || abs((float) $row['budget'] - $project['budget']) > .01
                || $row['donor_project_id'] !== $project['donor_project_id']) {
                throw new RuntimeException('Training label or donor does not match its generated ledger.');
            }
        }
        if (count($projects) !== $manifest['dummy_projects'] || count($items) !== $manifest['item_records']) {
            throw new RuntimeException('Dataset coverage differs from manifest.');
        }

        return ['valid' => true, 'projects' => count($projects), 'items' => count($items),
            'expenses' => array_sum(array_column($projects, 'expense_count')), 'inventory_transactions' => array_sum(array_column($projects, 'movement_count')),
            'all_project_costs_reconciled' => true, 'chronological_inventory_verified' => true, 'database_only_holdout_projects' => count($manifest['database_test_project_ids'])];
    }
}
