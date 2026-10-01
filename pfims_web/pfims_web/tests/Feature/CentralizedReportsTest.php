<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CentralizedReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    public function test_report_upload_endpoint_no_longer_exists(): void
    {
        $this->post('/api/reports/upload')->assertMethodNotAllowed();
    }

    public function test_admin_can_filter_a_live_project_report_and_receive_matching_kpis(): void
    {
        $admin = $this->user('admin');
        DB::table('project_tbl')->insert([
            ['project_id' => 1, 'project_name' => 'Alpha Site', 'client_name' => 'Client A', 'project_manager' => 'Ana', 'start_date' => '2026-01-01', 'estimated_end_date' => '2026-10-01', 'worker_count' => 20, 'phase' => 'Build', 'completion_percentage' => 50, 'status' => 'Ongoing'],
            ['project_id' => 2, 'project_name' => 'Beta Site', 'client_name' => 'Client B', 'project_manager' => 'Ben', 'start_date' => '2026-02-01', 'estimated_end_date' => '2026-11-01', 'worker_count' => 10, 'phase' => 'Design', 'completion_percentage' => 100, 'status' => 'Completed'],
        ]);
        DB::table('budgets_tbl')->insert([
            ['budget_id' => 1, 'project_id' => 1, 'budget_amount' => 100000, 'actual_amount' => 60000],
            ['budget_id' => 2, 'project_id' => 2, 'budget_amount' => 200000, 'actual_amount' => 190000],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/data/project?status=Ongoing');

        $response->assertOk()
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('rows.0.project_name', 'Alpha Site')
            ->assertJsonPath('kpis.0.value', '1');
    }

    public function test_budget_report_separates_direct_expenses_and_inventory_usage(): void
    {
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('project_id');
            $table->string('valuation_status');
            $table->decimal('allocated_amount', 14, 2)->nullable();
        });
        DB::table('project_tbl')->insert([
            'project_id' => 1, 'project_name' => 'Costed Site', 'client_name' => 'A',
            'start_date' => '2026-01-01', 'status' => 'Ongoing',
        ]);
        DB::table('budgets_tbl')->insert([
            'budget_id' => 1, 'project_id' => 1, 'budget_amount' => 10000, 'actual_amount' => 9999,
        ]);
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 1, 'category_code' => 'LABOR', 'category_name' => 'Labor', 'classification' => 'direct',
        ]);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => 1, 'fin_category_id' => 1, 'expense_description' => 'Crew',
            'amount' => 200, 'expense_date' => '2026-02-01',
        ]);
        DB::table('inventory_cost_allocation_tbl')->insert([
            ['project_id' => 1, 'valuation_status' => 'valued', 'allocated_amount' => 300],
            ['project_id' => 1, 'valuation_status' => 'unvalued', 'allocated_amount' => null],
        ]);

        $this->actingAs($this->user('admin'))->getJson('/api/reports/data/budget')
            ->assertOk()->assertJsonPath('rows.0.direct_cost', 200)
            ->assertJsonPath('rows.0.inventory_usage_cost', 300)
            ->assertJsonPath('rows.0.unvalued_withdrawals', 1)
            ->assertJsonPath('rows.0.actual_amount', 500);
    }

    public function test_role_catalog_only_exposes_authorized_report_tabs(): void
    {
        $accounting = $this->user('accounting');

        $this->actingAs($accounting)->getJson('/api/reports/catalog')
            ->assertOk()
            ->assertJsonCount(1, 'datasets')
            ->assertJsonPath('datasets.0.key', 'expense_summary')
            ->assertJsonPath('datasets.0.title', 'Expenses Summary');

        $this->actingAs($accounting)->getJson('/api/reports/data/project')->assertForbidden();

        $this->actingAs($this->user('admin'))->getJson('/api/reports/catalog')
            ->assertOk()
            ->assertJsonCount(2, 'datasets')
            ->assertJsonPath('datasets.0.title', 'Expenses Summary')
            ->assertJsonPath('datasets.1.key', 'inventory')
            ->assertJsonPath('datasets.1.filters', ['search', 'category_id', 'supplier_id', 'stock_status']);

        $this->actingAs($this->user('operations'))->getJson('/api/reports/catalog')
            ->assertOk()
            ->assertJsonCount(1, 'datasets')
            ->assertJsonPath('datasets.0.key', 'inventory')
            ->assertJsonMissing(['key' => 'workforce']);

        $this->getJson('/api/reports/data/workforce')->assertNotFound();
    }

    public function test_inventory_report_uses_item_columns_and_item_filters_not_expense_summary(): void
    {
        DB::table('inventory_category_tbl')->insert([
            ['inventory_category_id' => 1, 'inventory_category_name' => 'Hardware'],
            ['inventory_category_id' => 2, 'inventory_category_name' => 'Paint'],
        ]);
        DB::table('supplier_tbl')->insert([
            ['supplier_id' => 1, 'supplier_name' => 'Supplier A'],
            ['supplier_id' => 2, 'supplier_name' => 'Supplier B'],
        ]);
        DB::table('unit_tbl')->insert(['unit_id' => 1, 'unit_name' => 'pcs']);
        DB::table('inventory_item_tbl')->insert([
            ['item_id' => 1, 'item_name' => 'Hammer', 'inventory_category_id' => 1, 'supplier_id' => 1, 'unit_id' => 1, 'unit_price' => 450.25, 'current_stock' => 12, 'reorder_level' => 5],
            ['item_id' => 2, 'item_name' => 'Blue Paint', 'inventory_category_id' => 2, 'supplier_id' => 2, 'unit_id' => 1, 'unit_price' => 120.50, 'current_stock' => 0, 'reorder_level' => 5],
        ]);

        $this->actingAs($this->user('admin'))
            ->getJson('/api/reports/data/inventory?category_id=2&supplier_id=2&stock_status=Out%20of%20Stock')
            ->assertOk()
            ->assertJsonPath('dataset', 'inventory')
            ->assertJsonPath('columns.item_name', 'Item')
            ->assertJsonPath('columns.unit_price', 'Unit Price')
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('rows.0.item_name', 'Blue Paint')
            ->assertJsonPath('rows.0.unit_price', 120.5)
            ->assertJsonPath('rows.0.category_name', 'Paint')
            ->assertJsonPath('rows.0.stock_status', 'Out of Stock')
            ->assertJsonMissing(['project_name' => 'Blue Paint']);

        $this->postJson('/api/reports/preview', [
            'dataset' => 'inventory', 'title' => 'Inventory Items',
            'columns' => ['item_name', 'current_stock'],
            'filters' => ['category_id' => 1],
        ])->assertOk()
            ->assertJsonPath('row_count', 1)
            ->assertJsonPath('rows.0.item_name', 'Hammer')
            ->assertJsonPath('columns.current_stock', 'Current Stock');
    }

    public function test_export_row_limit_matches_preview_and_downloaded_inventory_file(): void
    {
        Storage::fake('public');
        DB::table('inventory_item_tbl')->insert(array_map(fn (int $number) => [
            'item_id' => $number, 'item_name' => sprintf('Item %02d', $number),
            'current_stock' => $number, 'reorder_level' => 1,
        ], range(1, 7)));

        $config = ['dataset' => 'inventory', 'title' => 'Limited Inventory',
            'columns' => ['item_name', 'current_stock'], 'row_limit' => 5];
        $this->actingAs($this->user('admin'))->postJson('/api/reports/preview', $config)
            ->assertOk()->assertJsonPath('row_count', 5)->assertJsonCount(5, 'rows');
        $this->postJson('/api/reports/export', $config + ['format' => 'csv'])->assertOk();

        $report = Report::query()->firstOrFail();
        $csv = Storage::disk('public')->get($report->file_path);
        $this->assertSame(5, $report->row_count);
        $this->assertSame(5, $report->export_options['row_limit']);
        $this->assertStringContainsString('Item 01', $csv);
        $this->assertStringNotContainsString('Item 07', $csv);
        $this->postJson('/api/reports/preview', array_diff_key($config, ['row_limit' => true]))
            ->assertOk()->assertJsonPath('row_count', 7);
        $this->postJson('/api/reports/preview', array_merge($config, ['row_limit' => 0]))->assertUnprocessable();
    }

    public function test_limited_expense_export_totals_only_include_exported_projects(): void
    {
        DB::table('project_tbl')->insert([
            ['project_id' => 1, 'project_name' => 'Alpha Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01'],
            ['project_id' => 2, 'project_name' => 'Beta Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01'],
        ]);
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 1, 'category_code' => 'CONSTRUCTION_SUPPLY',
            'category_name' => 'Construction Supply', 'classification' => 'direct',
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 25, 'expense_date' => '2026-08-31'],
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 75, 'expense_date' => '2026-09-30'],
            ['project_id' => 2, 'fin_category_id' => 1, 'amount' => 200, 'expense_date' => '2026-09-30'],
        ]);

        $this->actingAs($this->user('admin'))->postJson('/api/reports/preview', [
            'dataset' => 'expense_summary', 'title' => 'One Project',
            'columns' => ['project_name', 'construction_supply', 'total'], 'row_limit' => 1,
            'filters' => ['report_month' => 9, 'report_day' => 30, 'report_year' => 2026],
        ])->assertOk()->assertJsonPath('row_count', 1)
            ->assertJsonPath('rows.0.project_name', 'Alpha Site')
            ->assertJsonPath('totals.total', 100)
            ->assertJsonPath('previous_totals.total', 25)
            ->assertJsonPath('month_totals.total', 75);
    }

    public function test_expenses_summary_uses_ongoing_project_costs_without_storage_or_office_spending(): void
    {
        DB::table('project_tbl')->insert([
            ['project_id' => 1, 'project_name' => 'Active Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01'],
            ['project_id' => 2, 'project_name' => 'Finished Site', 'status' => 'Completed', 'start_date' => '2026-01-01'],
        ]);
        DB::table('fin_expense_category_tbl')->insert([
            ['fin_category_id' => 1, 'category_code' => 'CONSTRUCTION_SUPPLY', 'category_name' => 'Construction supplies', 'classification' => 'direct'],
            ['fin_category_id' => 2, 'category_code' => 'SALARIES_WAGES', 'category_name' => 'Site salaries and wages', 'classification' => 'direct'],
            ['fin_category_id' => 3, 'category_code' => 'OFFICE_RENT', 'category_name' => 'Office rent', 'classification' => 'admin'],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 100, 'expense_date' => '2026-02-01', 'inventory_transaction_id' => null],
            ['project_id' => 1, 'fin_category_id' => 2, 'amount' => 50, 'expense_date' => '2026-02-02', 'inventory_transaction_id' => null],
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 300, 'expense_date' => '2026-02-02', 'inventory_transaction_id' => 10],
            ['project_id' => null, 'fin_category_id' => 3, 'amount' => 25, 'expense_date' => '2026-02-02', 'inventory_transaction_id' => null],
            ['project_id' => 2, 'fin_category_id' => 1, 'amount' => 70, 'expense_date' => '2026-02-02', 'inventory_transaction_id' => null],
        ]);
        DB::table('inventory_transaction_tbl')->insert([
            'inventory_transaction_id' => 11, 'project_id' => 1, 'transaction_type' => 'OUT',
            'quantity' => 2, 'transaction_date' => '2026-02-03',
        ]);
        Schema::create('inventory_cost_allocation_tbl', function (Blueprint $table) {
            $table->bigIncrements('allocation_id');
            $table->integer('project_id');
            $table->integer('out_transaction_id');
            $table->string('valuation_status');
            $table->decimal('allocated_amount', 14, 2)->nullable();
        });
        DB::table('inventory_cost_allocation_tbl')->insert([
            'project_id' => 1, 'out_transaction_id' => 11,
            'valuation_status' => 'valued', 'allocated_amount' => 80,
        ]);

        $this->actingAs($this->user('admin'))->getJson('/api/reports/data/expense_summary')
            ->assertOk()->assertJsonPath('total_rows', 1)
            ->assertJsonPath('rows.0.project_name', 'Active Site')
            ->assertJsonPath('rows.0.construction_supply', 180)
            ->assertJsonPath('rows.0.salaries_wages', 50)
            ->assertJsonPath('rows.0.total', 230)
            ->assertJsonPath('totals.total', 230);
    }

    public function test_expense_summary_filters_by_calendar_project_status_and_expense_type(): void
    {
        DB::table('project_tbl')->insert([
            ['project_id' => 1, 'project_name' => 'Active Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01'],
            ['project_id' => 2, 'project_name' => 'Completed Site', 'status' => 'Completed', 'start_date' => '2026-01-01'],
        ]);
        DB::table('fin_expense_category_tbl')->insert([
            ['fin_category_id' => 1, 'category_code' => 'CONSTRUCTION_SUPPLY', 'category_name' => 'Construction Supply', 'classification' => 'direct'],
            ['fin_category_id' => 2, 'category_code' => 'RENT_EXPENSE', 'category_name' => 'Rent Expense', 'classification' => 'admin'],
            ['fin_category_id' => 3, 'category_code' => 'LEGACY', 'category_name' => 'Legacy', 'classification' => 'overall'],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 100, 'expense_date' => '2026-09-30'],
            ['project_id' => 1, 'fin_category_id' => 2, 'amount' => 20, 'expense_date' => '2026-09-30'],
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 50, 'expense_date' => '2026-09-29'],
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 40, 'expense_date' => '2026-08-31'],
            ['project_id' => 2, 'fin_category_id' => 1, 'amount' => 80, 'expense_date' => '2026-09-30'],
            ['project_id' => null, 'fin_category_id' => 2, 'amount' => 999, 'expense_date' => '2026-09-30'],
            ['project_id' => 1, 'fin_category_id' => 3, 'amount' => 999, 'expense_date' => '2026-09-30'],
        ]);

        $this->actingAs($this->user('admin'))->getJson('/api/reports/catalog')
            ->assertOk()->assertJsonPath('datasets.0.filters', [
                'search', 'report_month', 'report_day', 'report_year', 'project_status', 'expense_type',
            ]);
        $base = '/api/reports/data/expense_summary?report_month=9&report_day=30&report_year=2026';
        $this->getJson($base.'&project_status=Ongoing&expense_type=Overall')
            ->assertOk()->assertJsonPath('total_rows', 1)->assertJsonPath('rows.0.total', 210)
            ->assertJsonPath('totals.project_name', 'TOTAL(As of Current Month)')
            ->assertJsonPath('totals.total', 210)
            ->assertJsonPath('previous_totals.total', 40)
            ->assertJsonPath('month_totals.total', 170);
        $this->getJson($base.'&project_status=Ongoing&expense_type=Direct')
            ->assertOk()->assertJsonPath('rows.0.total', 190)->assertJsonPath('rows.0.administrative_expenses', 0)
            ->assertJsonPath('previous_totals.total', 40)->assertJsonPath('month_totals.total', 150);
        $this->getJson($base.'&project_status=Ongoing&expense_type=Admin')
            ->assertOk()->assertJsonPath('rows.0.total', 20)->assertJsonPath('rows.0.construction_supply', 0)
            ->assertJsonPath('previous_totals.total', 0)->assertJsonPath('month_totals.total', 20);
        $overall = $this->getJson($base.'&project_status=Ongoing&expense_type=Overall')->json();
        $direct = $this->getJson($base.'&project_status=Ongoing&expense_type=Direct')->json();
        $admin = $this->getJson($base.'&project_status=Ongoing&expense_type=Admin')->json();
        foreach (['rows.0', 'totals', 'previous_totals', 'month_totals'] as $path) {
            $this->assertEquals(
                data_get($direct, $path.'.total') + data_get($admin, $path.'.total'),
                data_get($overall, $path.'.total')
            );
        }
        $this->getJson('/api/reports/data/expense_summary?report_month=9&report_day=29&report_year=2026&project_status=Ongoing')
            ->assertOk()->assertJsonPath('rows.0.total', 90)
            ->assertJsonPath('previous_totals.total', 40)->assertJsonPath('month_totals.total', 50);
        $this->getJson($base.'&project_status=Completed&expense_type=Direct')
            ->assertOk()->assertJsonPath('rows.0.project_name', 'Completed Site')->assertJsonPath('rows.0.total', 80);
        $this->getJson('/api/reports/data/expense_summary?report_month=09&report_day=30&report_year=2026&project_status=Ongoing')
            ->assertOk()->assertJsonPath('rows.0.total', 210);
        $this->postJson('/api/reports/preview', [
            'dataset' => 'expense_summary', 'title' => 'Admin September 30',
            'columns' => ['project_name', 'administrative_expenses', 'total'],
            'filters' => ['report_month' => 9, 'report_day' => 30, 'report_year' => 2026,
                'project_status' => 'Ongoing', 'expense_type' => 'Admin'],
        ])->assertOk()->assertJsonPath('rows.0.total', 20)->assertJsonPath('totals.total', 20);
        $this->getJson('/api/reports/data/expense_summary?report_month=13')
            ->assertUnprocessable();
    }

    public function test_new_report_preview_and_all_file_formats_include_selected_columns(): void
    {
        Storage::fake('public');
        DB::table('project_tbl')->insert([
            'project_id' => 1, 'project_name' => 'Preview Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01',
        ]);
        $admin = $this->user('admin');
        $config = [
            'dataset' => 'expense_summary', 'title' => 'Summary of Expenses',
            'columns' => ['project_name', 'construction_supply', 'total'], 'filters' => [],
        ];
        $this->actingAs($admin)->postJson('/api/reports/preview', $config)
            ->assertOk()->assertJsonPath('row_count', 1)
            ->assertJsonPath('rows.0.project_name', 'Preview Site')
            ->assertJsonPath('totals.total', 0)
            ->assertJsonPath('previous_totals.total', 0)
            ->assertJsonPath('month_totals.total', 0);

        foreach (['csv', 'xlsx', 'pdf'] as $format) {
            $this->actingAs($admin)->postJson('/api/reports/export', $config + ['format' => $format])->assertOk();
            $report = Report::query()->where('export_format', $format)->firstOrFail();
            $bytes = Storage::disk('public')->get($report->file_path);
            $this->assertSame(['project_name', 'construction_supply', 'total'], $report->selected_columns);
            $this->assertNotEmpty($bytes);
            if ($format === 'pdf') $this->assertStringStartsWith('%PDF', $bytes);
            if ($format === 'csv') {
                $this->assertStringContainsString('TOTAL BALANCE(As of Previous Month)', $bytes);
                $this->assertStringContainsString('TOTAL(This Month)', $bytes);
            }
            if ($format === 'xlsx') {
                $this->assertStringStartsWith('PK', $bytes);
                $zipPath = Storage::disk('public')->path($report->file_path);
                $zip = new \ZipArchive;
                $this->assertTrue($zip->open($zipPath) === true);
                $this->assertNotFalse($zip->getFromName('xl/media/report-header.jpeg'));
                $this->assertStringContainsString('Preview Site', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('TOTAL BALANCE(As of Previous Month)', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('FF176638', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('FFF8FAFC', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('FF475569', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('horizontal="center"', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('<borders count="2">', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('ht="42"', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('PROJECT', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('FFF2F4F7', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('FFFDEAEA', $zip->getFromName('xl/styles.xml'));
                $this->assertStringContainsString('<c r="C9" s="16"', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('<c r="A10" s="1"', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('<c r="A11" s="10"', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('<c r="A12" s="12"', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $this->assertStringContainsString('<c r="A13" s="14"', $zip->getFromName('xl/worksheets/sheet1.xml'));
                $zip->close();
            }
        }
    }

    public function test_expense_summary_preview_and_export_totals_follow_selected_categories_and_date_filter(): void
    {
        Storage::fake('public');
        DB::table('project_tbl')->insert([
            'project_id' => 1, 'project_name' => 'Filtered Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01',
        ]);
        DB::table('fin_expense_category_tbl')->insert([
            ['fin_category_id' => 1, 'category_code' => 'CONSTRUCTION_SUPPLY', 'category_name' => 'Construction supplies', 'classification' => 'direct'],
            ['fin_category_id' => 2, 'category_code' => 'SALARIES_WAGES', 'category_name' => 'Site salaries and wages', 'classification' => 'direct'],
        ]);
        DB::table('fin_expense_tbl')->insert([
            ['project_id' => 1, 'fin_category_id' => 1, 'amount' => 100, 'expense_date' => '2026-02-01'],
            ['project_id' => 1, 'fin_category_id' => 2, 'amount' => 50, 'expense_date' => '2026-02-02'],
        ]);
        $config = [
            'dataset' => 'expense_summary', 'title' => 'Filtered Summary',
            'columns' => ['project_name', 'salaries_wages', 'total'],
            'filters' => ['date_from' => '2026-02-02', 'date_to' => '2026-02-02'],
        ];

        $this->actingAs($this->user('admin'))->postJson('/api/reports/preview', $config)
            ->assertOk()->assertJsonPath('rows.0.total', 50)->assertJsonPath('totals.total', 50);
        $this->postJson('/api/reports/export', $config + ['format' => 'csv'])->assertOk();
        $report = Report::query()->where('export_format', 'csv')->firstOrFail();
        $csv = Storage::disk('public')->get($report->file_path);
        $this->assertStringContainsString('"Filtered Site",50,50', $csv);
        $this->assertStringNotContainsString('100', $csv);
    }

    public function test_contracts_are_reported_in_reports_and_old_finance_link_redirects(): void
    {
        $reportsView = file_get_contents(resource_path('views/reports.blade.php'));
        $this->assertStringNotContainsString('id="addContract"', $reportsView);
        $this->assertStringNotContainsString('id="contractDialog"', $reportsView);
        $this->assertStringNotContainsString("apiJson('/api/project-contracts/", $reportsView);
        $this->assertStringContainsString('if (request !== datasetRequest || key !== state.dataset) return;', $reportsView);
        $this->assertStringContainsString('dataset: key,', $reportsView);
        $this->assertStringContainsString('id="dataBody"', $reportsView);
        $this->assertStringContainsString('class="report-contract-row"', $reportsView);
        DB::table('project_tbl')->insert([
            'project_id' => 4, 'project_name' => 'Contract Site', 'status' => 'Ongoing', 'start_date' => '2026-01-01',
        ]);
        DB::table('budgets_tbl')->insert(['budget_id' => 4, 'project_id' => 4, 'budget_amount' => 1000]);
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 4, 'category_code' => 'SALARIES_WAGES',
            'category_name' => 'Site salaries and wages', 'classification' => 'direct',
        ]);
        DB::table('fin_expense_tbl')->insert([
            'project_id' => 4, 'fin_category_id' => 4, 'expense_description' => 'Crew',
            'amount' => 150, 'expense_date' => '2026-02-01',
        ]);
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/finance?section=budgets')->assertOk()
            ->assertSee('id="addContractModal"', false)
            ->assertSee('id="contractsSubpanel"', false)
            ->assertSee('id="profitTable"', false)
            ->assertSee('onclick="openAddContractModal()"', false);
        $this->get('/afinance?section=contracts')->assertRedirect('/afinance?section=budgets&subtab=contracts');
        $this->actingAs($admin)->postJson('/api/project-contracts', [
            'project_id' => 4, 'original_contract_price' => 900,
            'additional_works_contract' => 100, 'original_payment_received' => 400,
            'additional_works_payment' => 50, 'remarks' => 'Signed',
        ])->assertCreated();
        $this->actingAs($admin)->getJson('/api/reports/data/contracts?project_id=4')
            ->assertOk()->assertJsonPath('total_rows', 1)
            ->assertJsonPath('columns.additional_works_contract', 'Addl. Works')
            ->assertJsonPath('columns.additional_works_payment', 'Addl. Payment')
            ->assertJsonPath('rows.0.project_name', 'Contract Site')
            ->assertJsonPath('rows.0.original_contract_price', 1000)
            ->assertJsonPath('rows.0.project_expense', 150)
            ->assertJsonPath('rows.0.total_contract_price', 1100)
            ->assertJsonPath('rows.0.accounts_receivable', 650);
        $this->get('/finance?section=contracts')->assertRedirect('/finance?section=budgets&subtab=contracts');
        $this->actingAs($this->user('operations'))->getJson('/api/reports/data/contracts')->assertForbidden();
    }

    public function test_live_report_records_and_export_history_are_paginated(): void
    {
        $admin = $this->user('admin');

        for ($index = 1; $index <= 12; $index++) {
            DB::table('project_tbl')->insert([
                'project_id' => $index,
                'project_name' => 'Project '.$index,
                'client_name' => 'Client',
                'project_manager' => 'Manager',
                'start_date' => sprintf('2026-01-%02d', $index),
                'estimated_end_date' => '2026-12-31',
                'worker_count' => 10,
                'phase' => 'Build',
                'completion_percentage' => 50,
                'status' => 'Ongoing',
            ]);
            DB::table('budgets_tbl')->insert([
                'budget_id' => $index,
                'project_id' => $index,
                'budget_amount' => 100000,
                'actual_amount' => 50000,
            ]);
            DB::table('reports')->insert([
                'report_id' => sprintf('RPT-%04d', $index),
                'title' => 'Project export '.$index,
                'type' => 'project',
                'role' => 'admin',
                'file_name' => "project-{$index}.csv",
                'file_path' => "reports/project-{$index}.csv",
                'date_uploaded' => '2026-01-01',
                'uploaded_by' => $admin->name,
                'status' => 'Completed',
                'generation_method' => 'system_export',
                'dataset_key' => 'project',
                'export_format' => 'csv',
                'generated_at' => now()->subSeconds($index),
                'user_id' => $admin->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($admin)->getJson('/api/reports/data/project?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'rows')
            ->assertJsonPath('rows.0.project_id', 7)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonPath('pagination.last_page', 3)
            ->assertJsonPath('pagination.per_page', 5)
            ->assertJsonPath('pagination.from', 6)
            ->assertJsonPath('pagination.to', 10)
            ->assertJsonPath('pagination.total', 12);

        $this->actingAs($admin)->getJson('/api/reports?dataset=project&per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('total', 12);
    }

    public function test_every_role_report_page_uses_the_shared_shell_and_both_paginators(): void
    {
        $pages = [
            ['role' => 'admin', 'path' => '/reports', 'links' => ['/dashboard', '/reports']],
            ['role' => 'accounting', 'path' => '/areports', 'links' => ['/adashboard', '/areports']],
            ['role' => 'operations', 'path' => '/oreports', 'links' => ['/odashboard', '/oreports']],
        ];

        foreach ($pages as $page) {
            $response = $this->actingAs($this->user($page['role']))
                ->get($page['path'])
                ->assertOk()
                ->assertSee('<body class="reports-page" data-portal="'.$page['role'].'" data-reports-api-base=', false)
                ->assertSee('data-reports-api-base="/api/reports"', false)
                ->assertSee('id="reportCatalogBootstrap"', false)
                ->assertDontSee('apiJson(`${reportsApiBase}/catalog`)', false)
                ->assertSee('class="top-header"', false)
                ->assertSee('class="sidebar"', false)
                ->assertSee('class="bottom-nav"', false)
                ->assertSee('class="main-content"', false)
                ->assertSee('id="dataPagination"', false)
                ->assertSee('id="filterReportMonth" type="hidden"', false)
                ->assertSee('id="filterReportDay" type="hidden"', false)
                ->assertSee('id="filterReportYear" type="hidden"', false)
                ->assertSee('data-date-part="report_month"', false)
                ->assertSee('data-date-part="report_day"', false)
                ->assertSee('data-date-part="report_year"', false)
                ->assertSee('Array.from({ length: daysInSelectedMonth() }', false)
                ->assertSee("button.addEventListener('click', event => {", false)
                ->assertSee('id="filterProjectStatus" data-default="Ongoing"', false)
                ->assertSee('id="filterExpenseType" data-default="Overall"', false)
                ->assertSee('control.remove();', false)
                ->assertDontSee('control.hidden = !state.definition.filters.includes(control.dataset.filter)', false)
                ->assertSee("document.getElementById('kpiGrid').hidden = key !== 'inventory'", false)
                ->assertSee('Inventory Items Summary', false)
                ->assertSee('clearFilters.parentNode === filterGrid ? clearFilters : null', false)
                ->assertDontSee('class="panel content-card chart-panel"', false)
                ->assertDontSee('Calculated from all records matching the active filters.', false)
                ->assertSee('id="dataPageSize"', false)
                ->assertSee('id="historyPagination"', false)
                ->assertSee('id="historyPageSize"', false)
                ->assertSee('data-pfims-standard-actions="off"', false)
                ->assertDontSee('id="editExportDesign"', false)
                ->assertDontSee('id="exportDesignControls"', false)
                ->assertSee('class="pfims-row-action history-view-button"', false)
                ->assertSee('class="pfims-row-action history-download-button"', false)
                ->assertSee('download title="Download report"', false)
                ->assertSee('data-report-view-icon=', false)
                ->assertSee('data-report-download-icon=', false)
                ->assertSee('id="closeHistoryDetail" aria-label="Close">×</button>', false)
                ->assertDontSee('id="cancelHistoryDetail"', false)
                ->assertSee('action="http://localhost/logout"', false)
                ->assertDontSee('Workforce Allocation');

            foreach ($page['links'] as $link) {
                $response->assertSee($link, false);
            }
        }
        $pickerCss = file_get_contents(public_path('css/centralized-reports.css'));
        $this->assertStringContainsString('body.reports-page .report-date-picker :is(.report-date-options button, .report-year-navigation button)', $pickerCss);
        $this->assertStringContainsString('font-weight: 400 !important;', $pickerCss);
        $this->assertStringContainsString('body.reports-page .report-date-picker .report-date-options button[aria-pressed="true"] { font-weight: 700 !important; }', $pickerCss);
        $this->assertStringContainsString('.report-year-navigation span { font-weight: 400; }', $pickerCss);
        $this->assertStringContainsString('.preview-paper th:not(.report-total-column) { background: #f8fafc !important; color: #475569 !important;', $pickerCss);
        $this->assertStringContainsString('.preview-paper table { min-width: 940px; border-collapse: collapse !important; }', $pickerCss);
        $this->assertStringContainsString('class="report-stock-status is-${stockTone}"', file_get_contents(resource_path('views/reports.blade.php')));
        $this->assertStringContainsString('.reports-page .report-stock-status.is-danger', $pickerCss);
        $this->assertStringContainsString("table.closest('.report-preview')", file_get_contents(public_path('js/pfims-system-ui.js')));
    }

    public function test_configured_csv_export_is_downloaded_and_persisted_as_export_history(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        DB::table('project_tbl')->insert([
            'project_id' => 7, 'project_name' => 'Export Project', 'client_name' => 'Client',
            'project_manager' => 'Manager', 'start_date' => '2026-03-01', 'estimated_end_date' => '2026-12-01',
            'worker_count' => 12, 'phase' => 'Construction', 'completion_percentage' => 30, 'status' => 'Ongoing',
        ]);
        DB::table('budgets_tbl')->insert(['budget_id' => 7, 'project_id' => 7, 'budget_amount' => 500000, 'actual_amount' => 100000]);

        $response = $this->actingAs($admin)->postJson('/api/reports/export', [
            'dataset' => 'project', 'title' => 'Filtered Project Export', 'format' => 'csv',
            'columns' => ['project_name', 'status', 'budget_amount'],
            'sections' => ['summary', 'kpis', 'data'],
            'filters' => ['project_id' => 7, 'status' => 'Ongoing'],
        ]);

        $response->assertOk();
        $report = Report::query()->firstOrFail();
        $this->assertSame('system_export', $report->generation_method);
        $this->assertSame('project', $report->dataset_key);
        $this->assertSame(1, $report->row_count);
        $this->assertSame(['project_name', 'status', 'budget_amount'], $report->selected_columns);
        $this->assertSame(['summary', 'kpis', 'data'], $report->export_options['sections']);
        $this->assertTrue(Storage::disk('public')->exists($report->file_path));

        $history = $this->actingAs($admin)->getJson('/api/reports?dataset=project');
        $history->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.report_id', $report->report_id);

        $download = $this->actingAs($admin)->get('/api/reports/download/'.$report->report_id);
        $download->assertOk()->assertDownload($report->file_name);
        $this->assertSame(Storage::disk('public')->get($report->file_path), $download->streamedContent());
    }

    public function test_dashboard_filters_update_kpis_charts_and_project_rows_together(): void
    {
        $admin = $this->user('admin');
        DB::table('project_tbl')->insert([
            ['project_id' => 21, 'project_name' => 'Filtered Project', 'client_name' => 'A', 'project_manager' => 'One', 'start_date' => now()->startOfMonth()->toDateString(), 'estimated_end_date' => now()->addMonth()->toDateString(), 'worker_count' => 8, 'phase' => 'Build', 'completion_percentage' => 40, 'status' => 'Ongoing'],
            ['project_id' => 22, 'project_name' => 'Excluded Project', 'client_name' => 'B', 'project_manager' => 'Two', 'start_date' => now()->startOfMonth()->toDateString(), 'estimated_end_date' => now()->addMonth()->toDateString(), 'worker_count' => 12, 'phase' => 'Build', 'completion_percentage' => 80, 'status' => 'Delayed'],
        ]);
        DB::table('budgets_tbl')->insert([
            ['budget_id' => 21, 'project_id' => 21, 'budget_amount' => 100000, 'actual_amount' => 25000],
            ['budget_id' => 22, 'project_id' => 22, 'budget_amount' => 200000, 'actual_amount' => 100000],
        ]);
        DB::table('fin_expense_category_tbl')->insert(['fin_category_id' => 1, 'category_code' => 'LABOR', 'category_name' => 'Labor', 'classification' => 'direct']);
        DB::table('fin_expense_tbl')->insert(['fin_expense_id' => 1, 'project_id' => 21, 'fin_category_id' => 1, 'expense_description' => 'Work', 'amount' => 25000, 'expense_date' => now()->toDateString()]);
        DB::table('expense_tbl')->insert(['project_id' => 21, 'material_amount' => 900000, 'labor_amount' => 800000, 'equipment_amount' => 0, 'other_amount' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?status=Ongoing');

        $response->assertOk()
            ->assertJsonPath('filters.status', 'Ongoing')
            ->assertJsonPath('stat_cards.0.value', '1')
            ->assertJsonPath('stat_cards.2.value', '0')
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.name', 'Filtered Project')
            ->assertJsonPath('project_status.labels.0', 'Ongoing')
            ->assertJsonPath('project_status.values.0', 1);
    }

    public function test_dashboard_stock_status_filter_updates_the_inventory_kpi(): void
    {
        $admin = $this->user('admin');
        DB::table('inventory_item_tbl')->insert([
            ['item_id' => 1, 'item_name' => 'Healthy Stock', 'current_stock' => 25, 'reorder_level' => 5],
            ['item_id' => 2, 'item_name' => 'Low Stock', 'current_stock' => 3, 'reorder_level' => 5],
            ['item_id' => 3, 'item_name' => 'No Stock', 'current_stock' => 0, 'reorder_level' => 5],
        ]);

        $this->actingAs($admin)->getJson('/api/dashboard?stock_status=Low%20stock')
            ->assertOk()
            ->assertJsonPath('filters.stock_status', 'Low stock')
            ->assertJsonPath('stat_cards.2.label', 'Inventory Items')
            ->assertJsonPath('stat_cards.2.value', '1')
            ->assertJsonPath('stock_status.labels.0', 'Low stock')
            ->assertJsonPath('stock_status.values.0', 1);

        $this->getJson('/api/dashboard?stock_status=Unknown')->assertUnprocessable();
    }

    public function test_finance_report_uses_finance_expense_categories_and_ignores_outdated_expense_rows(): void
    {
        $accounting = $this->user('accounting');
        DB::table('project_tbl')->insert([
            'project_id' => 31, 'project_name' => 'Finance Ledger Project', 'client_name' => 'A',
            'project_manager' => 'One', 'start_date' => '2026-01-01', 'estimated_end_date' => '2026-06-01',
            'worker_count' => 8, 'phase' => 'Build', 'completion_percentage' => 100, 'status' => 'Completed',
        ]);
        DB::table('fin_expense_category_tbl')->insert(['fin_category_id' => 7, 'category_code' => 'CONST_SUPPLY', 'category_name' => 'Construction Supply', 'classification' => 'direct']);
        DB::table('fin_expense_tbl')->insert(['fin_expense_id' => 7, 'project_id' => 31, 'fin_category_id' => 7, 'expense_description' => 'Cement', 'amount' => 12345, 'expense_date' => '2026-02-01']);
        DB::table('expense_tbl')->insert(['project_id' => 31, 'material_amount' => 999999, 'labor_amount' => 999999, 'equipment_amount' => 0, 'other_amount' => 0]);

        $response = $this->actingAs($accounting)->getJson('/api/reports/data/finance?project_id=31');

        $response->assertOk()
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('rows.0.category_name', 'Construction Supply')
            ->assertJsonPath('rows.0.amount', 12345)
            ->assertJsonPath('kpis.1.value', '₱12,345.00')
            ->assertJsonPath('kpis.2.value', '₱12,345.00');
    }

    private function user(string $role): User
    {
        return User::query()->create([
            'name' => ucfirst($role), 'email' => $role.'@example.test', 'password' => 'password', 'role' => $role,
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
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('project_tbl', function (Blueprint $table) {
            $table->integer('project_id')->primary();
            $table->string('project_name')->nullable();
            $table->string('client_name')->nullable();
            $table->string('project_manager')->nullable();
            $table->date('start_date')->nullable();
            $table->date('estimated_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->integer('worker_count')->nullable();
            $table->string('phase')->nullable();
            $table->decimal('completion_percentage', 5, 2)->nullable();
            $table->string('status')->nullable();
        });
        Schema::create('budgets_tbl', function (Blueprint $table) {
            $table->integer('budget_id')->primary();
            $table->integer('project_id');
            $table->decimal('budget_amount', 12, 2);
            $table->decimal('actual_amount', 12, 2)->nullable();
        });
        Schema::create('fin_expense_category_tbl', function (Blueprint $table) {
            $table->increments('fin_category_id');
            $table->string('category_code');
            $table->string('category_name');
            $table->string('classification');
        });
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->integer('project_id')->nullable();
            $table->unsignedInteger('fin_category_id');
            $table->string('expense_description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('remarks')->nullable();
            $table->integer('inventory_transaction_id')->nullable();
        });
        Schema::create('fin_project_contract_tbl', function (Blueprint $table) {
            $table->increments('contract_id');
            $table->integer('project_id');
            $table->decimal('original_contract_price', 14, 2);
            $table->decimal('additional_works_contract', 14, 2)->default(0);
            $table->decimal('original_payment_received', 14, 2)->default(0);
            $table->decimal('additional_works_payment', 14, 2)->default(0);
            $table->string('remarks')->nullable();
        });
        Schema::create('expense_tbl', function (Blueprint $table) {
            $table->increments('expense_id');
            $table->integer('project_id')->nullable();
            $table->decimal('material_amount', 12, 2)->nullable();
            $table->decimal('labor_amount', 12, 2)->nullable();
            $table->decimal('equipment_amount', 12, 2)->nullable();
            $table->decimal('other_amount', 12, 2)->nullable();
        });
        Schema::create('inventory_category_tbl', function (Blueprint $table) {
            $table->integer('inventory_category_id')->primary();
            $table->string('inventory_category_name')->nullable();
        });
        Schema::create('supplier_tbl', function (Blueprint $table) {
            $table->integer('supplier_id')->primary();
            $table->string('supplier_name')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
        });
        Schema::create('unit_tbl', function (Blueprint $table) {
            $table->integer('unit_id')->primary();
            $table->string('unit_name')->nullable();
        });
        Schema::create('inventory_item_tbl', function (Blueprint $table) {
            $table->integer('item_id')->primary();
            $table->integer('inventory_category_id')->nullable();
            $table->integer('supplier_id')->nullable();
            $table->integer('unit_id')->nullable();
            $table->string('item_name')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('current_stock', 10, 2)->nullable();
            $table->decimal('reorder_level', 10, 2)->nullable();
        });
        Schema::create('inventory_transaction_tbl', function (Blueprint $table) {
            $table->integer('inventory_transaction_id')->primary();
            $table->integer('item_id')->nullable();
            $table->integer('project_id')->nullable();
            $table->string('transaction_type')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->date('transaction_date')->nullable();
        });
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_id')->unique();
            $table->string('title');
            $table->string('type');
            $table->string('role');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->date('date_uploaded');
            $table->string('uploaded_by');
            $table->string('status');
            $table->string('generation_method')->default('legacy_upload');
            $table->string('dataset_key')->nullable();
            $table->string('export_format')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->json('selected_columns')->nullable();
            $table->json('filters_applied')->nullable();
            $table->json('export_options')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }
}
