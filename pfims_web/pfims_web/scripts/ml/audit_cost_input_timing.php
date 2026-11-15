<?php
/** Read-only evidence check. Do not rewrite historical dates to make them pass. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
App\Services\ML\LocalImprovementWorkbench::assertLocal();
use Illuminate\Support\Facades\DB;
$root = storage_path('app/ml-improvement');
$source = json_decode(file_get_contents($root.'/source.json'), true, 512, JSON_THROW_ON_ERROR);
$portable = json_decode(file_get_contents($root.'/portable-cost.json'), true, 512, JSON_THROW_ON_ERROR);
$pair = (new App\Services\ML\CostModelStore(storage_path('app/ml_model.phpml'), 10))->load();
foreach ($portable['fixtures'] as $fixture) {
    if (abs($pair['model']->predict($fixture['features']) - $fixture['expected']) > 1e-10) {
        throw new RuntimeException('Audit candidate differs from active model.');
    }
}
$ids = array_values(array_unique(array_column($source['records'], 'project_id')));
$expenses = DB::table('fin_expense_tbl')->whereIn('project_id', $ids)->whereNull('inventory_transaction_id')->get()->groupBy('project_id');
$movements = DB::table('inventory_transaction_tbl')->whereIn('project_id', $ids)->where('transaction_type', 'OUT')->get()->groupBy('project_id');
$allocations = DB::table('inventory_cost_allocation_tbl')->whereIn('project_id', $ids)->get()->groupBy('out_transaction_id');
$history = DB::table('project_budget_history')->whereIn('project_id', $ids)->get()->groupBy('project_id');
$counts = ['checkpoints' => count($source['records']), 'no_contemporaneously_recorded_budget' => 0,
    'late_or_unknown_expense_posting' => 0, 'late_or_unknown_inventory_posting_or_valuation' => 0,
    'strictly_eligible_checkpoints' => 0];
foreach ($source['records'] as $row) {
    $at = $row['captured_at'];
    $known = fn ($date) => !empty($date) && substr((string) $date, 0, 10) <= $at;
    $budgetKnown = $history->get($row['project_id'], collect())->contains(fn ($h) => $known($h->effective_at) && $known($h->recorded_at));
    $expenseUnknown = $expenses->get($row['project_id'], collect())->contains(fn ($e) => $known($e->expense_date) && !$known($e->created_at ?? null));
    $inventoryUnknown = $movements->get($row['project_id'], collect())->contains(function ($m) use ($known, $allocations) {
        if (!$known($m->transaction_date)) return false;
        $values = $allocations->get($m->inventory_transaction_id, collect());
        return !$known($m->recorded_at ?? null) || $values->isEmpty() || $values->contains(fn ($a) => !$known($a->allocated_at ?? null));
    });
    $counts['no_contemporaneously_recorded_budget'] += !$budgetKnown;
    $counts['late_or_unknown_expense_posting'] += $expenseUnknown;
    $counts['late_or_unknown_inventory_posting_or_valuation'] += $inventoryUnknown;
    $counts['strictly_eligible_checkpoints'] += $budgetKnown && !$expenseUnknown && !$inventoryUnknown;
}
$auditPath = storage_path('app/ml-r-squared-audit.json');
$audit = json_decode(file_get_contents($auditPath), true, 512, JSON_THROW_ON_ERROR);
if (!hash_equals($audit['model_sha256'], hash_file('sha256', storage_path('app/ml_model.phpml')))
    || !hash_equals($audit['source_sha256'], hash_file('sha256', $root.'/source.json'))) throw new RuntimeException('Stale audit.');
$audit['input_timing'] = $counts;
$audit['checks']['active_model_prediction_parity'] = true;
$audit['generated_at'] = now()->toIso8601String();
file_put_contents($auditPath, json_encode($audit, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($counts, JSON_PRETTY_PRINT).PHP_EOL;
