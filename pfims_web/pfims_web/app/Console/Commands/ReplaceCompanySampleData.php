<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ReplaceCompanySampleData extends Command
{
    protected $signature = 'pfims:replace-company-sample-data {--file=database/data/evc-sample/dataset.json} {--apply : Commit the replacement (default is dry-run)}';

    protected $description = 'Validate and optionally replace scoped PFIMS company sample data';

    private array $tables = ['expense_category_tbl', 'inventory_category_tbl', 'supplier_tbl', 'unit_tbl', 'project_tbl', 'company_asset_tbl', 'company_bank_account_tbl', 'fin_expense_category_tbl', 'inventory_item_tbl', 'budgets_tbl', 'inventory_transaction_tbl', 'expense_tbl', 'fin_expense_tbl', 'fin_construction_bond_tbl', 'fin_receivable_payable_tbl', 'fin_equipment_expense_tbl', 'fin_equipment_rental_income_tbl', 'fin_cash_position_tbl', 'fin_project_contract_tbl'];

    public function handle(): int
    {
        if (app()->environment() !== 'local') {
            $this->error('This command is restricted to the local environment.');

            return self::FAILURE;
        }
        $path = base_path($this->option('file'));
        if (! is_file($path)) {
            $this->error("Dataset not found: {$path}");

            return self::FAILURE;
        }
        $doc = json_decode(file_get_contents($path), true);
        $rows = $doc['tables'] ?? null;
        if (! is_array($rows)) {
            $this->error('Dataset must contain tables object.');

            return self::FAILURE;
        }
        try {
            $this->validate($rows);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        foreach ($this->tables as $t) {
            $this->line(sprintf('%-38s %d rows', $t, count($rows[$t] ?? [])));
        }
        if (! $this->option('apply')) {
            $this->info('Dry run only. Re-run with --apply to mutate data.');

            return self::SUCCESS;
        }
        $stamp = now()->format('Ymd_His_u');
        $dir = "sample-backups/{$stamp}";
        Storage::makeDirectory($dir);
        $manifest = ['created_at' => now()->toIso8601String(), 'dataset' => hash_file('sha256', $path), 'tables' => []];
        DB::transaction(function () use ($rows, $dir, &$manifest) {
            $snapshotProjectIds = Schema::hasTable('ml_project_cost_snapshots')
                ? DB::table('project_tbl')->pluck('project_id')->all()
                : [];
            foreach ($this->tables as $t) {
                $old = DB::table($t)->lockForUpdate()->get()->map(fn ($r) => (array) $r)->all();
                $json = json_encode($old, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (! Storage::put("{$dir}/{$t}.json", $json)) {
                    throw new \RuntimeException('Backup failed: '.$t);
                }
                $manifest['tables'][$t] = ['count' => count($old), 'sha256' => hash('sha256', $json)];
            }
            if (Schema::hasTable('ml_project_cost_snapshots')) {
                $snapshots = DB::table('ml_project_cost_snapshots')
                    ->whereIn('project_id', $snapshotProjectIds)
                    ->lockForUpdate()->get()->map(fn ($r) => (array) $r)->all();
                $json = json_encode($snapshots, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                if (! Storage::put("{$dir}/ml_project_cost_snapshots.json", $json)) {
                    throw new \RuntimeException('Snapshot backup failed');
                }
                $manifest['tables']['ml_project_cost_snapshots'] = ['count' => count($snapshots), 'sha256' => hash('sha256', $json), 'scope' => 'project_ids_replaced_by_this_command'];
            }
            $notifications = DB::table('notifications_tbl')->whereIn('reference_type', ['project', 'item', 'fin_expense', 'inventory', 'inventory_transaction', 'expense', 'supplier', 'budget']);
            if (! Storage::put("{$dir}/notifications_tbl.json", json_encode($notifications->get(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Notification backup failed');
            }
            foreach (['ml_model.phpml', 'ml_model.phpml.meta.json'] as $file) {
                if (is_file(storage_path('app/'.$file)) && ! Storage::put("{$dir}/{$file}", file_get_contents(storage_path('app/'.$file)))) {
                    throw new \RuntimeException('ML backup failed');
                }
            }
            $manifest['status'] = 'backed_up';
            if (! Storage::put("{$dir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Manifest backup failed');
            }
            $notifications->delete();
            if (Schema::hasTable('ml_project_cost_snapshots') && $snapshotProjectIds !== []) {
                DB::table('ml_project_cost_snapshots')->whereIn('project_id', $snapshotProjectIds)->delete();
            }
            $delete = array_reverse($this->tables);
            foreach ($delete as $t) {
                DB::table($t)->delete();
            }
            foreach ($this->tables as $t) {
                foreach ($rows[$t] ?? [] as $row) {
                    DB::table($t)->insert($row);
                }
            }
            $this->reconcile();
        });
        $manifest['status'] = 'replacement_committed';
        Storage::put("{$dir}/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
        $this->info('Recoverable backup: '.Storage::path($dir));
        $this->warn('Replacement committed. ML retraining must be run separately by the deployment workflow.');

        return self::SUCCESS;
    }

    private function validate(array $rows): void
    {
        if (array_diff($this->tables, array_keys($rows)) || array_diff(array_keys($rows), $this->tables)) {
            throw new \RuntimeException('Dataset must explicitly contain exactly the scoped tables.');
        }
        foreach ($this->tables as $t) {
            foreach (Schema::getIndexes($t) as $index) {
                if (! $index['unique']) {
                    continue;
                }
                $seen = [];
                foreach ($rows[$t] as $row) {
                    $values = array_map(fn ($key) => $row[$key] ?? null, $index['columns']);
                    if (in_array(null, $values, true)) {
                        continue;
                    }
                    $key = json_encode($values);
                    if (isset($seen[$key])) {
                        throw new \RuntimeException('Duplicate key: '.$t.'.'.$index['name']);
                    }
                    $seen[$key] = true;
                }
            }
        }
        $fks = DB::select('SELECT TABLE_NAME child_table,COLUMN_NAME child_column,REFERENCED_TABLE_NAME parent_table,REFERENCED_COLUMN_NAME parent_column FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
        foreach ($fks as $fk) {
            if (! isset($rows[$fk->child_table])) {
                continue;
            }
            $ids = isset($rows[$fk->parent_table]) ? array_column($rows[$fk->parent_table], $fk->parent_column) : DB::table($fk->parent_table)->pluck($fk->parent_column)->all();
            foreach ($rows[$fk->child_table] as $row) {
                $v = $row[$fk->child_column] ?? null;
                if ($v !== null && ! in_array($v, $ids)) {
                    throw new \RuntimeException('Missing FK parent: '.$fk->child_table.'.'.$fk->child_column);
                }
            }
        }
        foreach (['project_tbl' => 'project_name', 'inventory_item_tbl' => 'item_name'] as $t => $column) {
            $names = array_map(fn ($row) => mb_strtolower(trim($row[$column])), $rows[$t]);
            if (count($names) !== count(array_unique($names))) {
                throw new \RuntimeException('Duplicate names: '.$t);
            }
        }
        $this->validateTotals($rows);
        foreach ($rows as $t => $records) {
            if (! in_array($t, $this->tables, true)) {
                throw new \RuntimeException("Unknown scoped table: {$t}");
            } if (! is_array($records)) {
                throw new \RuntimeException("Rows for {$t} must be an array");
            } $cols = Schema::getColumnListing($t);
            foreach ($records as $row) {
                $unknown = array_diff(array_keys($row), $cols);
                if ($unknown) {
                    throw new \RuntimeException("{$t} has unknown columns: ".implode(',', $unknown));
                }
            }
        }
        foreach ($this->tables as $t) {
            if (! Schema::hasTable($t)) {
                throw new \RuntimeException("Missing table: {$t}");
            }
        }
        $inbound = DB::select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN ('.implode(',', array_fill(0, count($this->tables), '?')).') AND TABLE_NAME NOT IN ('.implode(',', array_fill(0, count($this->tables), '?')).')', array_merge($this->tables, $this->tables));
        $inbound = array_values(array_filter($inbound, fn ($fk) => ! (
            $fk->TABLE_NAME === 'ml_project_cost_snapshots'
            && $fk->COLUMN_NAME === 'project_id'
            && $fk->REFERENCED_TABLE_NAME === 'project_tbl'
        )));
        if ($inbound) {
            throw new \RuntimeException('Unknown inbound FK outside scope: '.json_encode($inbound));
        }
        $this->checkRefs($rows, 'project_tbl', 'project_id', ['budgets_tbl', 'expense_tbl', 'fin_expense_tbl', 'fin_construction_bond_tbl', 'fin_receivable_payable_tbl', 'fin_equipment_expense_tbl', 'fin_equipment_rental_income_tbl', 'fin_project_contract_tbl', 'inventory_transaction_tbl']);
        $this->checkRefs($rows, 'inventory_item_tbl', 'item_id', ['inventory_transaction_tbl']);
        $this->checkRefs($rows, 'fin_expense_category_tbl', 'fin_category_id', ['fin_expense_tbl']);
    }

    private function checkRefs(array $rows, string $parent, string $key, array $children): void
    {
        $ids = array_column($rows[$parent] ?? [], $key);
        if (count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException("Duplicate {$parent}.{$key}");
        } foreach ($children as $t) {
            foreach ($rows[$t] ?? [] as $r) {
                $v = $r[$key] ?? null;
                if ($v !== null && ! in_array($v, $ids, true)) {
                    throw new \RuntimeException("{$t}.{$key} references missing {$parent}: {$v}");
                }
            }
        }
    }

    private function reconcile(): void
    {
        $rows = [];
        foreach ($this->tables as $t) {
            $rows[$t] = DB::table($t)->get()->map(fn ($r) => (array) $r)->all();
        }
        $this->validateTotals($rows);
    }

    private function validateTotals(array $rows): void
    {
        $totals = [];
        foreach ($rows['fin_expense_tbl'] as $e) {
            if ($e['amount'] <= 0) {
                throw new \RuntimeException('Nonpositive expense');
            }
            if ($e['project_id'] !== null) {
                $totals[$e['project_id']] = ($totals[$e['project_id']] ?? 0) + (int) round($e['amount'] * 100);
            }
        }
        foreach ($rows['budgets_tbl'] as $b) {
            if ($b['budget_amount'] <= 0 || (int) round($b['actual_amount'] * 100) !== ($totals[$b['project_id']] ?? 0)) {
                throw new \RuntimeException('Budget/expense mismatch: '.$b['project_id']);
            }
        }
        $transactions = $rows['inventory_transaction_tbl'];
        usort($transactions, fn ($a, $b) => [$a['transaction_date'], $a['inventory_transaction_id']] <=> [$b['transaction_date'], $b['inventory_transaction_id']]);
        $stock = [];
        foreach ($transactions as $tx) {
            if ($tx['quantity'] <= 0 || ! in_array($tx['transaction_type'], ['IN', 'OUT'], true)) {
                throw new \RuntimeException('Invalid stock transaction');
            }
            $id = $tx['item_id'];
            $stock[$id] = ($stock[$id] ?? 0) + (int) round($tx['quantity'] * 100) * ($tx['transaction_type'] === 'IN' ? 1 : -1);
            if ($stock[$id] < 0) {
                throw new \RuntimeException('Negative running stock: '.$id);
            }
        }
        foreach ($rows['inventory_item_tbl'] as $item) {
            if ((int) round($item['current_stock'] * 100) !== ($stock[$item['item_id']] ?? 0)) {
                throw new \RuntimeException('Inventory balance mismatch: '.$item['item_id']);
            }
        }
        foreach ($rows['project_tbl'] as $p) {
            if (($p['data_source'] ?? '') !== 'company_inspired_sample' || $p['start_date'] < '2019-01-01' || $p['estimated_end_date'] < $p['start_date']) {
                throw new \RuntimeException('Invalid project source/schedule');
            }
            if ($p['status'] === 'Completed' && ($p['completion_percentage'] != 100 || ! $p['actual_end_date'] || $p['actual_end_date'] > now()->toDateString())) {
                throw new \RuntimeException('Invalid completed project');
            }
        }
    }
}
