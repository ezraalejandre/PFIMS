<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Logical presentation scenarios, never reconstructed operational observations. */
class ProjectCostPresentationPlan
{
    public function build(array $references, array $items, int $count = 48): array
    {
        if ($references === [] || $items === [] || $count < 6 || $count > 120 || $count % 6 !== 0) {
            throw new InvalidArgumentException('References, priced materials and a multiple of six scenarios are required.');
        }
        $projects = [];
        for ($index = 0; $index < $count; $index++) {
            $reference = $references[($index * 7) % count($references)];
            $outcome = ['material', 'within', 'small', 'material', 'within', 'material'][$index % 6];
            $factor = match ($outcome) {
                'material' => 1.065 + (($index * 11) % 23) * 0.007,
                'small' => 1.012 + (($index * 7) % 9) * 0.004,
                default => 0.925 + (($index * 3) % 10) * 0.007,
            };
            $budget = round((float) $reference['budget_amount'] * (0.86 + (($index * 13) % 29) / 100) / 5000) * 5000;
            $finalCents = (int) round($budget * $factor * 100);
            $plannedDays = max(75, (int) CarbonImmutable::parse($reference['start_date'])
                ->diffInDays(CarbonImmutable::parse($reference['estimated_end_date'])) + (($index % 5) - 2) * 7);
            $actualDays = (int) round($plannedDays * ($outcome === 'material'
                ? 0.98 + (($index * 3) % 11) * 0.025 : 0.95 + ($index % 7) * 0.025));
            $end = CarbonImmutable::parse('2026-09-20')->subDays(($count - 1 - $index) * 17);
            $start = $end->subDays($actualDays);
            $plannedEnd = $start->addDays($plannedDays);
            $name = preg_replace('/,\s*\d{4}-\d+\s*$/', '', $reference['project_name']);
            $project = [
                'project_name' => $name.', '.$end->year.'-'.(101 + $index),
                'client_name' => $reference['client_name'], 'project_manager' => $reference['project_manager'],
                'start_date' => $start->toDateString(), 'estimated_end_date' => $plannedEnd->toDateString(),
                'actual_end_date' => $end->toDateString(), 'worker_count' => max(6, (int) $reference['worker_count'] + $index % 9 - 4),
                'phase' => 'Complete', 'completion_percentage' => 100, 'status' => 'Completed',
                'data_source' => 'company_inspired_sample',
            ];
            $materialShare = 0.44 + ($index % 9) * 0.015;
            $laborShare = 0.25 + ($index % 5) * 0.012;
            $equipmentShare = 0.06 + ($index % 4) * 0.015;
            $componentCents = [
                'material' => (int) round($finalCents * $materialShare),
                'labor' => (int) round($finalCents * $laborShare),
                'equipment' => (int) round($finalCents * $equipmentShare),
            ];
            $componentCents['other'] = $finalCents - array_sum($componentCents);
            $waveCount = 14 + $index % 7;
            $weights = [];
            $alpha = 0.84 + (($index * 5) % 17) * 0.025;
            for ($wave = 1; $wave <= $waveCount; $wave++) {
                $weights[] = (($wave / $waveCount) ** $alpha - (($wave - 1) / $waveCount) ** $alpha)
                    * (0.82 + (($wave * 11 + $index * 7) % 31) / 100);
            }
            $distributed = [];
            foreach ($componentCents as $component => $cents) {
                $distributed[$component] = $this->distribute($cents, $weights);
            }
            $waves = [];
            for ($wave = 0; $wave < $waveCount; $wave++) {
                $day = max(1, min($actualDays, (int) round($actualDays * ($wave + 1) / $waveCount)
                    + (($wave + $index) % 5 - 2)));
                if ($wave === $waveCount - 1) {
                    $day = $actualDays;
                }
                $date = $start->addDays($day);
                $item = $items[($index + $wave * 3) % count($items)];
                $price = round((float) $item['unit_price'] * (0.94 + (($index + $wave) % 17) / 100), 2);
                $material = $distributed['material'][$wave] / 100;
                $quantity = max(1, (int) floor($material * (0.48 + ($wave % 5) * 0.06) / $price));
                if ($price <= 0 || $quantity * $price >= $material) {
                    throw new InvalidArgumentException('Reference prices are incompatible with the scenario material budget.');
                }
                $stockCost = round($quantity * $price, 2);
                $waves[] = [
                    'date' => $date->toDateString(), 'receipt_date' => $date->toDateString(),
                    'item_id' => (int) $item['item_id'], 'item_name' => $item['item_name'],
                    'quantity' => $quantity, 'unit_cost' => $price, 'inventory_cost' => $stockCost,
                    'direct' => [
                        'material' => round($material - $stockCost, 2),
                        'labor' => $distributed['labor'][$wave] / 100,
                        'equipment' => $distributed['equipment'][$wave] / 100,
                        'other' => $distributed['other'][$wave] / 100,
                    ],
                ];
            }
            $snapshots = [];
            foreach ([0.22, 0.55, 0.88] as $stage => $elapsedFraction) {
                $elapsed = (int) round($actualDays * $elapsedFraction);
                $asOf = $start->addDays($elapsed);
                $observed = array_values(array_filter($waves, fn ($wave) => $wave['date'] <= $asOf->toDateString()));
                $total = fn (string $component) => round(array_sum(array_map(fn ($wave) => $wave['direct'][$component], $observed)), 2);
                $stock = round(array_sum(array_column($observed, 'inventory_cost')), 2);
                $progress = [19 + $index % 11, 46 + ($index * 3) % 15, 81 + ($index * 7) % 12][$stage];
                $snapshot = [
                    'captured_at' => $asOf->format('Y-m-d').' 12:00:00', 'capture_reason' => 'presentation_scenario',
                    'planned_budget' => $budget, 'planned_duration_months' => max(1, (int) ceil($start->diffInMonths($plannedEnd))),
                    'worker_count' => $project['worker_count'], 'completion_percentage' => $progress,
                    'phase' => ['Structural Works', 'Finishing Works', 'Turnover'][$stage],
                    'elapsed_duration_months' => round($elapsed / 30, 2), 'finance_as_of_date' => $asOf->toDateString(),
                    'cumulative_material_expense' => round($total('material') + $stock, 2),
                    'cumulative_labor_expense' => $total('labor'), 'cumulative_equipment_expense' => $total('equipment'),
                    'cumulative_other_expense' => $total('other'),
                    'cumulative_total_expense' => round($total('material') + $stock + $total('labor') + $total('equipment') + $total('other'), 2),
                    'final_actual_cost' => $finalCents / 100, 'data_source' => 'company_inspired_sample',
                    'original_budget_amount' => $budget, 'budget_basis' => 'initial_recorded', 'capture_schema_version' => 3,
                    'planned_start_date' => $start->toDateString(), 'planned_end_date' => $plannedEnd->toDateString(),
                    'planned_duration_days' => $plannedDays, 'elapsed_days' => $elapsed,
                    'remaining_planned_days' => max(0, $plannedDays - $elapsed), 'days_past_planned_end' => max(0, $elapsed - $plannedDays),
                    'cost_coverage_complete' => true, 'valued_stock_out_cost' => $stock, 'unvalued_stock_out_count' => 0,
                ];
                foreach ([7, 30] as $window) {
                    $lower = $asOf->subDays($window - 1)->toDateString();
                    $recent = array_values(array_filter($observed, fn ($wave) => $wave['date'] >= $lower));
                    $snapshot['direct_expense_count_'.$window.'d'] = count($recent) * 4;
                    $snapshot['direct_expense_amount_'.$window.'d'] = round(array_sum(array_map(fn ($wave) => array_sum($wave['direct']), $recent)), 2);
                    $snapshot['stock_out_count_'.$window.'d'] = count($recent);
                    $snapshot['valued_stock_out_cost_'.$window.'d'] = round(array_sum(array_column($recent, 'inventory_cost')), 2);
                    if ($window === 30) {
                        $snapshot['stock_out_quantity_30d'] = array_sum(array_column($recent, 'quantity'));
                    }
                }
                $snapshots[] = $snapshot;
            }
            $projects[] = ['project' => $project, 'budget_amount' => $budget, 'final_cost' => $finalCents / 100,
                'expected_outcome' => $outcome, 'waves' => $waves, 'snapshots' => $snapshots];
        }

        $materials = array_map(fn ($item) => [
            'reference_item_id' => (int) $item['item_id'],
            'item_name' => mb_substr($item['item_name'], 0, 80).' - Site Supply',
            'inventory_category_id' => $item['inventory_category_id'] ?? null,
            'supplier_id' => $item['supplier_id'] ?? null, 'unit_id' => $item['unit_id'] ?? null,
            'unit_price' => $item['unit_price'], 'current_stock' => 0, 'reorder_level' => 0,
        ], $items);

        return ['version' => 1, 'projects' => $projects, 'materials' => $materials,
            'provenance' => 'company_inspired_sample',
            'observation_policy' => 'Scenario dates and costs are logical presentation inputs; they are not observations captured from past operational activity. Posting timestamps remain the actual import time.'];
    }

    private function distribute(int $cents, array $weights): array
    {
        $sum = array_sum($weights);
        $amounts = array_map(fn ($weight) => (int) floor($cents * $weight / $sum), $weights);
        $amounts[count($amounts) - 1] += $cents - array_sum($amounts);

        return $amounts;
    }
}
