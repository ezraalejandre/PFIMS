<?php

namespace Tests\Feature;

use App\Services\ML\ProjectCostAugmentationAudit;
use App\Services\ML\ProjectCostAugmentationDataset;
use App\Services\ML\ProjectCostRuleBasedGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RuleBasedIngestedDataTest extends TestCase
{
    public function test_business_calendar_ledger_rules_and_holdout_isolation(): void
    {
        $fixture = new \ReflectionMethod(ProjectCostAugmentationTest::class, 'source');
        $source = $fixture->invoke(new ProjectCostAugmentationTest('test_business_calendar_ledger_rules_and_holdout_isolation'));
        $source['inventory_item_tbl'][] = ['item_id' => 3, 'item_name' => 'Claw Hammer 16oz', 'unit_price' => 225];
        $source['inventory_item_tbl'][] = ['item_id' => 4, 'item_name' => 'PVC Electrical Conduit 20mm x 3m', 'unit_price' => 65];
        $source['inventory_item_tbl'][] = ['item_id' => 5, 'item_name' => 'Davies Flat Latex White 16L', 'unit_price' => 2450];
        $root = storage_path('framework/testing/rule-ingested-'.uniqid());
        try {
            $first = $root.'/first';
            $second = $root.'/second';
            $manifest = (new ProjectCostRuleBasedGenerator)->generate($source, $first, 160, 30, 100, 20261009);
            $this->assertSame('rule_based_construction_ledgers_v1', $manifest['method']);
            $this->assertSame(160, $manifest['ingested_projects']);
            $this->assertSame(.20, $manifest['database_test_ratio']);
            $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $manifest['database_training_project_ids']);
            $this->assertSame([9, 10], $manifest['database_test_project_ids']);
            $this->assertTrue((new ProjectCostAugmentationAudit)->audit($first)['valid']);
            $projects = iterator_to_array(ProjectCostAugmentationDataset::read($first.'/projects.jsonl.gz'));
            $years = [];
            $overruns = 0;
            $concurrent = false;
            foreach ($projects as $i => $project) {
                $start = CarbonImmutable::parse($project['start_date']);
                $years[$start->year] = true;
                $this->assertLessThanOrEqual($start->addMonthsNoOverflow(7)->toDateString(), $project['actual_end_date']);
                $this->assertLessThanOrEqual(today()->toDateString(), $project['actual_end_date']);
                $this->assertSame('rule_based_ingested', $project['data_source']);
                $overruns += $project['actual_cost'] > $project['budget'] ? 1 : 0;
                foreach (array_slice($projects, 0, $i) as $other) {
                    $concurrent = $concurrent || ($other['start_date'] < $project['actual_end_date'] && $project['start_date'] < $other['actual_end_date']);
                }
            }
            $this->assertSame(range(2019, today()->year), array_keys($years));
            $this->assertTrue($concurrent);
            $this->assertGreaterThan(0, $overruns);
            $this->assertLessThan(160, $overruns);
            foreach (ProjectCostAugmentationDataset::read($first.'/inventory-transactions.jsonl.gz') as $event) {
                $this->assertNotSame(3, $event['item_id']);
                $this->assertSame((float) round($event['quantity']), (float) $event['quantity']);
                $this->assertContains($event['construction_phase'], ['structure', 'services', 'finishing']);
            }
            // Test outcomes cannot change any generated training bytes.
            foreach ([8, 9] as $index) {
                $source['cohort']['records'][$index]['budget'] = 999999999;
                $source['cohort']['records'][$index]['actual_cost'] = 1;
                $source['fin_expense_tbl'][$index]['amount'] = 999999999;
            }
            (new ProjectCostRuleBasedGenerator)->generate($source, $second, 160, 30, 100, 20261009);
            foreach (['projects', 'expenses', 'inventory-transactions', 'training-observations', 'items'] as $file) {
                $this->assertSame(gzdecode(file_get_contents($first.'/'.$file.'.jsonl.gz')), gzdecode(file_get_contents($second.'/'.$file.'.jsonl.gz')));
            }
            $progressSource = $fixture->invoke(new ProjectCostAugmentationTest('test_business_calendar_ledger_rules_and_holdout_isolation'));
            foreach ($progressSource['cohort']['records'] as &$row) {
                $row['snapshot_id'] = 'S'.$row['project_id'];
                $row['elapsed_days'] = 15;
                $row['completion_percentage'] = 25;
                $row['reconciled_final_cost'] = $row['actual_cost'];
                $row['fin_total_expense'] = $row['actual_cost'] * .25;
                $row['actual_cost'] *= .75;
            }
            unset($row);
            $progressSource['cohort']['strategy'] = 'progress_snapshot_model';
            (new ProjectCostRuleBasedGenerator)->generate($progressSource, $root.'/progress', 8, 30, 100, 20261009);
            $this->assertTrue((new ProjectCostAugmentationAudit)->audit($root.'/progress')['valid']);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
