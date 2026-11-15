<?php

namespace App\Services\ML;

use App\Services\MLService;
use App\Services\ProjectOverrunEvaluation;
use Illuminate\Support\Facades\File;
use RuntimeException;

/** Synthetic sensitivity checks, kept separate from operational accuracy evidence. */
class ProjectCostStressEvaluation extends MLService
{
    public function run(string $sourcePath, int $count = 300): array
    {
        if ($count < 100 || $count > 5000) {
            throw new RuntimeException('Use between 100 and 5000 scenarios.');
        }
        $source = json_decode(gzdecode(File::get($sourcePath)), true, 512, JSON_THROW_ON_ERROR);
        $cohort = app(PlanningSpendingCohort::class)->build($source);
        $records = collect($cohort['records'])->map(fn ($row) => (object) $row);
        PlanningSpendingFeatures::validate(array_replace($cohort, ['records' => $records]));
        $split = ProjectCostAugmentationDataset::split($records);
        $donors = $records->whereIn('project_id', $split['training'])->sortBy('captured_at')->values();
        if ($donors->isEmpty()) {
            throw new RuntimeException('No training donors available.');
        }
        $rows = collect();
        $ratios = [.78, .9, .97, .995, 1.005, 1.03, 1.1, 1.22];
        for ($i = 0; $i < $count; $i++) {
            $row = clone $donors[$i % $donors->count()];
            $donorId = $row->project_id;
            // Change approved budgets around fixed known costs, covering boundary cases.
            // The outcome is defined by these scenario assumptions, never by model output.
            $scale = [.5, 1, 1.5, 2][$i % 4];
            $spent = (float) $row->fin_total_expense * $scale;
            $final = ((float) $row->actual_cost + (float) $row->fin_total_expense) * $scale;
            $row->budget = $final * $ratios[intdiv($i, 4) % count($ratios)];
            $row->fin_total_expense = $spent;
            $row->actual_cost = max(0, $final - $spent);
            $row->reconciled_final_cost = $final;
            $row->budget_used_fraction = $spent / $row->budget;
            foreach (['cost_burn_rate_30d', 'inventory_cost_burn_rate_30d', 'fin_material_expense',
                'fin_labor_expense', 'fin_equipment_expense', 'fin_other_expense', 'direct_expense_amount_7d',
                'direct_expense_amount_30d', 'valued_stock_out_cost_7d', 'valued_stock_out_cost_30d'] as $field) {
                $row->{$field} *= $scale;
            }
            $row->project_id = 'stress-'.($i + 1);
            $row->snapshot_id = 'stress-snapshot-'.($i + 1);
            $row->donor_project_id = $donorId;
            $row->data_source = 'synthetic_sensitivity_scenario';
            $rows->push($row);
        }
        $report = [
            'generated_at' => now()->toIso8601String(), 'schema_version' => 1,
            'scope' => 'Synthetic budget and project-size sensitivity evaluation; not operational accuracy or independent validation.',
            'method' => 'Deterministic training-donor scenarios; final costs and spending are scaled together, approved budgets vary independently from 78% to 122% of scenario final costs.',
            'scenario_count' => $count, 'source_sha256' => hash_file('sha256', $sourcePath),
            'active_model_sha256' => hash_file('sha256', $this->modelPath),
            'donor_project_ids' => $donors->pluck('project_id')->unique()->values()->all(),
            'excluded_holdout_project_ids' => $split['test'],
            'holdout_donor_overlap' => array_values(array_intersect($donors->pluck('project_id')->all(), $split['test'])),
            'used_for_training' => false, 'active_model_changed' => false,
            'active_model' => $this->evaluateSavedActiveEstimator($rows),
        ];
        $path = $this->modelPath.'.optimized-candidate';
        $activePair = (new CostModelStore($this->modelPath, 10))->load();
        $detector = app(ModelPromotionService::class)->loadDetector($this->modelPath.'.detector.json', $activePair['metadata']['model_sha256'] ?? null);
        if ($detector !== null) {
            $outcomes = $this->classifierOutcomes($detector['model'], $rows,
                $detector['metadata']['feature_names'], (float) $detector['metadata']['threshold']);
            $report['active_detector'] = app(ProjectOverrunEvaluation::class)->evaluateClasses($outcomes['predictions'], $outcomes['actuals']);
        }
        $pair = (new CostModelStore($path, 10))->load();
        if ($pair !== null) {
            $report['candidate_model'] = ['status' => 'evaluated', 'model_type' => $pair['metadata']['model_type'],
                'evaluation' => $this->evaluateModel($pair['model'], $pair['metadata']['transformer'], $rows)];
        }
        File::put(storage_path('app/ml-stress-scenarios.json'), json_encode($rows->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        File::put(storage_path('app/ml-stress-evaluation.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report;
    }
}
