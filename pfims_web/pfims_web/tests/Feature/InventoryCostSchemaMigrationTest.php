<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryCostSchemaMigrationTest extends TestCase
{
    public function test_schema_is_reversible_and_preserves_legacy_rows(): void
    {
        Schema::create('project_tbl', function (Blueprint $table) {
            $table->integer('project_id')->primary();
        });
        Schema::create('inventory_transaction_tbl', function (Blueprint $table) {
            $table->integer('inventory_transaction_id')->primary();
            $table->string('transaction_type', 20);
        });
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->integer('inventory_transaction_id')->nullable()->unique();
            $table->decimal('amount', 12, 2)->nullable();
        });

        DB::table('project_tbl')->insert(['project_id' => 7]);
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 10, 'transaction_type' => 'IN'],
            ['inventory_transaction_id' => 11, 'transaction_type' => 'OUT'],
        ]);
        DB::table('fin_expense_tbl')->insert(['inventory_transaction_id' => 10, 'amount' => null]);

        $migration = require base_path('database/migrations/2026_09_27_000001_prepare_inventory_cost_allocation.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumns('inventory_transaction_tbl', ['movement_reason', 'recorded_at']));
        $this->assertTrue(Schema::hasColumn('fin_expense_tbl', 'entry_kind'));
        $this->assertTrue(Schema::hasTable('inventory_cost_allocation_tbl'));
        $this->assertNull(DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', 10)->value('movement_reason'));
        $this->assertNull(DB::table('fin_expense_tbl')->where('inventory_transaction_id', 10)->value('entry_kind'));

        $allocation = [
            'in_transaction_id' => 10,
            'out_transaction_id' => 11,
            'project_id' => 7,
            'quantity' => 2,
            'valuation_status' => 'valued',
            'unit_cost' => 12.5,
            'allocated_amount' => 25,
            'allocated_at' => '2026-09-27 10:00:00',
        ];
        DB::table('inventory_cost_allocation_tbl')->insert($allocation);
        try {
            DB::table('inventory_cost_allocation_tbl')->insert($allocation);
            $this->fail('The same receipt-to-withdrawal pair must not be allocated twice.');
        } catch (QueryException $expected) {
            $this->assertSame(1, DB::table('inventory_cost_allocation_tbl')->count());
        }

        $migration->down();

        $this->assertFalse(Schema::hasTable('inventory_cost_allocation_tbl'));
        $this->assertFalse(Schema::hasColumn('inventory_transaction_tbl', 'movement_reason'));
        $this->assertFalse(Schema::hasColumn('inventory_transaction_tbl', 'recorded_at'));
        $this->assertFalse(Schema::hasColumn('fin_expense_tbl', 'entry_kind'));
        $this->assertSame(2, DB::table('inventory_transaction_tbl')->count());
        $this->assertSame(1, DB::table('fin_expense_tbl')->count());
    }
}
