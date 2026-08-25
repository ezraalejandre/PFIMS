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
            ['project_id' => 1, 'project_name' => 'Alpha Site', 'client_name' => 'Client A', 'project_manager' => 'Ana', 'start_date' => '2026-01-01', 'estimated_end_date' => '2026-10-01', 'worker_count' => 20, 'phase' => 'Build', 'completion_percentage' => 50, 'status' => 'On Track'],
            ['project_id' => 2, 'project_name' => 'Beta Site', 'client_name' => 'Client B', 'project_manager' => 'Ben', 'start_date' => '2026-02-01', 'estimated_end_date' => '2026-11-01', 'worker_count' => 10, 'phase' => 'Design', 'completion_percentage' => 100, 'status' => 'Completed'],
        ]);
        DB::table('budgets_tbl')->insert([
            ['budget_id' => 1, 'project_id' => 1, 'budget_amount' => 100000, 'actual_amount' => 60000],
            ['budget_id' => 2, 'project_id' => 2, 'budget_amount' => 200000, 'actual_amount' => 190000],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/data/project?status=On%20Track');

        $response->assertOk()
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('rows.0.project_name', 'Alpha Site')
            ->assertJsonPath('kpis.0.value', '1');
    }

    public function test_role_catalog_only_exposes_authorized_report_tabs(): void
    {
        $accounting = $this->user('accounting');

        $this->actingAs($accounting)->getJson('/api/reports/catalog')
            ->assertOk()
            ->assertJsonCount(2, 'datasets')
            ->assertJsonPath('datasets.0.key', 'finance')
            ->assertJsonPath('datasets.0.title', 'Finance')
            ->assertJsonPath('datasets.1.key', 'budget')
            ->assertJsonPath('datasets.1.title', 'Budget');

        $this->actingAs($accounting)->getJson('/api/reports/data/project')->assertForbidden();

        $this->actingAs($this->user('admin'))->getJson('/api/reports/catalog')
            ->assertOk()
            ->assertJsonCount(5, 'datasets')
            ->assertJsonPath('datasets.0.title', 'Project')
            ->assertJsonPath('datasets.1.title', 'Finance')
            ->assertJsonPath('datasets.2.title', 'Budget')
            ->assertJsonPath('datasets.3.title', 'Inventory')
            ->assertJsonPath('datasets.4.title', 'Supplier');

        $this->actingAs($this->user('operations'))->getJson('/api/reports/catalog')
            ->assertOk()
            ->assertJsonCount(3, 'datasets')
            ->assertJsonMissing(['key' => 'workforce']);

        $this->getJson('/api/reports/data/workforce')->assertNotFound();
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
                'status' => 'On Track',
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
                ->assertSee('<body class="reports-page">', false)
                ->assertSee('class="top-header"', false)
                ->assertSee('class="sidebar"', false)
                ->assertSee('class="bottom-nav"', false)
                ->assertSee('class="main-content"', false)
                ->assertSee('id="dataPagination"', false)
                ->assertSee('id="dataPageSize"', false)
                ->assertSee('id="historyPagination"', false)
                ->assertSee('id="historyPageSize"', false)
                ->assertSee('action="http://localhost/logout"', false)
                ->assertDontSee('Workforce Allocation');

            foreach ($page['links'] as $link) {
                $response->assertSee($link, false);
            }
        }
    }

    public function test_configured_csv_export_is_downloaded_and_persisted_as_export_history(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        DB::table('project_tbl')->insert([
            'project_id' => 7, 'project_name' => 'Export Project', 'client_name' => 'Client',
            'project_manager' => 'Manager', 'start_date' => '2026-03-01', 'estimated_end_date' => '2026-12-01',
            'worker_count' => 12, 'phase' => 'Construction', 'completion_percentage' => 30, 'status' => 'On Track',
        ]);
        DB::table('budgets_tbl')->insert(['budget_id' => 7, 'project_id' => 7, 'budget_amount' => 500000, 'actual_amount' => 100000]);

        $response = $this->actingAs($admin)->postJson('/api/reports/export', [
            'dataset' => 'project', 'title' => 'Filtered Project Export', 'format' => 'csv',
            'columns' => ['project_name', 'status', 'budget_amount'],
            'sections' => ['summary', 'kpis', 'data'],
            'filters' => ['project_id' => 7, 'status' => 'On Track'],
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
    }

    public function test_dashboard_filters_update_kpis_charts_and_project_rows_together(): void
    {
        $admin = $this->user('admin');
        DB::table('project_tbl')->insert([
            ['project_id' => 21, 'project_name' => 'Filtered Project', 'client_name' => 'A', 'project_manager' => 'One', 'start_date' => now()->startOfMonth()->toDateString(), 'estimated_end_date' => now()->addMonth()->toDateString(), 'worker_count' => 8, 'phase' => 'Build', 'completion_percentage' => 40, 'status' => 'On Track'],
            ['project_id' => 22, 'project_name' => 'Excluded Project', 'client_name' => 'B', 'project_manager' => 'Two', 'start_date' => now()->startOfMonth()->toDateString(), 'estimated_end_date' => now()->addMonth()->toDateString(), 'worker_count' => 12, 'phase' => 'Build', 'completion_percentage' => 80, 'status' => 'Delayed'],
        ]);
        DB::table('budgets_tbl')->insert([
            ['budget_id' => 21, 'project_id' => 21, 'budget_amount' => 100000, 'actual_amount' => 25000],
            ['budget_id' => 22, 'project_id' => 22, 'budget_amount' => 200000, 'actual_amount' => 100000],
        ]);
        DB::table('fin_expense_category_tbl')->insert(['fin_category_id' => 1, 'category_code' => 'LABOR', 'category_name' => 'Labor', 'classification' => 'direct']);
        DB::table('fin_expense_tbl')->insert(['fin_expense_id' => 1, 'project_id' => 21, 'fin_category_id' => 1, 'expense_description' => 'Work', 'amount' => 25000, 'expense_date' => now()->toDateString()]);
        DB::table('expense_tbl')->insert(['project_id' => 21, 'material_amount' => 900000, 'labor_amount' => 800000, 'equipment_amount' => 0, 'other_amount' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/dashboard?status=On%20Track');

        $response->assertOk()
            ->assertJsonPath('filters.status', 'On Track')
            ->assertJsonPath('stat_cards.0.value', '1')
            ->assertJsonPath('stat_cards.2.value', '₱25,000.00')
            ->assertJsonCount(1, 'projects')
            ->assertJsonPath('projects.0.name', 'Filtered Project')
            ->assertJsonPath('project_status.labels.0', 'On Track')
            ->assertJsonPath('project_status.values.0', 1);
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
