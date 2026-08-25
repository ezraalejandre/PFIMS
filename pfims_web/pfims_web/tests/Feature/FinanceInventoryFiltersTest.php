<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceInventoryFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        $this->createSchema();
        $this->seedData();
        $this->actingAs(User::query()->create([
            'name' => 'Filter Tester',
            'email' => 'filters@example.test',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'Active',
        ]));
    }

    public function test_finance_expense_filters_return_only_matching_rows(): void
    {
        $this->getJson('/api/finance-expenses?project_id=1&category_id=1&start_date=2026-01-01&end_date=2026-01-31&include_pending=0')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.expense_description', 'Cement January');

        $this->getJson('/api/finance-expenses?search=wages&include_pending=0')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.project_name', 'Beta Project');

        $this->getJson('/api/finance-expenses?project_cost_component=material&include_pending=0')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.project_cost_component', 'material');
    }

    public function test_finance_filter_inputs_are_validated_before_querying(): void
    {
        $this->getJson('/api/finance-expenses?start_date=2026-02-01&end_date=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/finance-expenses?category_id=999')->assertUnprocessable();
        $this->getJson('/api/finance-expenses?project_cost_component=invalid')->assertUnprocessable();
        $this->getJson('/api/construction-bonds?status=unknown')->assertUnprocessable();
        $this->getJson('/api/reports/backhoe-profitability?period=2026-01-02')->assertUnprocessable();
    }

    public function test_inventory_item_filters_use_configured_reorder_levels(): void
    {
        $this->getJson('/api/inventory?category_id=1&stock_state=low_stock')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Cement');

        $this->getJson('/api/inventory?stock_state=out_of_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Paint');

        $this->getJson('/api/inventory?search=hardware')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Nails');
    }

    public function test_inventory_transaction_filters_and_search_are_composable(): void
    {
        $this->getJson('/api/inventory/transactions?transaction_type=OUT&project_id=2&category_id=2&start_date=2026-02-01&end_date=2026-02-28')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Nails');

        $this->getJson('/api/inventory/transactions?search=222002')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.project', 'Beta Project');
    }

    public function test_inventory_transaction_filter_inputs_reject_invalid_ranges_and_enums(): void
    {
        $this->getJson('/api/inventory/transactions?transaction_type=TRANSFER')->assertUnprocessable();
        $this->getJson('/api/inventory/transactions?start_date=2026-03-01&end_date=2026-02-01')->assertUnprocessable();
        $this->getJson('/api/inventory?stock_state=critical')->assertUnprocessable();
    }

    private function seedData(): void
    {
        DB::table('project_tbl')->insert([
            ['project_id' => 1, 'project_name' => 'Alpha Project'],
            ['project_id' => 2, 'project_name' => 'Beta Project'],
        ]);
        DB::table('fin_expense_category_tbl')->insert([
            ['fin_category_id' => 1, 'category_code' => 'CONST_SUPPLY', 'category_name' => 'Construction Supply', 'classification' => 'direct', 'is_active' => true],
            ['fin_category_id' => 2, 'category_code' => 'SALARIES_WAGES', 'category_name' => 'Salaries and Wages', 'classification' => 'direct', 'is_active' => true],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => 1, 'fin_category_id' => 1, 'project_cost_component' => 'material', 'expense_description' => 'Cement January', 'amount' => 100, 'expense_date' => '2026-01-15'],
            ['project_id' => 1, 'fin_category_id' => 1, 'project_cost_component' => 'material', 'expense_description' => 'Cement February', 'amount' => 200, 'expense_date' => '2026-02-15'],
            ['project_id' => 2, 'fin_category_id' => 2, 'project_cost_component' => 'labor', 'expense_description' => 'Site wages', 'amount' => 300, 'expense_date' => '2026-01-20'],
        ]);
        DB::table('inventory_category_tbl')->insert([
            ['inventory_category_id' => 1, 'inventory_category_name' => 'Materials'],
            ['inventory_category_id' => 2, 'inventory_category_name' => 'Hardware'],
        ]);
        DB::table('supplier_tbl')->insert([
            ['supplier_id' => 1, 'supplier_name' => 'Build Supply'],
            ['supplier_id' => 2, 'supplier_name' => 'Fastener Depot'],
        ]);
        DB::table('unit_tbl')->insert([
            ['unit_id' => 1, 'unit_name' => 'Bag'],
            ['unit_id' => 2, 'unit_name' => 'Box'],
        ]);
        DB::table('inventory_item_tbl')->insert([
            ['item_id' => 1, 'inventory_category_id' => 1, 'supplier_id' => 1, 'unit_id' => 1, 'item_name' => 'Cement', 'current_stock' => 8, 'reorder_level' => 10],
            ['item_id' => 2, 'inventory_category_id' => 2, 'supplier_id' => 2, 'unit_id' => 2, 'item_name' => 'Nails', 'current_stock' => 40, 'reorder_level' => 5],
            ['item_id' => 3, 'inventory_category_id' => 1, 'supplier_id' => 1, 'unit_id' => 1, 'item_name' => 'Paint', 'current_stock' => 0, 'reorder_level' => 3],
        ]);
        DB::table('inventory_transaction_tbl')->insert([
            ['inventory_transaction_id' => 1, 'item_id' => 1, 'project_id' => 1, 'transaction_type' => 'IN', 'quantity' => 10, 'bar_code' => 111001, 'transaction_date' => '2026-01-10'],
            ['inventory_transaction_id' => 2, 'item_id' => 2, 'project_id' => 2, 'transaction_type' => 'OUT', 'quantity' => 5, 'bar_code' => 222002, 'transaction_date' => '2026-02-10'],
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('status')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('project_tbl', function (Blueprint $table) {
            $table->integer('project_id')->primary();
            $table->string('project_name');
        });
        Schema::create('fin_expense_category_tbl', function (Blueprint $table) {
            $table->increments('fin_category_id');
            $table->string('category_code');
            $table->string('category_name');
            $table->string('classification');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->integer('project_id')->nullable();
            $table->unsignedInteger('fin_category_id');
            $table->unsignedInteger('inventory_transaction_id')->nullable();
            $table->string('project_cost_component', 20)->nullable();
            $table->string('expense_description');
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('remarks')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
        });
        Schema::create('inventory_category_tbl', function (Blueprint $table) {
            $table->increments('inventory_category_id');
            $table->string('inventory_category_name');
        });
        Schema::create('supplier_tbl', function (Blueprint $table) {
            $table->increments('supplier_id');
            $table->string('supplier_name');
        });
        Schema::create('unit_tbl', function (Blueprint $table) {
            $table->increments('unit_id');
            $table->string('unit_name');
        });
        Schema::create('inventory_item_tbl', function (Blueprint $table) {
            $table->increments('item_id');
            $table->unsignedInteger('inventory_category_id');
            $table->unsignedInteger('supplier_id');
            $table->unsignedInteger('unit_id');
            $table->string('item_name');
            $table->decimal('current_stock', 14, 2);
            $table->decimal('reorder_level', 14, 2);
        });
        Schema::create('inventory_transaction_tbl', function (Blueprint $table) {
            $table->increments('inventory_transaction_id');
            $table->unsignedInteger('item_id');
            $table->integer('project_id')->nullable();
            $table->string('transaction_type');
            $table->decimal('quantity', 14, 2);
            $table->integer('bar_code')->nullable();
            $table->date('transaction_date');
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
        });
    }
}
