<?php

namespace App\Services\ML;

use App\Services\ProjectCostFeatureBuilder;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;
use Throwable;

/** Smoothed joint empirical project bootstrap, conditional on one training donor per project. */
class ProjectCostDistributionGenerator
{
    private Randomizer $random;

    public function generate(array $source, string $directory, int $projects = 1000, int $expenses = 300, int $transactions = 1000, int $seed = 20261008): array
    {
        if (($source['source']['host'] ?? '') !== 'srv603.hstgr.io' || ($source['source']['database'] ?? '') !== 'u822802132_pfims') {
            throw new RuntimeException('A verified live PFIMS source export is required.');
        }
        if ($projects < 1 || $expenses < 1 || $transactions < 2 || $transactions % 2 !== 0) {
            throw new RuntimeException('Positive counts and paired IN/OUT inventory movements are required.');
        }
        if (is_dir($directory)) {
            throw new RuntimeException('Dataset directory already exists; use a new version directory.');
        }
        $records = collect($source['cohort']['records'])->map(fn ($r) => (object) $r);
        $split = ProjectCostAugmentationDataset::split($records);
        $training = $records->whereIn('project_id', $split['training'])->groupBy('project_id');
        $projectMap = array_column($source['project_tbl'], null, 'project_id');
        $items = array_column($source['inventory_item_tbl'], null, 'item_id');
        $pricedItems = array_filter($items, fn ($i) => (float) $i['unit_price'] > 0);
        if ($pricedItems === []) {
            throw new RuntimeException('The live catalogue has no valued items.');
        }
        $expenseGroups = collect($source['fin_expense_tbl'])->filter(fn ($e) => $e['project_id'] !== null && $e['inventory_transaction_id'] === null && (float) $e['amount'] > 0)->groupBy('project_id');
        $outGroups = collect($source['inventory_transaction_tbl'])->where('transaction_type', 'OUT')->groupBy('project_id');
        $this->random = new Randomizer(new Mt19937($seed));
        $temporary = $directory.'.building.'.bin2hex(random_bytes(6));
        if (! mkdir($temporary, 0700, true)) {
            throw new RuntimeException('Unable to create dataset directory.');
        }
        $handles = $counts = [];
        try {
            foreach (['projects', 'expenses', 'inventory-transactions', 'items', 'training-observations'] as $name) {
                $handles[$name] = gzopen($temporary.'/'.$name.'.jsonl.gz', 'wb6');
                if ($handles[$name] === false) {
                    throw new RuntimeException('Unable to open dataset output.');
                }
                $counts[$name] = 0;
            }
            $write = function (string $name, array $row) use (&$handles, &$counts): void {
                $line = json_encode($row, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n";
                if (gzwrite($handles[$name], $line) !== strlen($line)) {
                    throw new RuntimeException('Incomplete dataset write.');
                }
                $counts[$name]++;
            };
            foreach ($items as $item) {
                $write('items', $item);
            }
            for ($index = 1; $index <= $projects; $index++) {
                $donorId = $this->pick($split['training']);
                $donorRows = $training[$donorId];
                $donor = (array) $donorRows->last();
                $project = $projectMap[$donorId];
                $id = sprintf('dummy-%06d', $index);
                $scale = $this->draw(0.9, 1.1);
                $budget = round((float) $donor['budget'] * $scale, 2);
                $donorFinal = (float) ($donor['reconciled_final_cost'] ?? $donor['actual_cost']);
                $final = max(2, (int) round($donorFinal * $scale * $this->draw(.97, 1.03) * 100));
                $directDonor = $expenseGroups->get($donorId, collect())->all();
                $directShare = $donorFinal > 0 ? array_sum(array_column($directDonor, 'amount')) / $donorFinal : .5;
                $directTotal = max($expenses, (int) round($final * min(.95, max(.05, $directShare))));
                $materialTotal = $final - $directTotal;
                if ($materialTotal < $transactions / 2) {
                    throw new RuntimeException('Project cost cannot support the requested transaction count.');
                }
                $start = CarbonImmutable::parse($project['start_date'])->startOfDay();
                $donorDays = max(1, (int) $start->diffInDays(CarbonImmutable::parse($project['actual_end_date'])));
                $durationScale = $this->draw(.95, 1.05);
                $maximumDays = (int) $start->diffInDays($start->addMonthsNoOverflow(8));
                $days = min($maximumDays, max(1, (int) round($donorDays * $durationScale)));
                $donorPlannedDays = max(1, (int) CarbonImmutable::parse($project['start_date'])->diffInDays(CarbonImmutable::parse($project['estimated_end_date'])));
                $plannedDays = min($maximumDays, max(1, (int) round($donorPlannedDays * $durationScale)));
                $end = $start->addDays($days);
                if ($end->isFuture()) {
                    $start = CarbonImmutable::today()->subDays($days);
                }
                $end = $start->addDays($days);
                $plannedEnd = $start->addDays($plannedDays);
                $direct = $movements = [];
                $weights = [];
                $templates = [];
                for ($j = 0; $j < $expenses; $j++) {
                    $template = $directDonor === [] ? ['expense_date' => $project['start_date'], 'amount' => 1, 'project_cost_component' => 'other', 'fin_category_id' => null, 'expense_description' => 'Project operating expense'] : $this->pick(array_values($directDonor));
                    $templates[] = $template;
                    $weights[] = max(.01, (float) $template['amount']) * $this->draw(.8, 1.2);
                }
                foreach ($this->apportion($directTotal, $weights) as $j => $amount) {
                    $template = $templates[$j];
                    $direct[] = ['expense_id' => $id.'-E'.($j + 1), 'project_id' => $id, 'expense_date' => $this->eventDate($start, $days, $project, $template['expense_date']),
                        'project_cost_component' => $template['project_cost_component'] ?? 'other', 'fin_category_id' => $template['fin_category_id'],
                        'expense_description' => $template['expense_description'], 'amount' => $amount / 100];
                }
                $outs = $outGroups->get($donorId, collect())->filter(fn ($o) => isset($pricedItems[$o['item_id']]))->values()->all();
                $pairs = $transactions / 2;
                $materials = $weights = [];
                for ($j = 0; $j < $pairs; $j++) {
                    $template = $outs === [] ? ['item_id' => $this->pick(array_keys($pricedItems)), 'quantity' => 1, 'transaction_date' => $project['start_date']] : $this->pick($outs);
                    $materials[] = $template;
                    $weights[] = max(.01, (float) $template['quantity'] * (float) $pricedItems[$template['item_id']]['unit_price']) * $this->draw(.8, 1.2);
                }
                foreach ($this->apportion($materialTotal, $weights) as $j => $amount) {
                    $template = $materials[$j];
                    $item = $pricedItems[$template['item_id']];
                    $date = $this->eventDate($start, $days, $project, $template['transaction_date']);
                    $receipt = max($start->toDateString(), CarbonImmutable::parse($date)->subDays($this->random->getInt(0, 3))->toDateString());
                    $quantity = round($amount / 100 / (float) $item['unit_price'], 8);
                    $base = ['project_id' => $id, 'item_id' => $item['item_id'], 'quantity' => $quantity, 'unit_cost' => (float) $item['unit_price'], 'amount' => $amount / 100, 'lot_id' => $id.'-L'.($j + 1)];
                    $movements[] = $base + ['transaction_id' => $id.'-I'.($j + 1), 'transaction_type' => 'IN', 'transaction_date' => $receipt];
                    $movements[] = $base + ['transaction_id' => $id.'-O'.($j + 1), 'transaction_type' => 'OUT', 'transaction_date' => $date];
                }
                usort($direct, fn ($a, $b) => [$a['expense_date'], $a['expense_id']] <=> [$b['expense_date'], $b['expense_id']]);
                usort($movements, fn ($a, $b) => [$a['transaction_date'], $a['transaction_type'], $a['transaction_id']] <=> [$b['transaction_date'], $b['transaction_type'], $b['transaction_id']]);
                $newProject = ['project_id' => $id, 'donor_project_id' => $donorId, 'project_name' => $project['project_name'].' / '.sprintf('%04d', $index),
                    'status' => 'Completed', 'completion_percentage' => 100, 'start_date' => $start->toDateString(), 'actual_end_date' => $end->toDateString(),
                    'estimated_end_date' => $plannedEnd->toDateString(), 'duration_months' => max(1, (int) $start->diffInMonths($plannedEnd)), 'worker_count' => max(1, (int) round($donor['worker_count'] * $scale)),
                    'budget' => $budget, 'actual_cost' => $final / 100, 'data_source' => 'distribution_dummy'];
                $write('projects', $newProject);
                foreach ($direct as $event) {
                    $write('expenses', $event);
                }
                foreach ($movements as $event) {
                    $write('inventory-transactions', $event);
                }
                foreach ($donorRows as $donorRow) {
                    $row = (array) $donorRow;
                    $row = array_replace($row, $newProject, ['completed_at' => $end->toDateString(), 'planned_start_date' => $start->toDateString(), 'data_source' => 'distribution_dummy']);
                    if (isset($donorRow->snapshot_id)) {
                        $fraction = min(.99, max(0, (float) ($donorRow->elapsed_days ?? 0) / $donorDays));
                        $asOf = $start->addDays((int) floor($days * $fraction));
                        $row = array_replace($row, $this->snapshot($direct, $movements, $asOf, $start, $plannedDays, $budget, (float) $donorRow->completion_percentage));
                        $row['snapshot_id'] = $id.'-S'.$donorRow->snapshot_id;
                        $row['actual_cost'] = max(0, $final / 100 - $row['fin_total_expense']);
                        $row['reconciled_final_cost'] = $final / 100;
                        $row = array_replace($row, app(ProjectCostFeatureBuilder::class)->build($row)['values']);
                    }
                    $write('training-observations', $row);
                }
            }
            foreach ($handles as $handle) {
                gzclose($handle);
            }
            $handles = [];
            $manifest = ['schema_version' => 1, 'created_at' => now()->toIso8601String(), 'method' => 'conditional_joint_empirical_bootstrap_with_bounded_jitter',
                'seed' => $seed, 'live_source' => $source['source'], 'database_fingerprint' => ProjectCostAugmentationDataset::fingerprint($records),
                'database_training_project_ids' => $split['training'], 'database_test_project_ids' => $split['test'], 'database_test_ratio' => .30,
                'dummy_projects' => $projects, 'expenses_per_project' => $expenses, 'inventory_transactions_per_project' => $transactions,
                'item_records' => count($items), 'prediction_strategy' => $source['cohort']['strategy'],
                'database_source_provenance' => $records->groupBy('data_source')->map(fn ($r) => $r->pluck('project_id')->unique()->count())->all(),
                'test_scope' => 'database_only; no dummy observations',
                'cross_validation_policy' => 'Include dummy projects only when their single donor belongs to the fold training partition.',
                'limitations' => ['Fixed requested event counts deliberately differ from observed per-project transaction frequencies.',
                    'A larger dummy dataset adds no independent company outcomes; performance must be evaluated on database projects.',
                    'Database provenance labels are preserved; they are not independently verified by this generator.'], 'files' => []];
            foreach ($counts as $name => $count) {
                $manifest['files'][$name.'.jsonl.gz'] = ['records' => $count, 'sha256' => hash_file('sha256', $temporary.'/'.$name.'.jsonl.gz')];
            }
            file_put_contents($temporary.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            (new ProjectCostAugmentationDataset($temporary))->assertDatabaseMatches($records);
            (new ProjectCostAugmentationAudit)->audit($temporary);
            if (! rename($temporary, $directory)) {
                throw new RuntimeException('Unable to publish dataset directory.');
            }

            return $manifest;
        } catch (Throwable $error) {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    gzclose($handle);
                }
            }
            // Incomplete files remain in an unreferenced .building directory for diagnosis.
            throw $error;
        }
    }

