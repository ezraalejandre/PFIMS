<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProjectCostLedger;
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

        $this->getJson('/api/finance-expenses?include_pending=0')->assertOk()->assertJsonCount(3);
    }

    public function test_existing_administrative_expenses_are_detached_without_losing_totals(): void
    {
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 3, 'category_code' => 'OFFICE_RENT',
            'category_name' => 'Office rent', 'classification' => 'admin', 'is_active' => true,
        ]);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => 1, 'fin_category_id' => 3,
            'expense_description' => 'Office lease', 'amount' => 500,
            'expense_date' => '2026-01-21',
        ]);

        $expenseCount = DB::table('fin_expense_tbl')->count();
        $migration = require database_path('migrations/2026_09_29_000001_detach_office_expenses_from_projects.php');
        $migration->up();

        $this->getJson('/api/finance-expenses?category_id=3&include_pending=0')
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.project_name', 'Admin Expenses')
            ->assertJsonPath('0.is_office_expense', false)
            ->assertJsonPath('0.category_classification', 'admin');
        $this->assertDatabaseHas('fin_expense_tbl', [
            'fin_category_id' => 3, 'project_id' => null, 'amount' => 500,
        ]);
        $this->assertSame(1, DB::table('fin_expense_tbl')->where('fin_category_id', 3)->count());
        $this->assertSame($expenseCount, DB::table('fin_expense_tbl')->count());
    }

    public function test_finance_filter_inputs_are_validated_before_querying(): void
    {
        $this->getJson('/api/finance-expenses?start_date=2026-02-01&end_date=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/finance-expenses?category_id=999')->assertUnprocessable();
        $this->getJson('/api/construction-bonds?status=unknown')->assertUnprocessable();
        $this->getJson('/api/reports/backhoe-profitability?period=2026-01-02')->assertUnprocessable();
    }

    public function test_receivable_dates_accept_history_and_future_while_bonds_reject_future_records(): void
    {
        Schema::create('fin_receivable_payable_tbl', function (Blueprint $table) {
            $table->increments('rp_id');
            $table->string('entry_type');
            $table->integer('project_id')->nullable();
            $table->string('counterparty_name');
            $table->date('entry_date');
            foreach (['amount_30d', 'amount_31_60d', 'amount_61_90d', 'amount_91_120d'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->string('status');
            $table->string('remarks')->nullable();
        });
        Schema::create('fin_construction_bond_tbl', function (Blueprint $table) {
            $table->increments('bond_id');
            $table->integer('project_id');
            $table->date('bond_date');
            $table->decimal('amount', 14, 2);
            $table->string('bond_provider')->nullable();
            $table->string('status');
            $table->string('remarks')->nullable();
        });

        $past = today()->subDay()->toDateString();
        $future = today()->addDay()->toDateString();
        $receivable = ['entry_type' => 'accounts_receivable', 'counterparty_name' => 'Client', 'entry_date' => $past];
        $this->postJson('/api/receivables-payables', $receivable)->assertCreated();
        $this->postJson('/api/receivables-payables', [...$receivable, 'entry_date' => $future])->assertCreated();
        $this->putJson('/api/receivables-payables/1', ['entry_date' => today()->addDays(2)->toDateString()])->assertOk();
        $bond = ['project_id' => 1, 'bond_date' => $past, 'amount' => 100];
        $this->postJson('/api/construction-bonds', $bond)->assertCreated();
        $this->postJson('/api/construction-bonds', [...$bond, 'bond_date' => $future])->assertUnprocessable();
        $this->putJson('/api/construction-bonds/1', ['bond_date' => $future])->assertUnprocessable();
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
            'bar_code' => '01234567890123',
            'transaction_date' => '2026-01-11',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.quantity', 14);

        $this->assertDatabaseHas('inventory_transaction_tbl', [
            'inventory_transaction_id' => 1,
            'bar_code' => '01234567890123',
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

    public function test_purchase_stock_in_persists_a_valued_construction_supply_expense(): void
    {
        Storage::fake('public');

        $this->post('/api/inventory/transaction', [
            'item_id' => 1,
            'transaction_type' => 'IN',
            'movement_reason' => 'purchase',
            'purchase_amount' => 1200,
            'quantity' => 4,
            'bar_code' => '01234567890123',
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
            'amount' => 1200,
            'entry_kind' => 'inventory_purchase',
            'expense_date' => '2026-09-20',
            'remarks' => 'Inventory stock-in transaction. Receiving reference: 01234567890123',
        ]);
        $this->assertDatabaseHas('inventory_transaction_tbl', [
            'inventory_transaction_id' => $transactionId,
            'movement_reason' => 'purchase',
            'project_id' => null,
        ]);
        $this->getJson('/api/finance-expenses?include_pending=1')
            ->assertOk()
            ->assertJsonFragment([
                'inventory_transaction_id' => $transactionId,
                'entry_kind' => 'inventory_purchase',
                'is_pending_inventory' => false,
            ]);
    }

    public function test_inventory_transactions_can_be_added_without_proof_and_uploaded_proofs_are_still_validated(): void
    {
        Storage::fake('public');

        $this->postJson('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'adjustment',
            'quantity' => 1, 'transaction_date' => '2026-09-20',
        ])->assertCreated();
        $this->assertDatabaseHas('inventory_transaction_tbl', [
            'inventory_transaction_id' => 3, 'proof_file_path' => null, 'proof_file_name' => null,
        ]);

        $this->postJson('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 1, 'transaction_date' => '2026-09-20',
        ])->assertCreated();
        $this->assertDatabaseHas('inventory_transaction_tbl', [
            'inventory_transaction_id' => 4, 'proof_file_path' => null, 'proof_file_name' => null,
        ]);

        $this->postJson('/api/inventory/transaction', [
            'item_id' => 1, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
            'purchase_amount' => 300, 'quantity' => 2, 'transaction_date' => '2026-09-20',
        ])->assertCreated();
        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => 5, 'amount' => 300, 'proof_file_path' => null,
        ]);

        $this->post('/api/inventory/transaction', [
            'item_id' => 1, 'transaction_type' => 'IN', 'movement_reason' => 'adjustment',
            'quantity' => 3, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('proof_file');
    }

    public function test_purchase_requires_an_amount_and_adjustment_does_not_create_an_expense(): void
    {
        Storage::fake('public');
        $input = [
            'item_id' => 1,
            'transaction_type' => 'IN',
            'movement_reason' => 'purchase',
            'quantity' => 4,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'),
        ];
        $this->post('/api/inventory/transaction', $input, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('purchase_amount');
        $this->assertSame(2, DB::table('inventory_transaction_tbl')->count());

        $input['movement_reason'] = 'adjustment';
        $this->post('/api/inventory/transaction', $input, ['Accept' => 'application/json'])->assertCreated();
        $this->assertDatabaseHas('inventory_transaction_tbl', ['movement_reason' => 'adjustment', 'project_id' => null]);
        $this->assertSame(3, DB::table('fin_expense_tbl')->count());
        // The fourth row is the seeded, unclassified historical IN; the adjustment adds none.
        $this->getJson('/api/finance-expenses?include_pending=1')->assertOk()->assertJsonCount(4);
    }

    public function test_purchase_rolls_back_when_finance_category_is_unavailable(): void
    {
        Storage::fake('public');
        DB::table('fin_expense_category_tbl')->where('fin_category_id', 1)->update(['is_active' => false]);
        $this->post('/api/inventory/transaction', [
            'item_id' => 1,
            'transaction_type' => 'IN',
            'movement_reason' => 'purchase',
            'purchase_amount' => 1200,
            'quantity' => 4,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame(2, DB::table('inventory_transaction_tbl')->count());
        $this->assertSame(3, DB::table('fin_expense_tbl')->count());
        $this->assertEquals(8, DB::table('inventory_item_tbl')->where('item_id', 1)->value('current_stock'));
        $this->assertSame([], Storage::disk('public')->allFiles('inventory-transaction-proofs'));
    }

    public function test_purchase_amount_can_be_corrected_without_assigning_a_project(): void
    {
        Storage::fake('public');
        $this->post('/api/inventory/transaction', [
            'item_id' => 1,
            'transaction_type' => 'IN',
            'movement_reason' => 'purchase',
            'purchase_amount' => 1200,
            'quantity' => 4,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('delivery.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $transactionId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');

        $this->postJson('/api/finance-expenses/from-inventory/'.$transactionId, ['amount' => 1400])
            ->assertOk()->assertJsonPath('amount', 1400);
        $this->postJson('/api/finance-expenses/from-inventory/'.$transactionId, ['project_id' => 1, 'amount' => 1400])
            ->assertUnprocessable();
        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => $transactionId,
            'entry_kind' => 'inventory_purchase',
            'project_id' => null,
            'amount' => 1400,
        ]);
    }

    public function test_stock_out_allocates_fifo_purchase_cost_without_another_finance_expense(): void
    {
        Storage::fake('public');
        foreach ([[3, 1000, 700001], [2, 500, 700002]] as [$quantity, $amount, $barcode]) {
            $this->post('/api/inventory/transaction', [
                'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
                'purchase_amount' => $amount, 'quantity' => $quantity, 'bar_code' => $barcode,
                'transaction_date' => '2026-09-20',
                'proof_file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }
        $purchaseIds = DB::table('inventory_transaction_tbl')->where('item_id', 3)
            ->where('transaction_type', 'IN')->orderBy('inventory_transaction_id')->pluck('inventory_transaction_id');
        $financeCount = DB::table('fin_expense_tbl')->count();

        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 4, 'bar_code' => 700003, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $outId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');

        $this->assertDatabaseHas('inventory_cost_allocation_tbl', [
            'in_transaction_id' => $purchaseIds[0], 'out_transaction_id' => $outId,
            'project_id' => 1, 'quantity' => 3, 'valuation_status' => 'valued', 'allocated_amount' => 1000,
        ]);
        $this->assertDatabaseHas('inventory_cost_allocation_tbl', [
            'in_transaction_id' => $purchaseIds[1], 'out_transaction_id' => $outId,
            'project_id' => 1, 'quantity' => 1, 'valuation_status' => 'valued', 'allocated_amount' => 250,
        ]);
        $this->assertSame($financeCount, DB::table('fin_expense_tbl')->count());
        $this->assertEquals(1, DB::table('inventory_item_tbl')->where('item_id', 3)->value('current_stock'));
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 1, 'bar_code' => 700004, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->patchJson('/api/inventory/transaction/'.$outId, [
            'quantity' => 3, 'bar_code' => 700003, 'transaction_date' => '2026-09-20',
        ])->assertStatus(409);
        $this->deleteJson('/api/inventory/transaction/'.$outId)->assertStatus(409);
        $this->postJson('/api/finance-expenses/from-inventory/'.$purchaseIds[1], ['amount' => 600])->assertStatus(409);
    }

    public function test_project_cost_counts_stock_usage_once_not_the_storage_purchase(): void
    {
        Storage::fake('public');
        Schema::create('budgets_tbl', function (Blueprint $table) {
            $table->increments('budget_id');
            $table->integer('project_id');
            $table->decimal('budget_amount', 14, 2);
            $table->decimal('actual_amount', 14, 2)->default(0);
        });
        DB::table('budgets_tbl')->insert(['project_id' => 1, 'budget_amount' => 10000, 'actual_amount' => 0]);
        $ledger = app(ProjectCostLedger::class);
        $baseline = $ledger->forProject(1)['total'];

        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
            'purchase_amount' => 900, 'quantity' => 3, 'bar_code' => 710001,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertEquals($baseline, $ledger->forProject(1)['total']);

        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 2, 'bar_code' => 710002, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertEquals($baseline + 600, $ledger->forProject(1)['total']);
        $this->assertDatabaseHas('budgets_tbl', ['project_id' => 1, 'actual_amount' => $baseline + 600]);
    }

    public function test_latest_stock_out_can_be_corrected_and_deleted_with_its_allocations(): void
    {
        Storage::fake('public');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
            'purchase_amount' => 600, 'quantity' => 2, 'bar_code' => 700041,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $purchaseId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 1, 'bar_code' => 700042, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $outId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');

        $this->patchJson('/api/inventory/transaction/'.$outId, [
            'quantity' => 2, 'bar_code' => 700042, 'transaction_date' => '2026-09-20',
        ])->assertOk();
        $this->assertDatabaseHas('inventory_cost_allocation_tbl', [
            'out_transaction_id' => $outId, 'quantity' => 2, 'allocated_amount' => 600,
        ]);
        $this->deleteJson('/api/inventory/transaction/'.$outId)->assertOk();
        $this->assertDatabaseMissing('inventory_cost_allocation_tbl', ['out_transaction_id' => $outId]);
        $this->assertEquals(2, DB::table('inventory_item_tbl')->where('item_id', 3)->value('current_stock'));
        $this->deleteJson('/api/inventory/transaction/'.$purchaseId)->assertOk();
        $this->assertDatabaseMissing('fin_expense_tbl', ['inventory_transaction_id' => $purchaseId]);
    }

    public function test_stock_out_marks_adjustment_stock_unvalued_not_free(): void
    {
        Storage::fake('public');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'adjustment',
            'quantity' => 1, 'bar_code' => 700011, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('adjustment.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $adjustmentId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
            'purchase_amount' => 600, 'quantity' => 2, 'bar_code' => 700012,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $purchaseId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 2, 'bar_code' => 700013, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $outId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');

        $this->assertDatabaseHas('inventory_cost_allocation_tbl', [
            'in_transaction_id' => $adjustmentId, 'out_transaction_id' => $outId,
            'valuation_status' => 'unvalued', 'allocated_amount' => null,
        ]);
        $this->assertDatabaseHas('inventory_cost_allocation_tbl', [
            'in_transaction_id' => $purchaseId, 'out_transaction_id' => $outId,
            'valuation_status' => 'valued', 'allocated_amount' => 300,
        ]);
    }

    public function test_fifo_assigns_the_final_purchase_cent_to_the_last_withdrawal(): void
    {
        Storage::fake('public');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
            'purchase_amount' => 1000.01, 'quantity' => 3, 'bar_code' => 700021,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $purchaseId = DB::table('inventory_transaction_tbl')->max('inventory_transaction_id');

        foreach ([700022, 700023, 700024] as $barcode) {
            $this->post('/api/inventory/transaction', [
                'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
                'quantity' => 1, 'bar_code' => $barcode, 'transaction_date' => '2026-09-20',
                'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $amounts = DB::table('inventory_cost_allocation_tbl')->where('in_transaction_id', $purchaseId)
            ->orderBy('out_transaction_id')->pluck('allocated_amount')->map(fn ($amount) => (float) $amount)->all();
        $this->assertSame([333.34, 333.34, 333.33], $amounts);
    }

    public function test_unallocated_earlier_withdrawal_cannot_shift_cost_to_a_later_project(): void
    {
        Storage::fake('public');
        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'transaction_type' => 'IN', 'movement_reason' => 'purchase',
            'purchase_amount' => 600, 'quantity' => 2, 'bar_code' => 700031,
            'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();
        DB::table('inventory_transaction_tbl')->insert([
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 1, 'bar_code' => 700032, 'transaction_date' => '2026-09-20',
        ]);
        DB::table('inventory_item_tbl')->where('item_id', 3)->update(['current_stock' => 1]);
        $priorCount = DB::table('inventory_transaction_tbl')->count();

        $this->post('/api/inventory/transaction', [
            'item_id' => 3, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 1, 'bar_code' => 700033, 'transaction_date' => '2026-09-20',
            'proof_file' => UploadedFile::fake()->create('withdrawal.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertSame($priorCount, DB::table('inventory_transaction_tbl')->count());
        $this->assertEquals(1, DB::table('inventory_item_tbl')->where('item_id', 3)->value('current_stock'));
        $this->assertSame(0, DB::table('inventory_cost_allocation_tbl')->count());
    }

    public function test_historical_stock_in_cannot_be_assigned_whole_purchase_to_project(): void
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
            ->assertUnprocessable();

        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => 1,
            'project_id' => null,
            'amount' => null,
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

    public function test_historical_priced_stock_in_cannot_be_assigned_to_a_project(): void
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
            ->assertUnprocessable();
        $this->assertDatabaseHas('fin_expense_tbl', ['inventory_transaction_id' => 1, 'project_id' => null, 'amount' => 2500]);
    }

    public function test_legacy_project_linked_stock_in_cannot_be_reassigned(): void
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
            ->assertUnprocessable();

        $this->assertDatabaseHas('fin_expense_tbl', [
            'inventory_transaction_id' => 1,
            'project_id' => 1,
            'amount' => 2500,
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
            $table->string('entry_kind', 32)->nullable();
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
            $table->string('movement_reason', 32)->nullable();
            $table->dateTime('recorded_at', 6)->nullable();
            $table->decimal('quantity', 14, 2);
            $table->string('bar_code', 64)->nullable();
            $table->date('transaction_date');
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
        });
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('in_transaction_id');
            $table->integer('out_transaction_id');
            $table->integer('project_id');
            $table->decimal('quantity', 10, 2);
            $table->string('valuation_status', 16);
            $table->decimal('unit_cost', 18, 6)->nullable();
            $table->decimal('allocated_amount', 14, 2)->nullable();
            $table->dateTime('allocated_at', 6);
            $table->unique(['in_transaction_id', 'out_transaction_id']);
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
