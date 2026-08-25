<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class DataImportAndConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        $this->createSchema();
        $this->seedLookups();
    }

    public function test_import_endpoints_require_authentication_and_module_role(): void
    {
        $file = UploadedFile::fake()->createWithContent('expenses.csv', "category_code,expense_description,amount,expense_date\nCONST_SUPPLY,Cement,10,2026-01-01\n");
        $this->post('/api/imports/finance-expenses', ['file' => $file])->assertRedirect('/');

        $operations = $this->user('operations');
        $file = UploadedFile::fake()->createWithContent('expenses.csv', "category_code,expense_description,amount,expense_date\nCONST_SUPPLY,Cement,10,2026-01-01\n");
        $this->actingAs($operations)->postJson('/api/imports/finance-expenses', ['file' => $file])->assertForbidden();

        $accounting = $this->user('accounting');
        $file = UploadedFile::fake()->createWithContent('items.csv', "item_name,category,supplier,unit,current_stock,reorder_level\nCement,Materials,Build Supply,Bag,10,2\n");
        $this->actingAs($accounting)->postJson('/api/imports/inventory', ['type' => 'items', 'file' => $file])->assertForbidden();
    }

    public function test_finance_csv_import_is_transactional_and_reports_duplicate_row_numbers(): void
    {
        $admin = $this->user('admin');
        $csv = "project_name,category_code,project_cost_component,expense_description,amount,expense_date,remarks\nAlpha Project,CONST_SUPPLY,material,Cement delivery,25000.00,2026-01-10,Initial import\n";
        $response = $this->actingAs($admin)->postJson('/api/imports/finance-expenses', [
            'file' => UploadedFile::fake()->createWithContent('expenses.csv', $csv),
        ]);
        $response->assertCreated()->assertJsonPath('data.imported', 1);
        $this->assertDatabaseHas('fin_expense_tbl', ['expense_description' => 'Cement delivery', 'amount' => 25000, 'project_cost_component' => 'material']);

        $duplicateAndNew = "project_name,category_code,project_cost_component,expense_description,amount,expense_date,remarks\nAlpha Project,CONST_SUPPLY,material,Cement delivery,25000.00,2026-01-10,Duplicate\nAlpha Project,CONST_SUPPLY,material,New valid row,99.00,2026-01-11,Must roll back\n";
        $response = $this->actingAs($admin)->postJson('/api/imports/finance-expenses', [
            'file' => UploadedFile::fake()->createWithContent('expenses.csv', $duplicateAndNew),
        ]);
        $response->assertUnprocessable()->assertJsonPath('errors.0.row', 2);
        $this->assertDatabaseCount('fin_expense_tbl', 1);
    }

    public function test_inventory_csv_items_and_xlsx_transactions_use_existing_tables_and_update_stock(): void
    {
        $operations = $this->user('operations');
        $items = "item_name,category,supplier,unit,current_stock,reorder_level,opening_balance_date\nPortland Cement,Materials,Build Supply,Bag,100,25,2026-01-01\n";
        $this->actingAs($operations)->postJson('/api/imports/inventory', [
            'type' => 'items',
            'file' => UploadedFile::fake()->createWithContent('items.csv', $items),
        ])->assertCreated()->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('inventory_item_tbl', ['item_name' => 'Portland Cement', 'current_stock' => 100]);
        $this->assertDatabaseHas('inventory_transaction_tbl', ['transaction_type' => 'IN', 'quantity' => 100]);

        $xlsxPath = $this->xlsx([
            ['item_name', 'project_name', 'transaction_type', 'quantity', 'bar_code', 'transaction_date'],
            ['Portland Cement', 'Alpha Project', 'OUT', '10', '100001', '2026-01-12'],
        ]);
        $upload = new UploadedFile($xlsxPath, 'transactions.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->actingAs($operations)->postJson('/api/imports/inventory', ['type' => 'transactions', 'file' => $upload])
            ->assertCreated()->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('inventory_item_tbl', ['item_name' => 'Portland Cement', 'current_stock' => 90]);
        $this->assertDatabaseHas('inventory_transaction_tbl', ['transaction_type' => 'OUT', 'quantity' => 10, 'bar_code' => 100001]);
        @unlink($xlsxPath);
    }

    public function test_configuration_crud_uses_finance_categories_and_rejects_duplicates(): void
    {
        $admin = $this->user('admin');
        $response = $this->actingAs($admin)->postJson('/api/config/exp_categories', [
            'category_code' => 'permit fees',
            'category_name' => 'Permit Fees',
            'classification' => 'admin',
            'is_active' => '1',
        ]);
        $response->assertCreated()->assertJsonPath('data.category_code', 'PERMIT_FEES');
        $this->assertDatabaseHas('fin_expense_category_tbl', ['category_code' => 'PERMIT_FEES', 'category_name' => 'Permit Fees']);

        $this->actingAs($admin)->postJson('/api/config/exp_categories', [
            'category_code' => 'ANOTHER_CODE',
            'category_name' => 'permit fees',
            'classification' => 'direct',
            'is_active' => '1',
        ])->assertStatus(409);

        $this->actingAs($this->user('operations'))->getJson('/api/config/units')->assertForbidden();
    }

    public function test_direct_finance_expenses_require_project_and_cost_component(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->postJson('/api/finance-expenses', [
            'fin_category_id' => 1,
            'expense_description' => 'Unassigned direct cost',
            'amount' => 1200,
            'expense_date' => '2026-01-10',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id', 'project_cost_component']);

        DB::table('fin_expense_tbl')->insert([
            'project_id' => 1,
            'fin_category_id' => 1,
            'project_cost_component' => 'labor',
            'expense_description' => 'Site labor',
            'amount' => 1500,
            'expense_date' => '2026-01-11',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expenseId = (int) DB::table('fin_expense_tbl')->value('fin_expense_id');
        $this->actingAs($admin)->putJson('/api/finance-expenses/'.$expenseId, [
            'project_id' => 1,
            'fin_category_id' => 1,
            'project_cost_component' => 'equipment',
            'expense_description' => 'Site labor updated',
            'amount' => 1750,
            'expense_date' => '2026-01-11',
        ])->assertOk()
            ->assertJsonPath('project_cost_component', 'equipment');

        $this->assertDatabaseHas('fin_expense_tbl', [
            'fin_expense_id' => $expenseId,
            'project_cost_component' => 'equipment',
            'expense_description' => 'Site labor updated',
        ]);
    }

    public function test_admin_finance_expense_import_remains_project_optional(): void
    {
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 2,
            'category_code' => 'RENT',
            'category_name' => 'Rent',
            'classification' => 'admin',
            'is_active' => true,
        ]);

        $admin = $this->user('admin');
        $csv = "project_name,category_code,expense_description,amount,expense_date,remarks\n,RENT,Office rent,5000.00,2026-01-15,Admin import\n";
        $this->actingAs($admin)->postJson('/api/imports/finance-expenses', [
            'file' => UploadedFile::fake()->createWithContent('admin-expenses.csv', $csv),
        ])->assertCreated()->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('fin_expense_tbl', [
            'project_id' => null,
            'fin_category_id' => 2,
            'project_cost_component' => null,
            'expense_description' => 'Office rent',
        ]);
    }

    public function test_finance_import_rejects_direct_rows_without_cost_component(): void
    {
        $admin = $this->user('admin');
        $csv = "project_name,category_code,expense_description,amount,expense_date,remarks\nAlpha Project,CONST_SUPPLY,Cement delivery,25000.00,2026-01-10,Missing component\n";
        $this->actingAs($admin)->postJson('/api/imports/finance-expenses', [
            'file' => UploadedFile::fake()->createWithContent('expenses.csv', $csv),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'project_cost_component');

        $this->assertDatabaseCount('fin_expense_tbl', 0);
    }

    public function test_finance_import_template_includes_cost_component_header(): void
    {
        $admin = $this->user('admin');

        $response = $this->actingAs($admin)->get('/api/imports/templates/finance-expenses');
        $response->assertOk();
        $this->assertStringContainsString(
            'project_name,category_code,project_cost_component,expense_description,amount,expense_date,remarks',
            $response->streamedContent()
        );
    }

    public function test_normal_inventory_and_finance_inputs_reject_natural_key_duplicates(): void
    {
        $admin = $this->user('admin');
        DB::table('inventory_item_tbl')->insert([
            'item_id' => 1, 'item_name' => 'Portland Cement', 'inventory_category_id' => 1,
            'supplier_id' => 1, 'unit_id' => 1, 'current_stock' => 0, 'reorder_level' => 10,
        ]);
        $this->actingAs($admin)->postJson('/api/inventory/item', [
            'item_name' => '  portland cement ', 'inventory_category_id' => 1, 'supplier_id' => 1,
            'unit_id' => 1, 'current_stock' => 0, 'reorder_level' => 10,
        ])->assertStatus(409);

        DB::table('fin_expense_tbl')->insert([
            'project_id' => 1, 'fin_category_id' => 1, 'project_cost_component' => 'material', 'expense_description' => 'Cement delivery',
            'amount' => 25000, 'expense_date' => '2026-01-10', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->postJson('/api/finance-expenses', [
            'project_id' => 1, 'fin_category_id' => 1, 'project_cost_component' => 'material', 'expense_description' => ' cement delivery ',
            'amount' => 25000, 'expense_date' => '2026-01-10',
        ])->assertStatus(409);
    }

    private function user(string $role): User
    {
        return User::query()->create([
            'name' => ucfirst($role),
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function seedLookups(): void
    {
        DB::table('project_tbl')->insert(['project_id' => 1, 'project_name' => 'Alpha Project']);
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 1, 'category_code' => 'CONST_SUPPLY', 'category_name' => 'Construction Supply', 'classification' => 'direct', 'is_active' => true,
        ]);
        DB::table('inventory_category_tbl')->insert(['inventory_category_id' => 1, 'inventory_category_name' => 'Materials']);
        DB::table('supplier_tbl')->insert(['supplier_id' => 1, 'supplier_name' => 'Build Supply', 'address' => 'Manila', 'contact_number' => '09170000000']);
        DB::table('unit_tbl')->insert(['unit_id' => 1, 'unit_name' => 'Bag']);
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
            $table->timestamps();
        });
        Schema::create('inventory_category_tbl', function (Blueprint $table) {
            $table->increments('inventory_category_id');
            $table->string('inventory_category_name');
        });
        Schema::create('supplier_tbl', function (Blueprint $table) {
            $table->increments('supplier_id');
            $table->string('supplier_name');
            $table->string('address');
            $table->string('contact_number');
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

    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pfims-xlsx-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>');
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = chr(65 + $columnIndex).($rowIndex + 1);
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }
}
