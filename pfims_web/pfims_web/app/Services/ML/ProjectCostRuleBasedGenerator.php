<?php

namespace App\Services\ML;

use App\Services\ProjectCostFeatureBuilder;
use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;
use Throwable;

/** Rule-based construction ledgers; training-only donors anchor project scale and catalogue. */
class ProjectCostRuleBasedGenerator
{
    private Randomizer $random;

    public function generate(array $source, string $directory, int $projects = 1000, int $expenses = 300, int $transactions = 500, int $seed = 20261009): array
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
                $donorDays = max(1, (int) CarbonImmutable::parse($project['start_date'])->diffInDays(CarbonImmutable::parse($project['actual_end_date'])));
                $id = sprintf('ingested-%06d', $index);
                // Each record depends on one training donor, never a validation/test outcome.
                $year = 2019 + (($index - 1) % (today()->year - 2019 + 1));
                $earliest = CarbonImmutable::create($year, 1, 1);
                $latest = CarbonImmutable::create($year, 12, 31)->min(CarbonImmutable::today()->subMonthsNoOverflow(7));
                if ($latest->lt($earliest)) {
                    $latest = CarbonImmutable::today()->subDays(45)->max($earliest);
                }
                $start = $earliest->addDays($this->random->getInt(0, max(0, (int) $earliest->diffInDays($latest))));
                $kind = $this->projectKind($project['project_name']);
                $months = $kind === 'interior' ? $this->random->getInt(2, 4) : $this->random->getInt(4, 6);
                $plannedEnd = $start->addMonthsNoOverflow($months)->min(CarbonImmutable::today());
                $plannedDays = max(1, (int) $start->diffInDays($plannedEnd));
                $scenario = $this->random->getInt(1, 100);
                $delay = $scenario <= 20 ? $this->random->getInt(7, 28) : $this->random->getInt(-7, 7);
                $end = $plannedEnd->addDays($delay)->min($start->addMonthsNoOverflow(7))->min(CarbonImmutable::today());
                $days = max(1, (int) $start->diffInDays($end));
                // Scenario adjustment, not a claim of observed historical inflation.
                $priceFactor = pow(1.025, $year - today()->year);
                $scale = $this->draw(.75, 1.25) * $priceFactor;
                $budget = round((float) $donor['budget'] * $scale, 2);
                $costRatio = $scenario <= 20 ? $this->draw(1.01, 1.18) : $this->draw(.85, .995);
                $final = max(2, (int) round($budget * $costRatio * 100));
                $directDonor = $expenseGroups->get($donorId, collect())->all();
                $directShare = $kind === 'interior' ? $this->draw(.35, .45) : $this->draw(.28, .38);
                $dailyWage = round($this->draw(650, 950) * $priceFactor, 2);
                $plannedCrew = max(1, (int) round($budget * $directShare * (7 / 10.5) / (max(1, $plannedDays * 6 / 7) * $dailyWage)));
                $directTotal = max($expenses, (int) round($final * $directShare));
                $materialTotal = $final - $directTotal;
                $pricingScenario = ['year_factor' => $priceFactor, 'planned_daily_wage_estimate' => $dailyWage, 'cost_scenario' => $scenario <= 20 ? 'delay_and_rework' : 'controlled_delivery'];
                $direct = $movements = [];
                $weights = [];
                $templates = [];
                for ($j = 0; $j < $expenses; $j++) {
                    $component = $j % 10 < 7 ? 'labor' : ($j % 10 < 9 ? 'equipment' : 'other');
                    $matching = array_values(array_filter($directDonor, fn ($e) => ($e['project_cost_component'] ?? '') === $component));
                    $templates[] = $matching === [] ? ['fin_category_id' => null, 'project_cost_component' => $component] : $this->pick($matching);
                    $weights[] = ($component === 'labor' ? 1 : ($component === 'equipment' ? 1.5 : .5)) * $this->draw(.8, 1.2);
                }
                $directWeights = $weights;
                foreach ($this->apportion($directTotal, $weights) as $j => $amount) {
                    $template = $templates[$j];
                    $component = $j % 10 < 7 ? 'labor' : ($j % 10 < 9 ? 'equipment' : 'other');
                    // Payroll batches follow work weeks; rentals/transport follow site activity.
                    $day = min($days, max(0, (int) round(($j / max(1, $expenses - 1)) * $days / 7) * 7));
                    $direct[] = ['expense_id' => $id.'-E'.($j + 1), 'project_id' => $id, 'expense_date' => $start->addDays($day)->toDateString(),
                        'project_cost_component' => $component, 'fin_category_id' => $template['fin_category_id'],
                        'expense_description' => match ($component) {
                            'labor' => 'Site crew payroll batch', 'equipment' => 'Site equipment rental and mobilization', default => 'Delivery transport and site overhead'
                        },
                        'amount' => $amount / 100];
                }
                $movements = $this->materialLedger($pricedItems, $kind, $budget, $priceFactor, $materialTotal, (int) ($transactions / 2), $id, $start, $days);
                $materialCents = (int) round(array_sum(array_column(array_filter($movements, fn ($e) => $e['transaction_type'] === 'OUT'), 'amount')) * 100);
                // Settle the direct-work allowance after whole-package purchasing.
                // This keeps a controlled-delivery scenario from becoming an overrun
                // solely because a requested event count forced package rounding.
                $directTotal = $final - $materialCents;
                foreach ($this->apportion($directTotal, $directWeights) as $j => $amount) {
                    $direct[$j]['amount'] = $amount / 100;
                }
                usort($direct, fn ($a, $b) => [$a['expense_date'], $a['expense_id']] <=> [$b['expense_date'], $b['expense_id']]);
                usort($movements, fn ($a, $b) => [$a['transaction_date'], $a['transaction_type'], $a['transaction_id']] <=> [$b['transaction_date'], $b['transaction_type'], $b['transaction_id']]);
                $newProject = ['project_id' => $id, 'donor_project_id' => $donorId, 'project_name' => $project['project_name'].' / '.sprintf('%04d', $index),
                    'status' => 'Completed', 'completion_percentage' => 100, 'start_date' => $start->toDateString(), 'actual_end_date' => $end->toDateString(),
                    'estimated_end_date' => $plannedEnd->toDateString(), 'duration_months' => max(1, (int) $start->diffInMonths($plannedEnd)), 'worker_count' => $plannedCrew,
                    'budget' => $budget, 'actual_cost' => $final / 100, 'project_kind' => $kind, 'generation_rules' => $pricingScenario, 'data_source' => 'rule_based_ingested'];
                $write('projects', $newProject);
                foreach ($direct as $event) {
                    $write('expenses', $event);
                }
                foreach ($movements as $event) {
                    $write('inventory-transactions', $event);
                }
                foreach ($donorRows as $donorRow) {
                    $row = (array) $donorRow;
                    $row = array_replace($row, $newProject, ['completed_at' => $end->toDateString(), 'planned_start_date' => $start->toDateString(), 'data_source' => 'rule_based_ingested']);
                    if (isset($donorRow->snapshot_id)) {
                        $planningSpending = ($source['cohort']['strategy'] ?? '') === PlanningSpendingFeatures::STRATEGY;
                        $fraction = min(.99, max(0, (float) ($donorRow->elapsed_days ?? 0) / ($planningSpending ? max(1, (float) $donorRow->planned_duration_days) : $donorDays)));
                        $asOf = $start->addDays(min($days - 1, (int) floor(($planningSpending ? $plannedDays : $days) * $fraction)));
                        $row = array_replace($row, $this->snapshot($direct, $movements, $asOf, $start, $plannedDays, $budget, (float) $donorRow->completion_percentage));
                        $row['snapshot_id'] = $id.'-S'.$donorRow->snapshot_id;
                        $row['actual_cost'] = max(0, $final / 100 - $row['fin_total_expense']);
                        $row['reconciled_final_cost'] = $final / 100;
                        $row = array_replace($row, $planningSpending ? PlanningSpendingFeatures::build($row) : app(ProjectCostFeatureBuilder::class)->build($row)['values']);
                        if ($planningSpending) {
                            $row['observation_basis'] = 'record_dates';
                            foreach (ProjectCostFeatureBuilder::FEATURE_NAMES as $key) {
                                if (! in_array($key, PlanningSpendingFeatures::FEATURES, true)) {
                                    unset($row[$key]);
                                }
                            }
                            unset($row['completion_percentage']);
                        }
                    }
                    $write('training-observations', $row);
                }
            }
            foreach ($handles as $handle) {
                gzclose($handle);
            }
            $handles = [];
            $manifest = ['schema_version' => 1, 'created_at' => now()->toIso8601String(), 'method' => 'rule_based_construction_ledgers_v1',
                'seed' => $seed, 'live_source' => $source['source'], 'database_fingerprint' => ProjectCostAugmentationDataset::fingerprint($records),
                'database_training_project_ids' => $split['training'], 'database_test_project_ids' => $split['test'], 'database_test_ratio' => .20,
                'dummy_projects' => $projects, 'ingested_projects' => $projects, 'project_id_prefix' => 'ingested-', 'year_range' => [2019, today()->year], 'maximum_duration_months' => 7, 'business_rules_version' => 2, 'expenses_per_project' => $expenses, 'inventory_transactions_per_project' => $transactions,
                'item_records' => count($items), 'prediction_strategy' => $source['cohort']['strategy'],
                'database_source_provenance' => $records->groupBy('data_source')->map(fn ($r) => $r->pluck('project_id')->unique()->count())->all(),
                'test_scope' => 'database_only; no ingested observations',
                'cross_validation_policy' => 'Include ingested projects only when their single donor belongs to the fold training partition.',
                'business_rules' => ['overrun_scenario_probability' => .20, 'controlled_cost_ratio_range' => [.85, .995],
                    'delay_rework_cost_ratio_range' => [1.01, 1.18], 'historical_scenario_annual_price_factor' => 1.025,
                    'package_quantity_policy' => 'Whole catalogue packages; sand/gravel in quarter cubic metres; cost labels reconcile after package rounding.',
                    'phase_policy' => 'Bill-of-quantity allocation, then dated lots: structure, services, finishing. Interior work emphasizes finishing; warehouses omit domestic cabinetry/quartz. Durable tools are not repeatedly consumed.',
                    'payroll_policy' => 'Weekly expense batches; 70% labor entries, 20% equipment entries, 10% delivery/site overhead entries. Crew size is planned from budget, duration and a PHP650-950/day scenario wage adjusted by the historical price scenario, not from final outcomes.'],
                'limitations' => ['Requested fixed event counts are a presentation constraint, not observed transaction-frequency evidence.', 'Historical price factors are rule-based scenarios anchored to the live catalogue, not historical market observations.',
                    'A larger ingested dataset adds no independent company outcomes; performance must be evaluated on database projects.',
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

    /** Allocate a bill of quantities first, then split it into delivery/withdrawal lots. */
    private function materialLedger(array $catalogue, string $kind, float $budget, float $priceFactor, int $total, int $pairs, string $id, CarbonImmutable $start, int $days): array
    {
        $items = array_values(array_filter($catalogue, function ($item) use ($kind) {
            $name = $item['item_name'];
            // Durable site tools are covered by equipment costs, not consumed repeatedly.
            if (preg_match('/hammer|mallet|pliers|tape measure|drill bit/i', $name)) {
                return false;
            }

            return ! ($kind === 'warehouse' && preg_match('/quartz|cabinet|drawer|particle|mdf|fluted|lockset|hinge/i', $name));
        }));
        usort($items, fn ($a, $b) => [$this->itemPhase($a['item_name']), $a['item_id']] <=> [$this->itemPhase($b['item_name']), $b['item_id']]);
        // A tiny requested test ledger may not have a lot for every catalogue item.
        $items = array_slice($items, 0, min(count($items), $pairs));
        $weights = [];
        foreach ($items as $item) {
            $weights[] = (float) $item['unit_price'] * $this->quantityRate($item['item_name'], $kind) * $this->draw(.85, 1.15);
        }
        $plans = [];
        foreach ($this->apportion($total, $weights) as $i => $allocation) {
            $item = $items[$i];
            $price = round((float) $item['unit_price'] * $priceFactor * $this->draw(.95, 1.05), 2);
            $step = preg_match('/sand|gravel/i', $item['item_name']) ? .25 : 1;
            $units = max(1, (int) round($allocation / 100 / $price / $step));
            $plans[] = ['item' => $item, 'price' => $price, 'step' => $step, 'units' => $units, 'lots' => 1, 'phase' => $this->itemPhase($item['item_name'])];
        }
        if (array_sum(array_column($plans, 'units')) < $pairs) {
            throw new RuntimeException('Realistic package quantities cannot support the requested number of withdrawals.');
        }
        // More frequently used materials receive more lots; high-value fixtures do not.
        for ($count = count($plans); $count < $pairs; $count++) {
            $chosen = null;
            $score = -1;
            foreach ($plans as $i => $plan) {
                if ($plan['lots'] < $plan['units'] && $score < $plan['units'] / $plan['lots']) {
                    $chosen = $i;
                    $score = $plan['units'] / $plan['lots'];
                }
            }
            $plans[$chosen]['lots']++;
        }
        $lots = [];
        foreach ($plans as $plan) {
            foreach ($this->apportion($plan['units'], array_fill(0, $plan['lots'], 1)) as $units) {
                $lots[$plan['phase']][] = $plan + ['quantity' => $units * $plan['step']];
            }
        }
        $boundaries = $kind === 'interior' ? [0, .25, .5, 1] : [0, $kind === 'warehouse' ? .65 : .55, .75, 1];
        $movements = [];
        $number = 0;
        foreach (['structure', 'services', 'finishing'] as $phaseIndex => $phase) {
            $phaseLots = $lots[$phase] ?? [];
            // Avoid grouping all withdrawals of one item on a single day.
            $phaseLots = $this->random->shuffleArray($phaseLots);
            foreach ($phaseLots as $i => $lot) {
                $fraction = $boundaries[$phaseIndex] + ($boundaries[$phaseIndex + 1] - $boundaries[$phaseIndex]) * ($i + 1) / (count($phaseLots) + 1);
                $day = (int) round($fraction * $days);
                $number++;
                $amount = round($lot['quantity'] * $lot['price'], 2);
                $base = ['project_id' => $id, 'item_id' => $lot['item']['item_id'], 'quantity' => $lot['quantity'], 'unit_cost' => $lot['price'],
                    'amount' => $amount, 'lot_id' => $id.'-L'.$number, 'construction_phase' => $phase];
                $movements[] = $base + ['transaction_id' => $id.'-I'.$number, 'transaction_type' => 'IN', 'transaction_date' => $start->addDays(max(0, $day - $this->random->getInt(1, 4)))->toDateString()];
                $movements[] = $base + ['transaction_id' => $id.'-O'.$number, 'transaction_type' => 'OUT', 'transaction_date' => $start->addDays($day)->toDateString()];
            }
        }

        return $movements;
    }

    /** Relative bill-of-quantity rates; calibrated to the live catalogue, not invoices. */
    private function quantityRate(string $name, string $kind): float
    {
        $structure = $kind === 'interior' ? .35 : 1;

        return match (true) {
            preg_match('/portland|^cement$/i', $name) === 1 => 300 * $structure,
            preg_match('/sand|gravel/i', $name) === 1 => 25 * $structure,
            preg_match('/deformed|^steel$/i', $name) === 1 => 150 * $structure,
            preg_match('/hollow block/i', $name) === 1 => 1200 * $structure,
            preg_match('/tie wire|common nail|concrete nail/i', $name) === 1 => 2 * $structure,
            preg_match('/quartz/i', $name) === 1 => .6,
            preg_match('/plywood|phenolic|particle|mdf/i', $name) === 1 => 15,
            preg_match('/thhn/i', $name) === 1 => 2,
            preg_match('/latex.*16l/i', $name) === 1 => 6,
            preg_match('/paint|latex|enamel|putty|thinner|waterproofing/i', $name) === 1 => 8,
            preg_match('/ceiling panel/i', $name) === 1 => 80,
            preg_match('/adhesive|skimcoat/i', $name) === 1 => 20,
            preg_match('/hinge|cabinet handle|cabinet leg/i', $name) === 1 => 30,
            preg_match('/pipe|conduit|furring|channel|moulding/i', $name) === 1 => 25,
            default => 12,
        };
    }

    private function projectKind(string $name): string
    {
        return preg_match('/interior|renovation|roof replacement/i', $name) ? 'interior' : (preg_match('/warehouse|workshop|storage/i', $name) ? 'warehouse' : 'building');
    }

    private function itemPhase(string $name): string
    {
        if (preg_match('/conduit|wire 2|wire 3|switch|outlet|junction|utility box|panel board|downlight|pipe|elbow|wye|trap|drain|faucet/i', $name)) {
            return 'services';
        }
        if (preg_match('/paint|latex|enamel|putty|thinner|adhesive|skimcoat|hinge|cabinet|drawer|quartz|ceiling|moulding|fluted|silicone|lockset|particle|mdf|suspension|flange|channel|furring|sandpaper|masking/i', $name)) {
            return 'finishing';
        }

        return 'structure';
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
