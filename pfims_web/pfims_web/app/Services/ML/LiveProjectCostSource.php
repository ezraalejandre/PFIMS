<?php

namespace App\Services\ML;

use App\Services\MLService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Consistent read-only export; credentials stay in the configured connection. */
class LiveProjectCostSource
{
    public function export(string $connection, string $output): array
    {
        $previous = DB::getDefaultConnection();
        $database = DB::connection($connection);
        $host = $database->getConfig('host');
        $isLiveHost = $host === 'srv603.hstgr.io';
        $isHostedApp = parse_url(config('app.url'), PHP_URL_HOST) === 'gray-elk-934703.hostingersite.com';
        if ($database->getDriverName() !== 'mysql' || (! $isLiveHost && ! $isHostedApp)
            || $database->selectOne('SELECT DATABASE() AS name')->name !== 'u822802132_pfims') {
            throw new RuntimeException('The source connection must identify the live PFIMS database.');
        }
        if (is_file($output)) {
            throw new RuntimeException('Source export already exists; choose a new version path.');
        }
        $pdo = $database->getPdo();
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Export requires its own consistent read-only transaction.');
        }
        DB::setDefaultConnection($connection);
        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
            $source = ['source' => ['host' => 'srv603.hstgr.io', 'database' => 'u822802132_pfims', 'captured_at' => now()->toIso8601String()]];
            foreach (['project_tbl', 'budgets_tbl', 'fin_expense_tbl', 'fin_expense_category_tbl', 'inventory_item_tbl',
                'inventory_transaction_tbl', 'inventory_cost_allocation_tbl', 'ml_project_cost_snapshots'] as $table) {
                $source[$table] = DB::table($table)->orderByRaw('1')->get()->map(fn ($row) => (array) $row)->all();
            }
            $cohort = (new MLService(loadModel: false))->databaseTrainingCohort();
            $source['cohort'] = ['strategy' => $cohort['strategy'], 'feature_names' => $cohort['feature_names'],
                'records' => $cohort['records']->map(fn ($row) => (array) $row)->all(), 'snapshot_readiness' => $cohort['snapshot_readiness']];
            $pdo->rollBack();
            if (! is_dir(dirname($output)) && ! mkdir(dirname($output), 0700, true)) {
                throw new RuntimeException('Unable to create private source directory.');
            }
            $payload = gzencode(json_encode($source, JSON_THROW_ON_ERROR), 9);
            if (file_put_contents($output, $payload, LOCK_EX) !== strlen($payload)) {
                throw new RuntimeException('Incomplete source export.');
            }

            return ['source' => $source['source'], 'eligible_projects' => $cohort['records']->pluck('project_id')->unique()->count(),
                'strategy' => $cohort['strategy'], 'sha256' => hash_file('sha256', $output)];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            DB::setDefaultConnection($previous);
        }
    }
}
