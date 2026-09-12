<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportData extends Command
{
    protected $signature = 'data:import 
                            {--mode=add : Import mode - add, overwrite, upsert, skip} 
                            {--force : Force import without confirmation}';

    protected $description = 'Import data from JSON files into pfims_db database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mode = $this->option('mode');

        $this->info('========================================');
        $this->info('  IMPORTING DATA INTO pfims_db');
        $this->info('========================================');
        $this->info("Import mode: {$mode}\n");

        // Check if tables exist
        $this->checkTables();

        // Import each table
        $this->importProjects($mode);
        $this->importBudgets($mode);
        $this->importExpenses($mode);
        $this->importInventoryItems($mode);
        $this->importInventoryTransactions($mode);

        // Show summary
        $this->showSummary();

        $this->info("\n✅ Import completed successfully!");
    }

    /**
     * Check if all required tables exist
     */
    protected function checkTables()
    {
        $this->info('📋 Checking database tables...');

        $tables = ['project_tbl', 'budgets_tbl', 'fin_expense_category_tbl', 'fin_expense_tbl', 'inventory_item_tbl', 'inventory_transaction_tbl'];
        $missing = [];

        foreach ($tables as $table) {
            $exists = DB::select("SHOW TABLES LIKE '{$table}'");
            if (empty($exists)) {
                $missing[] = $table;
            }
        }

        if (! empty($missing)) {
            $this->error('❌ Missing tables: '.implode(', ', $missing));
            exit(1);
        }

        $this->info('✅ All tables exist!');
    }

    /**
     * Clean date values (handle empty strings)
     */
    protected function cleanDate($date)
    {
        if (empty($date) || $date === '' || $date === 'null' || $date === null) {
            return null;
        }
        $timestamp = strtotime($date);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Clean numeric values
     */
    protected function cleanNumeric($value)
    {
        if (empty($value) || $value === '' || $value === 'null' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Calculate actual amount from expense fields
     */
    protected function calculateActualAmount($expense)
    {
        $amount = 0;
        if (isset($expense['labor_amount']) && is_numeric($expense['labor_amount'])) {
            $amount += $expense['labor_amount'];
        }
        if (isset($expense['material_amount']) && is_numeric($expense['material_amount'])) {
            $amount += $expense['material_amount'];
        }
        if (isset($expense['equipment_amount']) && is_numeric($expense['equipment_amount'])) {
            $amount += $expense['equipment_amount'];
        }
        if (isset($expense['other_amount']) && is_numeric($expense['other_amount'])) {
            $amount += $expense['other_amount'];
        }

        return $amount > 0 ? $amount : null;
    }

    /**
     * Import projects
     */
    protected function importProjects($mode)
    {
        $this->info("\n📦 Importing projects from projects.json...");

        $data = $this->getData('projects');
        if (empty($data)) {
            $this->warn('No projects data found');

            return;
        }

        $cleanData = [];
        foreach ($data as $item) {
            $cleanData[] = [
                'project_id' => $item['project_id'],
                'project_name' => $item['project_name'] ?? null,
                'client_name' => $item['client_name'] ?? null,
                'project_manager' => $item['project_manager'] ?? null,
                'start_date' => $this->cleanDate($item['start_date'] ?? null),
                'estimated_end_date' => $this->cleanDate($item['estimated_end_date'] ?? null),
                'actual_end_date' => $this->cleanDate($item['actual_end_date'] ?? null),
                'worker_count' => $this->cleanNumeric($item['worker_count'] ?? null),
                'phase' => $item['phase'] ?? null,
                'completion_percentage' => $this->cleanNumeric($item['completion_percentage'] ?? 0),
                'status' => $item['status'] ?? 'Pending',
            ];
        }

        $this->insertData('project_tbl', $cleanData, 'project_id', $mode);
        $this->info('✅ Projects imported');
    }

    /**
     * Import budgets
     */
    protected function importBudgets($mode)
    {
        $this->info("\n💰 Importing budgets from budgets.json...");

        $data = $this->getData('budgets');
        if (empty($data)) {
            $this->warn('No budgets data found');

            return;
        }

        $cleanData = [];
        foreach ($data as $item) {
            $cleanData[] = [
                'budget_id' => $item['budget_id'],
                'project_id' => $item['project_id'],
                'budget_amount' => $this->cleanNumeric($item['budget_amount'] ?? 0),
                'actual_amount' => $this->cleanNumeric($item['actual_amount'] ?? null),
            ];
        }

        $this->insertData('budgets_tbl', $cleanData, 'budget_id', $mode);
        $this->info('✅ Budgets imported');
    }

    /**
     * Import expenses
     */
    protected function importExpenses($mode)
    {
        $this->info("\n🧾 Importing expenses from expenses.json...");

        $data = $this->getData('expenses');
        if (empty($data)) {
            $this->warn('No expenses data found');

            return;
        }

        $cleanData = [];
        foreach ($data as $item) {
            $sourceId = $this->cleanNumeric($item['expense_id'] ?? null);
            $components = [
                'labor' => $this->cleanNumeric($item['labor_amount'] ?? null),
                'material' => $this->cleanNumeric($item['material_amount'] ?? null),
                'equipment' => $this->cleanNumeric($item['equipment_amount'] ?? null),
                'other' => $this->cleanNumeric($item['other_amount'] ?? null),
            ];
            if (! collect($components)->contains(fn ($amount) => (float) $amount > 0)) {
                $components['other'] = $this->calculateActualAmount($item);
            }

            $rowIndex = 1;
            foreach ($components as $component => $amount) {
                if ((float) $amount <= 0) {
                    continue;
                }

                $cleanData[] = [
                    'fin_expense_id' => $sourceId ? ((int) $sourceId * 10) + $rowIndex : null,
                    'project_id' => $this->cleanNumeric($item['project_id'] ?? null),
                    'fin_category_id' => $this->finExpenseCategoryIdForComponent($component),
                    'inventory_transaction_id' => $this->cleanNumeric($item['inventory_transaction_id'] ?? null),
                    'project_cost_component' => $component,
                    'expense_description' => $item['expense_description'] ?? ucfirst($component).' expense',
                    'amount' => $amount,
                    'expense_date' => $this->cleanDate($item['expense_date'] ?? null),
                    'remarks' => $item['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $rowIndex++;
            }
        }

        $this->insertData('fin_expense_tbl', $cleanData, 'fin_expense_id', $mode);
        $this->info('✅ Expenses imported');
    }

    protected function finExpenseCategoryIdForComponent(string $component): int
    {
        $terms = match ($component) {
            'material' => ['material', 'materials', 'supply', 'supplies', 'construction'],
            'labor' => ['labor', 'labour', 'salary', 'salaries', 'wage', 'wages'],
            'equipment' => ['equipment', 'machine', 'machinery', 'rental', 'repair', 'maintenance'],
            default => ['other', 'misc', 'admin'],
        };

        $query = DB::table('fin_expense_category_tbl')->where('classification', 'direct');
        $query->where(function ($inner) use ($terms) {
            foreach ($terms as $term) {
                $inner->orWhere('category_code', 'like', '%'.$term.'%')
                    ->orWhere('category_name', 'like', '%'.$term.'%');
            }
        });

        return (int) ($query->value('fin_category_id')
            ?? DB::table('fin_expense_category_tbl')->where('classification', 'direct')->value('fin_category_id')
            ?? DB::table('fin_expense_category_tbl')->value('fin_category_id'));
    }

    /**
     * Import inventory items
     */
    protected function importInventoryItems($mode)
    {
        $this->info("\n📦 Importing inventory items from inventory_items.json...");

        $data = $this->getData('inventory_items');
        if (empty($data)) {
            $this->warn('No inventory items data found');

            return;
        }

        $cleanData = [];
        foreach ($data as $item) {
            $cleanData[] = [
                'item_id' => $item['item_id'],
                'inventory_category_id' => $this->cleanNumeric($item['inventory_category_id'] ?? null),
                'supplier_id' => $this->cleanNumeric($item['supplier_id'] ?? null),
                'unit_id' => $this->cleanNumeric($item['unit_id'] ?? null),
                'item_name' => $item['item_name'] ?? null,
                'current_stock' => $this->cleanNumeric($item['current_stock'] ?? 0),
                'reorder_level' => $this->cleanNumeric($item['reorder_level'] ?? 0),
            ];
        }

        $this->insertData('inventory_item_tbl', $cleanData, 'item_id', $mode);
        $this->info('✅ Inventory items imported');
    }

    /**
     * Import inventory transactions
     */
    protected function importInventoryTransactions($mode)
    {
        $this->info("\n📊 Importing inventory transactions from inventory_transactions.json...");

        $data = $this->getData('inventory_transactions');
        if (empty($data)) {
            $this->warn('No inventory transactions data found');

            return;
        }

        $cleanData = [];
        foreach ($data as $item) {
            $cleanData[] = [
                'inventory_transaction_id' => $item['inventory_transaction_id'],
                'item_id' => $this->cleanNumeric($item['item_id'] ?? null),
                'project_id' => $this->cleanNumeric($item['project_id'] ?? null),
                'transaction_type' => $item['transaction_type'] ?? 'IN',
                'quantity' => $this->cleanNumeric($item['quantity'] ?? 0),
                'transaction_date' => $this->cleanDate($item['transaction_date'] ?? null),
            ];
        }

        $this->insertData('inventory_transaction_tbl', $cleanData, 'inventory_transaction_id', $mode);
        $this->info('✅ Inventory transactions imported');
    }

    /**
     * Get data from JSON file
     */
    protected function getData($filename)
    {
        $filePath = storage_path("app/data/{$filename}.json");

        if (! file_exists($filePath)) {
            // Try looking in project root
            $filePath = base_path("{$filename}.txt");
            if (! file_exists($filePath)) {
                return [];
            }
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("JSON error in {$filename}: ".json_last_error_msg());

            return [];
        }

        return $data;
    }

    /**
     * Insert data into table with specified mode
     */
    protected function insertData($table, $data, $primaryKey, $mode)
    {
        if (empty($data)) {
            $this->warn("No data to import into {$table}");

            return;
        }

        switch ($mode) {
            case 'overwrite':
                if (! $this->option('force')) {
                    if (! $this->confirm("⚠️  Truncate table {$table} and insert all data? (--force to skip)")) {
                        return;
                    }
                }
                DB::table($table)->truncate();
                $this->insertChunks($table, $data);
                break;

            case 'upsert':
                DB::table($table)->upsert(
                    $data,
                    [$primaryKey],
                    array_keys($data[0])
                );
                break;

            case 'skip':
                $existingIds = DB::table($table)->pluck($primaryKey)->toArray();
                $newData = array_filter($data, function ($item) use ($existingIds, $primaryKey) {
                    return ! in_array($item[$primaryKey], $existingIds);
                });
                if (! empty($newData)) {
                    $this->insertChunks($table, $newData);
                } else {
                    $this->info("No new records to insert into {$table}");
                }
                break;

            case 'add':
            default:
                $this->insertChunks($table, $data);
                break;
        }
    }

    /**
     * Insert data in chunks to avoid memory issues
     */
    protected function insertChunks($table, $data)
    {
        $chunks = array_chunk($data, 100);
        $inserted = 0;

        foreach ($chunks as $chunk) {
            try {
                DB::table($table)->insert($chunk);
                $inserted += count($chunk);
            } catch (\Exception $e) {
                $this->warn("Error inserting into {$table}: ".$e->getMessage());
                // Try one by one
                foreach ($chunk as $record) {
                    try {
                        DB::table($table)->insert($record);
                        $inserted++;
                    } catch (\Exception $e2) {
                        $this->warn('Failed to insert record: '.json_encode($record));
                    }
                }
            }
        }

        $this->info("Inserted {$inserted} records into {$table}");
    }

    /**
     * Show import summary
     */
    protected function showSummary()
    {
        $this->info("\n========================================");
        $this->info('  IMPORT SUMMARY');
        $this->info('========================================');

        $counts = [
            'Projects' => DB::table('project_tbl')->count(),
            'Budgets' => DB::table('budgets_tbl')->count(),
            'Finance Expenses' => DB::table('fin_expense_tbl')->count(),
            'Inventory Items' => DB::table('inventory_item_tbl')->count(),
            'Inventory Transactions' => DB::table('inventory_transaction_tbl')->count(),
        ];

        foreach ($counts as $name => $count) {
            $this->info(sprintf('%-20s: %s', $name, number_format($count)));
        }

        $this->info('========================================');
    }
}