    private function pick(array $values): mixed
    {
        return $values[$this->random->getInt(0, count($values) - 1)];
    }

    private function draw(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->random->getInt(0, 1000000) / 1000000;
    }

    private function apportion(int $total, array $weights): array
    {
        $remaining = $total - count($weights);
        $sum = array_sum($weights);
        if ($remaining < 0) {
            throw new RuntimeException('Requested events exceed available centavos.');
        }
        $amounts = array_map(fn ($w) => 1 + (int) floor($remaining * $w / $sum), $weights);
        $amounts[count($amounts) - 1] += $total - array_sum($amounts);

        return $amounts;
    }

    private function eventDate(CarbonImmutable $start, int $days, array $project, string $date): string
    {
        $donorStart = CarbonImmutable::parse($project['start_date']);
        $donorDays = max(1, (int) $donorStart->diffInDays(CarbonImmutable::parse($project['actual_end_date'])));
        $fraction = min(1, max(0, $donorStart->diffInDays(CarbonImmutable::parse($date), false) / $donorDays));

        return $start->addDays(max(0, min($days, (int) round($fraction * $days) + $this->random->getInt(-3, 3))))->toDateString();
    }

    private function snapshot(array $direct, array $movements, CarbonImmutable $asOf, CarbonImmutable $start, int $days, float $budget, float $progress): array
    {
        $date = $asOf->toDateString();
        $recent = $asOf->subDays(29)->toDateString();
        $week = $asOf->subDays(6)->toDateString();
        $known = array_filter($direct, fn ($e) => $e['expense_date'] <= $date);
        $outs = array_filter($movements, fn ($e) => $e['transaction_type'] === 'OUT' && $e['transaction_date'] <= $date);
        $sum = fn ($rows) => round(array_sum(array_column($rows, 'amount')), 2);
        $last = fn ($rows, $key, $since) => array_filter($rows, fn ($e) => $e[$key] >= $since);
        $components = [];
        foreach (['material', 'labor', 'equipment', 'other'] as $component) {
            $components[$component] = $sum(array_filter($known, fn ($e) => $e['project_cost_component'] === $component));
        }
        $components['material'] += $sum($outs);
        $elapsed = (int) $start->diffInDays($asOf);
        $row = ['captured_at' => $asOf->toDateTimeString(), 'completion_percentage' => $progress, 'budget' => $budget,
            'planned_duration_days' => $days, 'elapsed_days' => $elapsed, 'elapsed_duration_months' => $elapsed / 30, 'cost_coverage_complete' => 1,
            'fin_total_expense' => round($sum($known) + $sum($outs), 2), 'material_cost' => $components['material'], 'labor_cost' => $components['labor'],
            'direct_expense_count_30d' => count($last($known, 'expense_date', $recent)), 'direct_expense_count_7d' => count($last($known, 'expense_date', $week)),
            'direct_expense_amount_30d' => $sum($last($known, 'expense_date', $recent)), 'direct_expense_amount_7d' => $sum($last($known, 'expense_date', $week)),
            'stock_out_count_30d' => count($last($outs, 'transaction_date', $recent)), 'stock_out_count_7d' => count($last($outs, 'transaction_date', $week)),
            'valued_stock_out_cost' => $sum($outs), 'valued_stock_out_cost_30d' => $sum($last($outs, 'transaction_date', $recent)), 'valued_stock_out_cost_7d' => $sum($last($outs, 'transaction_date', $week)),
            'unvalued_stock_out_count' => 0, 'has_unvalued_stock_out' => 0];
        foreach ($components as $name => $amount) {
            $row['fin_'.$name.'_expense'] = $amount;
        }
        $window = max(1, min(30, $elapsed + 1));
        $row['expense_frequency_30d'] = $row['direct_expense_count_30d'] / $window * 7;
        $row['stock_out_frequency_30d'] = $row['stock_out_count_30d'] / $window * 7;
        $row['expense_amount_per_day_30d'] = $row['direct_expense_amount_30d'] / $window;

        return $row;
    }
}
