<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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

    public function test_cash_accounts_endpoint_returns_database_accounts_for_the_add_modal(): void
    {
        DB::table('company_bank_account_tbl')->insert([
            ['account_id' => 2, 'account_name' => 'Site revolving fund', 'account_type' => 'cash_on_hand_field'],
            ['account_id' => 1, 'account_name' => 'Company treasury', 'account_type' => 'treasury'],
        ]);

        $this->getJson('/api/cash-accounts')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.account_id', 1)
            ->assertJsonPath('0.account_name', 'Company treasury')
            ->assertJsonPath('1.account_id', 2)
            ->assertJsonPath('1.account_name', 'Site revolving fund');
    }

    public function test_expense_overall_summary_aggregates_source_rows_without_a_database_view(): void
    {
        $this->getJson('/api/reports/expovrall?period=2026-01-01')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'project_name' => 'Alpha Project',
                'category_code' => 'CONST_SUPPLY',
                'category_total' => 100,
            ])
            ->assertJsonFragment([
                'project_name' => 'Beta Project',
                'category_code' => 'SALARIES_WAGES',
                'category_total' => 300,
            ]);

        $this->getJson('/api/reports/expovrall?period=2026-01-01&project_id=1')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.project_name', 'Alpha Project');
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

    public function test_inventory_transaction_can_be_edited_and_stock_is_recalculated(): void
    {
        $this->patchJson('/api/inventory/transaction/1', [
            'quantity' => 14,
            'bar_code' => 111009,
            'transaction_date' => '2026-01-11',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 14);

        $this->assertDatabaseHas('inventory_transaction_tbl', [
            'inventory_transaction_id' => 1,
            'bar_code' => 111009,
            'transaction_date' => '2026-01-11',
        ]);
        $this->assertEquals(14.0, (float) DB::table('inventory_item_tbl')->where('item_id', 1)->value('current_stock'));
    }

    public function test_unlinked_inventory_transaction_can_be_deleted_and_stock_is_recalculated(): void
    {
        $this->deleteJson('/api/inventory/transaction/1')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('inventory_transaction_tbl', ['inventory_transaction_id' => 1]);
        $this->assertEquals(0.0, (float) DB::table('inventory_item_tbl')->where('item_id', 1)->value('current_stock'));
    }

    public function test_finance_linked_inventory_transaction_cannot_be_deleted(): void
    {
        DB::table('fin_expense_tbl')->where('fin_expense_id', 1)->update(['inventory_transaction_id' => 1]);

        $this->deleteJson('/api/inventory/transaction/1')
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This transaction cannot be deleted because its Finance expense already has details.');

        $this->assertDatabaseHas('inventory_transaction_tbl', ['inventory_transaction_id' => 1]);
    }

    public function test_stock_in_persists_a_pending_construction_supply_expense(): void
    {
        Storage::fake('public');

        $this->post('/api/inventory/transaction', [
            'item_id' => 1,
            'transaction_type' => 'IN',
            'quantity' => 4,
            'bar_code' => 2800030,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $transactionId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');
        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => $transactionId,
            'project_id' => null,
            'fin_category_id' => 1,
            'project_cost_component' => 'material',
            'expense_description' => 'Purchased 4 bag of Cement',
            'amount' => null,
            'expense_date' => '2026-09-20',
            'remarks' => 'Inventory stock-in transaction. Receiving reference: 2800030',
        ]);
    }

    public function test_add_details_completes_the_persisted_stock_in_expense(): void
    {
        DB::table('fin_expense_tbl')->insert([
            'project_id' => null,
            'fin_category_id' => 1,
            'inventory_transaction_id' => 1,
            'project_cost_component' => 'material',
            'expense_description' => 'Purchased 10 bag of Cement',
            'amount' => null,
            'expense_date' => '2026-01-10',
            'remarks' => 'Inventory stock-in transaction. Receiving reference: 111001',
        ]);

        $this->postJson('/api/finance-expenses/from-inventory/1', ['project_id' => 1, 'amount' => 2500])
            ->assertOk()
            ->assertJsonPath('project_id', 1)
            ->assertJsonPath('amount', 2500);

        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => 1,
            'project_id' => 1,
            'amount' => 2500,
        ]);
    }

    public function test_construction_supply_expense_creates_inventory_stock_in(): void
    {
        $this->post('/api/finance-expenses', [
            'fin_category_id' => 1,
            'project_cost_component' => 'material',
            'expense_description' => 'ignored for synchronized purchases',
            'amount' => 1800,
            'expense_date' => '2026-09-20',
            'inventory_item_id' => 2,
            'inventory_quantity' => 4,
            'inventory_bar_code' => 2800030,
        ], ['Accept' => 'application/json'])->assertCreated();

        $transactionId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');
        $this->assertDatabaseHas('inventory_transaction_tbl', [
            'inventory_transaction_id' => $transactionId,
            'item_id' => 2,
            'project_id' => null,
            'transaction_type' => 'IN',
            'quantity' => 4,
            'bar_code' => 2800030,
        ]);
        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => $transactionId,
            'project_id' => null,
            'expense_description' => 'Purchased 4 box of Nails',
            'amount' => 1800,
        ]);
        $this->assertEquals(44.0, (float) DB::table('inventory_item_tbl')->where('item_id', 2)->value('current_stock'));
    }

    public function test_add_details_can_assign_a_project_when_inventory_purchase_already_has_an_amount(): void
    {
        DB::table('fin_expense_tbl')->insert([
            'project_id' => null,
            'fin_category_id' => 1,
            'inventory_transaction_id' => 1,
            'project_cost_component' => 'material',
            'expense_description' => 'Purchased 10 bag of Cement',
            'amount' => 2500,
            'expense_date' => '2026-01-10',
            'remarks' => 'Inventory stock-in transaction. Receiving reference: 111001',
        ]);

        $this->postJson('/api/finance-expenses/from-inventory/1', ['project_id' => 2, 'amount' => 2500])
            ->assertOk()
            ->assertJsonPath('project_id', 2)
            ->assertJsonPath('amount', 2500);
    }

    public function test_completed_stock_in_expense_details_can_be_edited_again(): void
    {
        DB::table('fin_expense_tbl')->insert([
            'project_id' => 1,
            'fin_category_id' => 1,
            'inventory_transaction_id' => 1,
            'project_cost_component' => 'material',
            'expense_description' => 'Purchased 10 bag of Cement',
            'amount' => 2500,
            'expense_date' => '2026-01-10',
            'remarks' => 'Inventory stock-in transaction. Receiving reference: 111001',
        ]);

        $this->postJson('/api/finance-expenses/from-inventory/1', ['project_id' => 2, 'amount' => 3000])
            ->assertOk()
            ->assertJsonPath('project_id', 2)
            ->assertJsonPath('amount', 3000)
            ->assertJsonPath('is_inventory_expense', true)
            ->assertJsonPath('is_pending_inventory', false);

        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => 1,
            'project_id' => 2,
            'amount' => 3000,
        ]);
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
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('expense_date');
            $table->string('remarks')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
            $table->timestamps();
        });
        Schema::create('company_bank_account_tbl', function (Blueprint $table) {
            $table->increments('account_id');
            $table->string('account_name');
            $table->string('account_type');
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
        Schema::create('notifications_tbl', function (Blueprint $table) {
            $table->id('notification_id');
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->string('kind')->default('info');
            $table->string('filter')->default('alerts');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }
}
