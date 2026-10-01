<?php

namespace Tests\Feature;

use App\Services\InventoryHistoryReconciler;
use App\Services\LegacyInventoryPriceBackfill;
use App\Services\ProjectCostLedger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryHistoryReconcilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('inventory_transaction_tbl', function (Blueprint $table) {
            $table->increments('inventory_transaction_id');
            $table->unsignedInteger('item_id')->nullable();
            $table->string('transaction_type');
            $table->unsignedInteger('project_id')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->date('transaction_date');
            $table->string('movement_reason')->nullable();
            $table->string('bar_code')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
        });
        Schema::create('unit_tbl', function (Blueprint $table) {
            $table->increments('unit_id');
            $table->string('unit_name');
        });
        Schema::create('inventory_item_tbl', function (Blueprint $table) {
            $table->increments('item_id');
            $table->string('item_name');
            $table->unsignedInteger('unit_id')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
        });
        Schema::create('fin_expense_category_tbl', function (Blueprint $table) {
            $table->increments('fin_category_id');
            $table->string('category_code');
            $table->string('category_name');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->unsignedInteger('inventory_transaction_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('fin_category_id');
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('expense_date');
            $table->string('entry_kind')->nullable();
            $table->string('project_cost_component')->nullable();
            $table->string('expense_description')->nullable();
            $table->string('remarks')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->increments('allocation_id');
            $table->unsignedInteger('in_transaction_id');
            $table->unsignedInteger('out_transaction_id');
            $table->unsignedInteger('project_id');
            $table->decimal('quantity', 10, 2);
            $table->string('valuation_status');
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('allocated_amount', 14, 2)->nullable();
            $table->dateTime('allocated_at');
        });
        Schema::create('budgets_tbl', function (Blueprint $table) {
            $table->increments('budget_id');
            $table->unsignedInteger('project_id');
            $table->decimal('actual_amount', 14, 2)->default(0);
        });
        DB::table('fin_expense_category_tbl')->insert([
            ['fin_category_id' => 1, 'category_code' => 'CONSTRUCTION_SUPPLY', 'category_name' => 'Construction supplies'],
            ['fin_category_id' => 2, 'category_code' => 'OTHER', 'category_name' => 'Other'],
        ]);
    }

    public function test_legacy_pricing_creates_one_purchase_per_receipt_and_values_project_withdrawals_once(): void
    {
        DB::table('unit_tbl')->insert(['unit_id' => 1, 'unit_name' => 'pcs']);
        DB::table('inventory_item_tbl')->insert([
            ['item_id' => 1, 'item_name' => 'Bolts', 'unit_id' => 1, 'unit_price' => 5],
            ['item_id' => 2, 'item_name' => 'Paint', 'unit_id' => 1, 'unit_price' => 10],
        ]);
        DB::table('budgets_tbl')->insert([
            ['project_id' => 7, 'actual_amount' => 0],
            ['project_id' => 8, 'actual_amount' => 0],
        ]);
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 1, 'item_id' => 1, 'transaction_type' => 'IN', 'project_id' => null, 'quantity' => 10, 'transaction_date' => '2026-01-01', 'movement_reason' => 'legacy_unpriced'],
            ['inventory_transaction_id' => 2, 'item_id' => 1, 'transaction_type' => 'IN', 'project_id' => 7, 'quantity' => 4, 'transaction_date' => '2026-01-02', 'movement_reason' => null],
            ['inventory_transaction_id' => 3, 'item_id' => 2, 'transaction_type' => 'IN', 'project_id' => null, 'quantity' => 2, 'transaction_date' => '2026-01-02', 'movement_reason' => 'adjustment'],
            ['inventory_transaction_id' => 4, 'item_id' => 1, 'transaction_type' => 'OUT', 'project_id' => 8, 'quantity' => 11, 'transaction_date' => '2026-01-03', 'movement_reason' => null],
        ]);

        $backfill = app(LegacyInventoryPriceBackfill::class);
        $this->assertCount(2, $backfill->candidates());
        $this->assertSame(['storage_purchases' => 1, 'project_purchases' => 1], $backfill->apply());
        $this->assertSame(['storage_purchases' => 0, 'project_purchases' => 0], $backfill->apply());
        $this->assertSame(2, DB::table('fin_expense_tbl')->count());
        $this->assertSame(20.0, (float) DB::table('fin_expense_tbl')->where('inventory_transaction_id', 2)->value('amount'));
        $this->assertSame(7, DB::table('fin_expense_tbl')->where('inventory_transaction_id', 2)->value('project_id'));
        $this->assertSame('adjustment', DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', 3)->value('movement_reason'));

        $result = app(InventoryHistoryReconciler::class)->allocateSafeWithdrawals();
        $this->assertSame([4], $result['allocated_withdrawals']);
        $this->assertSame([], $result['unreconciled_withdrawals']);
        $this->assertSame(55.0, (float) DB::table('inventory_cost_allocation_tbl')->where('out_transaction_id', 4)->sum('allocated_amount'));
        $this->assertSame(0, app(ProjectCostLedger::class)->forProject(7)['unvalued_count']);
        $this->assertSame(0.0, app(ProjectCostLedger::class)->forProject(7)['total']);
        $this->assertSame(55.0, app(ProjectCostLedger::class)->forProject(8)['total']);
    }

    public function test_audit_is_read_only_and_only_proven_receipts_are_classified(): void
    {
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 1, 'transaction_type' => 'IN', 'project_id' => null, 'transaction_date' => '2026-01-01'],
            ['inventory_transaction_id' => 2, 'transaction_type' => 'IN', 'project_id' => null, 'transaction_date' => '2026-01-01'],
            ['inventory_transaction_id' => 3, 'transaction_type' => 'IN', 'project_id' => 7, 'transaction_date' => '2026-01-01'],
            ['inventory_transaction_id' => 4, 'transaction_type' => 'OUT', 'project_id' => 7, 'transaction_date' => '2026-01-02'],
            ['inventory_transaction_id' => 5, 'transaction_type' => 'IN', 'project_id' => null, 'transaction_date' => '2026-01-01'],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['fin_expense_id' => 10, 'inventory_transaction_id' => 1, 'project_id' => null, 'fin_category_id' => 1, 'amount' => 120, 'expense_date' => '2026-01-01'],
            ['fin_expense_id' => 11, 'inventory_transaction_id' => 3, 'project_id' => 7, 'fin_category_id' => 1, 'amount' => 90, 'expense_date' => '2026-01-01'],
            ['fin_expense_id' => 12, 'inventory_transaction_id' => 5, 'project_id' => null, 'fin_category_id' => 2, 'amount' => 50, 'expense_date' => '2026-01-01'],
        ]);

        $service = app(InventoryHistoryReconciler::class);
        $report = $service->report();
        $this->assertSame([['id' => 1, 'expense_id' => 10]], $report['safe_purchase_receipts']);
        $this->assertSame([2], $report['unpriced_receipts']);
        $this->assertSame([4], $report['unallocated_withdrawals']);
        $this->assertCount(2, $report['ambiguous_receipts']);
        $this->assertNull(DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', 1)->value('movement_reason'));

        $service->classifySafeReceipts();
        $this->assertSame('purchase', DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', 1)->value('movement_reason'));
        $this->assertSame('inventory_purchase', DB::table('fin_expense_tbl')->where('fin_expense_id', 10)->value('entry_kind'));
        $this->assertSame('legacy_unpriced', DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', 2)->value('movement_reason'));
        $this->assertNull(DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', 3)->value('movement_reason'));
        $this->assertSame(3, DB::table('fin_expense_tbl')->count());
        $this->assertSame(0, DB::table('inventory_cost_allocation_tbl')->count());
    }

    public function test_safe_fifo_replay_allocates_partial_withdrawals_across_projects_and_prices(): void
    {
        DB::table('budgets_tbl')->insert([
            ['project_id' => 7, 'actual_amount' => 0],
            ['project_id' => 8, 'actual_amount' => 0],
        ]);
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 1, 'item_id' => 1, 'transaction_type' => 'IN', 'project_id' => null, 'quantity' => 10, 'transaction_date' => '2026-01-01'],
            ['inventory_transaction_id' => 2, 'item_id' => 1, 'transaction_type' => 'OUT', 'project_id' => 7, 'quantity' => 4, 'transaction_date' => '2026-01-02'],
            ['inventory_transaction_id' => 3, 'item_id' => 1, 'transaction_type' => 'OUT', 'project_id' => 8, 'quantity' => 3, 'transaction_date' => '2026-01-03'],
            ['inventory_transaction_id' => 4, 'item_id' => 1, 'transaction_type' => 'IN', 'project_id' => null, 'quantity' => 10, 'transaction_date' => '2026-01-04'],
            ['inventory_transaction_id' => 5, 'item_id' => 1, 'transaction_type' => 'OUT', 'project_id' => 8, 'quantity' => 5, 'transaction_date' => '2026-01-05'],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['inventory_transaction_id' => 1, 'project_id' => null, 'fin_category_id' => 1, 'amount' => 100, 'expense_date' => '2026-01-01'],
            ['inventory_transaction_id' => 4, 'project_id' => null, 'fin_category_id' => 1, 'amount' => 200, 'expense_date' => '2026-01-04'],
        ]);

        $service = app(InventoryHistoryReconciler::class);
        $service->classifySafeReceipts();
        $result = $service->allocateSafeWithdrawals();

        $this->assertSame([2, 3, 5], $result['allocated_withdrawals']);
        $this->assertSame([], $result['unreconciled_withdrawals']);
        $this->assertSame(40.0, (float) DB::table('budgets_tbl')->where('project_id', 7)->value('actual_amount'));
        $this->assertSame(100.0, (float) DB::table('budgets_tbl')->where('project_id', 8)->value('actual_amount'));
        $this->assertSame(2, DB::table('inventory_cost_allocation_tbl')->where('out_transaction_id', 5)->count());
        $this->assertSame(70.0, (float) DB::table('inventory_cost_allocation_tbl')->where('out_transaction_id', 5)->sum('allocated_amount'));
        $this->assertSame([], $service->report()['unallocated_withdrawals']);
    }

    public function test_ambiguous_receipt_blocks_replay_and_unpriced_return_keeps_cost_unknown(): void
    {
        DB::table('budgets_tbl')->insert(['project_id' => 7, 'actual_amount' => 0]);
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 1, 'item_id' => 1, 'transaction_type' => 'IN', 'project_id' => 7, 'quantity' => 5, 'transaction_date' => '2026-01-01', 'movement_reason' => null],
            ['inventory_transaction_id' => 2, 'item_id' => 1, 'transaction_type' => 'OUT', 'project_id' => 7, 'quantity' => 2, 'transaction_date' => '2026-01-02', 'movement_reason' => null],
            ['inventory_transaction_id' => 3, 'item_id' => 2, 'transaction_type' => 'IN', 'project_id' => null, 'quantity' => 4, 'transaction_date' => '2026-01-10', 'movement_reason' => 'return'],
            ['inventory_transaction_id' => 4, 'item_id' => 2, 'transaction_type' => 'OUT', 'project_id' => 7, 'quantity' => 2, 'transaction_date' => '2026-01-09', 'movement_reason' => null],
        ]);
        $service = app(InventoryHistoryReconciler::class);
        $result = $service->allocateSafeWithdrawals();

        $this->assertSame([], $result['allocated_withdrawals']);
        $this->assertSame([2, 4], array_column($result['unreconciled_withdrawals'], 'id'));
        $this->assertSame(0, DB::table('inventory_cost_allocation_tbl')->count());
        $this->assertSame(0.0, (float) DB::table('budgets_tbl')->where('project_id', 7)->value('actual_amount'));
    }

    public function test_unpriced_legacy_receipt_never_overwrites_project_actual_cost(): void
    {
        DB::table('budgets_tbl')->insert(['project_id' => 7, 'actual_amount' => 500]);
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 1, 'item_id' => 1, 'transaction_type' => 'IN', 'project_id' => null, 'quantity' => 4, 'transaction_date' => '2026-01-01'],
            ['inventory_transaction_id' => 2, 'item_id' => 1, 'transaction_type' => 'OUT', 'project_id' => 7, 'quantity' => 2, 'transaction_date' => '2026-01-02'],
        ]);

        $service = app(InventoryHistoryReconciler::class);
        $service->classifySafeReceipts();
        $result = $service->allocateSafeWithdrawals();

        $this->assertSame([], $result['allocated_withdrawals']);
        $this->assertSame([['id' => 2, 'reason' => 'unpriced_source']], $result['unreconciled_withdrawals']);
        $this->assertSame(0, DB::table('inventory_cost_allocation_tbl')->count());
        $this->assertSame(500.0, (float) DB::table('budgets_tbl')->where('project_id', 7)->value('actual_amount'));
    }
}
