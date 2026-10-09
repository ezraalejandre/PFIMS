<?php

namespace Tests\Feature;

use App\Services\ML\CostModelStore;
use App\Services\ML\PlanningSpendingCohort;
use App\Services\ML\PlanningSpendingFeatures;
use App\Services\ML\ProjectCostAugmentationAudit;
use App\Services\ML\ProjectCostAugmentationDataset;
use App\Services\ML\ProjectCostRuleBasedGenerator;
use App\Services\MLService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlanningSpendingFeaturesTest extends TestCase
{
    private function source(): array
    {
        $s = ['source' => ['host' => 'srv603.hstgr.io', 'database' => 'u822802132_pfims', 'captured_at' => '2026-10-09'],
            'project_tbl' => [], 'budgets_tbl' => [], 'fin_expense_tbl' => [], 'inventory_transaction_tbl' => [], 'inventory_cost_allocation_tbl' => [],
            'inventory_item_tbl' => [['item_id' => 1, 'item_name' => 'Cement', 'unit_price' => 250]], 'fin_expense_category_tbl' => []];
        foreach (range(1, 12) as $id) {
            $start = sprintf('2024-%02d-01', $id);
            $end = CarbonImmutable::parse($start)->addMonths(4)->toDateString();
            $cost = 900000 + $id * 10000;
            $s['project_tbl'][] = ['project_id' => $id, 'project_name' => 'Residence '.$id, 'start_date' => $start, 'estimated_end_date' => $end, 'actual_end_date' => $end, 'worker_count' => 5, 'status' => 'Completed', 'data_source' => 'operational'];
            $s['budgets_tbl'][] = ['budget_id' => $id, 'project_id' => $id, 'budget_amount' => 1000000, 'actual_amount' => $cost];
            foreach ([0, 30, 60, 90] as $day) {
                $s['fin_expense_tbl'][] = ['project_id' => $id, 'inventory_transaction_id' => null, 'expense_date' => CarbonImmutable::parse($start)->addDays($day)->toDateString(), 'amount' => $cost / 4, 'project_cost_component' => 'labor', 'fin_category_id' => null, 'created_at' => '2026-10-09'];
            }
        }

        return $s;
    }

    public function test_features_do_not_depend_on_physical_progress(): void
    {
        $input = ['budget' => 1000, 'duration_months' => 4, 'worker_count' => 5, 'elapsed_days' => 30, 'planned_duration_days' => 120, 'fin_total_expense' => 250, 'direct_expense_amount_30d' => 200, 'valued_stock_out_cost_30d' => 50];
        $this->assertSame(PlanningSpendingFeatures::build($input + ['completion_percentage' => 10]), PlanningSpendingFeatures::build($input + ['completion_percentage' => 90]));
        $this->assertNotContains('completion_percentage', PlanningSpendingFeatures::FEATURES);
        $this->assertSame(90., PlanningSpendingFeatures::build($input)['remaining_planned_days']);
    }

    public function test_record_dates_ignore_posting_times_and_later_costs_only_change_the_label(): void
    {
        $s = $this->source();
        $builder = new PlanningSpendingCohort;
        $first = $builder->build($s);
        $s['fin_expense_tbl'][0]['created_at'] = '2030-01-01';
        $this->assertEquals($first, $builder->build($s));
        $before = (array) $first['records']->first();
        $s['fin_expense_tbl'][3]['amount'] += 10000;
        $s['budgets_tbl'][0]['actual_amount'] += 10000;
        $after = (array) $builder->build($s)['records']->first();
        foreach (PlanningSpendingFeatures::FEATURES as $name) {
            $this->assertSame($before[$name], $after[$name]);
        }
        $this->assertSame($before['actual_cost'] + 10000, $after['actual_cost']);
        $this->assertSame(36, $first['records']->count());
    }

    public function test_inventory_uses_withdrawal_dates_and_excludes_linked_purchase_expenses(): void
    {
        $s = $this->source();
        $s['inventory_transaction_tbl'][] = ['inventory_transaction_id' => 1, 'project_id' => 1, 'transaction_type' => 'OUT', 'transaction_date' => '2024-01-15'];
        $s['inventory_cost_allocation_tbl'][] = ['out_transaction_id' => 1, 'allocated_amount' => 100, 'valuation_status' => 'valued', 'allocated_at' => '2026-10-09'];
        $s['fin_expense_tbl'][] = ['project_id' => 1, 'inventory_transaction_id' => 1, 'expense_date' => '2024-01-15', 'amount' => 100];
        $s['budgets_tbl'][0]['actual_amount'] += 100;
        $row = (new PlanningSpendingCohort)->build($s)['records']->first();
        $this->assertEquals(100, $row->fin_material_expense);
        $this->assertEquals(455100, $row->fin_total_expense);
    }

    public function test_effective_budget_date_is_used_and_invalid_ledgers_are_excluded(): void
    {
        $s = $this->source();
        $s['project_budget_history'] = [['project_id' => 1, 'history_id' => 1, 'effective_at' => '2024-01-01', 'budget_amount' => 800000]];
        $c = (new PlanningSpendingCohort)->build($s);
        $this->assertEquals(800000, $c['records']->first()->budget);
        $s['budgets_tbl'][0]['actual_amount'] += 1;
        $c = (new PlanningSpendingCohort)->build($s);
        $this->assertFalse($c['records']->contains('project_id', 1));
        $this->assertArrayHasKey(1, $c['snapshot_readiness']['excluded_projects']);
    }

    public function test_linear_retains_all_correlated_planning_spending_inputs(): void
    {
        config(['ml.planning_spending_enabled' => true]);
        $cohort = (new PlanningSpendingCohort)->build($this->source());
        PlanningSpendingFeatures::validate($cohort);
        $service = new MLService(loadModel: false);
        [$model,$transformer] = (new \ReflectionMethod($service, 'buildServableRegressionModel'))->invoke($service, 'ridge_linear_regression', ['alpha' => .1], $cohort['records'], PlanningSpendingFeatures::FEATURES);
        $this->assertSame(PlanningSpendingFeatures::FEATURES, $transformer['selected_feature_names']);
        $this->assertCount(16, $transformer['selected_feature_indexes']);
        $this->assertSame('remaining_cost_fraction_of_budget', $transformer['target_scaling']);
    }

    public function test_ingested_observations_reconcile_and_do_not_use_progress(): void
    {
        $source = $this->source();
        $cohort = (new PlanningSpendingCohort)->build($source);
        $source['cohort'] = $cohort;
        $source['cohort']['records'] = $cohort['records']->map(fn ($r) => (array) $r)->all();
        $path = storage_path('framework/testing/planning-spending-'.uniqid());
        try {
            (new ProjectCostRuleBasedGenerator)->generate($source, $path, 12, 30, 100, 123);
            $this->assertTrue((new ProjectCostAugmentationAudit)->audit($path)['valid']);
            foreach (ProjectCostAugmentationDataset::read($path.'/training-observations.jsonl.gz') as $row) {
                $this->assertArrayNotHasKey('completion_percentage', $row);
                $this->assertSame('record_dates', $row['observation_basis']);
                $this->assertEqualsWithDelta($row['reconciled_final_cost'], $row['actual_cost'] + $row['fin_total_expense'], .01);
            }
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_progress_model_is_not_compared_using_invented_historical_progress(): void
    {
        config(['ml.planning_spending_enabled' => true]);
        $rows = (new PlanningSpendingCohort)->build($this->source())['records'];
        $path = storage_path('framework/testing/planning-comparison-'.uniqid());
        try {
            $service = new MLService($path, false);
            [$model, $transformer] = (new \ReflectionMethod($service, 'buildServableRegressionModel'))->invoke($service, 'ridge_linear_regression', ['alpha' => .1], $rows, PlanningSpendingFeatures::FEATURES);
            (new CostModelStore($path, 10))->save($model, ['schema_version' => 10, 'model_type' => 'ridge_linear_regression', 'prediction_strategy' => 'progress_snapshot_model', 'transformer' => $transformer]);
            $comparison = (new \ReflectionMethod($service, 'evaluateSavedActiveEstimator'))->invoke($service, $rows);
            $this->assertSame('unavailable', $comparison['status']);
            $this->assertStringContainsString('historical physical progress', $comparison['message']);
            $this->assertNull($comparison['evaluation']);
        } finally {
            File::delete([$path, $path.'.meta.json']);
        }
    }

    public function test_current_spending_uses_record_dates_and_supplies_all_prediction_activity_fields(): void
    {
        $tables = ['fin_expense_category_tbl', 'fin_expense_tbl', 'inventory_transaction_tbl', 'inventory_cost_allocation_tbl'];
        try {
            Schema::create($tables[0], function (Blueprint $t) {
                $t->integer('fin_category_id');
                $t->string('category_name');
            });
            Schema::create($tables[1], function (Blueprint $t) {
                $t->integer('project_id');
                $t->integer('inventory_transaction_id')->nullable();
                $t->date('expense_date');
                $t->decimal('amount', 12, 2);
                $t->string('project_cost_component');
                $t->timestamp('created_at')->nullable();
            });
            Schema::create($tables[2], function (Blueprint $t) {
                $t->integer('inventory_transaction_id');
                $t->integer('project_id');
                $t->string('transaction_type');
                $t->date('transaction_date');
            });
            Schema::create($tables[3], function (Blueprint $t) {
                $t->integer('out_transaction_id');
                $t->string('valuation_status');
                $t->decimal('allocated_amount', 12, 2);
            });
            DB::table($tables[1])->insert([
                ['project_id' => 1, 'inventory_transaction_id' => null, 'expense_date' => today()->subDays(2)->toDateString(), 'amount' => 100, 'project_cost_component' => 'labor', 'created_at' => now()->addYears(1)],
                ['project_id' => 1, 'inventory_transaction_id' => null, 'expense_date' => today()->addDay()->toDateString(), 'amount' => 999, 'project_cost_component' => 'labor', 'created_at' => now()],
                ['project_id' => 1, 'inventory_transaction_id' => 1, 'expense_date' => today()->toDateString(), 'amount' => 50, 'project_cost_component' => 'material', 'created_at' => now()],
            ]);
            DB::table($tables[2])->insert([
                ['inventory_transaction_id' => 1, 'project_id' => 1, 'transaction_type' => 'OUT', 'transaction_date' => today()->toDateString()],
                ['inventory_transaction_id' => 2, 'project_id' => 1, 'transaction_type' => 'OUT', 'transaction_date' => today()->toDateString()],
                ['inventory_transaction_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT', 'transaction_date' => today()->addDay()->toDateString()],
            ]);
            DB::table($tables[3])->insert(['out_transaction_id' => 1, 'valuation_status' => 'valued', 'allocated_amount' => 50]);
            $input = (new PlanningSpendingCohort)->forecastInputs(1, today()->subDays(30), today()->addDays(90));
            $this->assertSame(150., $input['fin_total_expense']);
            $this->assertSame(1, $input['direct_expense_count_30d']);
            $this->assertSame(1, $input['stock_out_count_30d']);
            $this->assertSame(1, $input['unvalued_stock_out_count']);
            $this->assertFalse($input['cost_coverage_complete']);
            $this->assertSame(30, $input['elapsed_days']);
        } finally {
            foreach (array_reverse($tables) as $table) {
                Schema::dropIfExists($table);
            }
        }
    }
}
