<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CoreIntegrityProjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropAllTables();
        $this->createSchema();
    }

    public function test_sensitive_project_lookup_budget_and_expense_routes_require_a_web_session(): void
    {
        foreach (['/api/projects', '/api/projects/list', '/api/units', '/api/expense-categories', '/api/budgets', '/api/expenses'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }

        $this->postJson('/api/projects', [])->assertUnauthorized();
        $this->postJson('/api/budgets', [])->assertUnauthorized();
        $this->postJson('/api/expenses', [])->assertUnauthorized();

        $this->actingAs($this->user())->getJson('/api/units')->assertOk();
    }

    public function test_project_validation_and_normalized_natural_key_prevent_duplicates(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->postJson('/api/projects', [
            'project_name' => str_repeat('A', 151),
            'client_name' => 'Client',
            'project_manager' => 'Manager',
            'start_date' => '1999-12-31',
            'estimated_end_date' => '1999-01-01',
            'worker_count' => 100001,
            'completion_percentage' => 101,
            'phase' => 'Unknown',
            'status' => 'Unknown',
            'budget' => 1000000000000,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'project_name', 'start_date', 'estimated_end_date', 'worker_count',
            'completion_percentage', 'phase', 'status', 'budget',
        ]);

        $payload = [
            'project_name' => '  North   Tower ',
            'client_name' => ' Acme   Holdings ',
            'project_manager' => 'A. Santos',
            'start_date' => '2026-01-10',
            'estimated_end_date' => '2026-08-10',
            'worker_count' => 25,
            'phase' => 'Planning',
            'status' => 'On Track',
            'completion_percentage' => 0,
        ];
        $this->actingAs($admin)->postJson('/api/projects', $payload)
            ->assertCreated()->assertJsonPath('project_name', 'North Tower');

        $payload['project_name'] = 'north tower';
        $payload['client_name'] = 'ACME HOLDINGS';
        $this->actingAs($admin)->postJson('/api/projects', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('project_name');
        $this->assertDatabaseCount('project_tbl', 1);
    }

    public function test_project_update_keeps_budget_out_of_project_table_and_updates_budget_atomically(): void
    {
        $admin = $this->user();
        $projectId = $this->project([
            'project_name' => 'Original Project',
            'client_name' => 'Client A',
            'start_date' => '2026-01-01',
        ]);
        DB::table('budgets_tbl')->insert([
            'project_id' => $projectId, 'budget_amount' => 100000, 'actual_amount' => 500,
        ]);

        $this->actingAs($admin)->putJson("/api/projects/{$projectId}", [
            'project_name' => 'Updated Project',
            'budget' => 125000,
            'completion_percentage' => 25,
        ])->assertOk()->assertJsonPath('budget', 125000);

        $this->assertDatabaseHas('project_tbl', [
            'project_id' => $projectId, 'project_name' => 'Updated Project', 'completion_percentage' => 25,
        ]);
        $this->assertDatabaseHas('budgets_tbl', [
            'project_id' => $projectId, 'budget_amount' => 125000, 'actual_amount' => 500,
        ]);
        $this->assertFalse(Schema::hasColumn('project_tbl', 'budget'));
    }

    public function test_project_api_filters_search_status_phase_and_start_date_range(): void
    {
        $admin = $this->user();
        $matching = $this->project([
            'project_name' => 'Civic Center', 'client_name' => 'City Government',
            'project_manager' => 'Maria Cruz', 'start_date' => '2026-03-10',
            'phase' => 'Structure', 'status' => 'On Track',
        ]);
        $this->project([
            'project_name' => 'Old Warehouse', 'client_name' => 'Private Client',
            'project_manager' => 'Juan Reyes', 'start_date' => '2025-01-10',
            'phase' => 'Planning', 'status' => 'Delayed',
        ]);

        $this->actingAs($admin)->getJson('/api/projects?search=Maria&status=On%20Track&phase=Structure&start_date=2026-01-01&end_date=2026-12-31')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.project_id', $matching);
        $this->actingAs($admin)->getJson('/api/projects?status=NotReal')->assertUnprocessable();
    }

    public function test_budget_and_expense_validation_reject_duplicates_and_invalid_relations(): void
    {
        $admin = $this->user();
        $projectId = $this->project(['start_date' => '2026-01-10', 'actual_end_date' => '2026-07-10']);
        DB::table('fin_expense_category_tbl')->insert([
            'fin_category_id' => 1,
            'category_code' => 'CONST_SUPPLY',
            'category_name' => 'Construction Supply',
            'classification' => 'direct',
            'is_active' => true,
        ]);
        DB::table('unit_tbl')->insert(['unit_id' => 1, 'unit_name' => 'Bag']);

        $this->actingAs($admin)->postJson('/api/budgets', [
            'project_id' => $projectId, 'budget_amount' => 500000,
        ])->assertCreated();
        $this->actingAs($admin)->postJson('/api/budgets', [
            'project_id' => $projectId, 'budget_amount' => 600000,
        ])->assertConflict();

        $invalid = $this->actingAs($admin)->postJson('/api/expenses', [
            'project_id' => $projectId,
            'expense_category_id' => 1,
            'expense_description' => 'Cement delivery',
            'amount' => 0,
            'expense_date' => '2026-01-01',
            'remarks' => str_repeat('x', 1001),
            'unit_id' => 999,
        ]);
        $invalid->assertUnprocessable()->assertJsonValidationErrors(['amount', 'expense_date', 'remarks', 'unit_id']);

        $expense = [
            'project_id' => $projectId,
            'expense_category_id' => 1,
            'expense_description' => ' Cement   delivery ',
            'amount' => 25000,
            'expense_date' => '2026-02-10',
            'unit_id' => 1,
        ];
        $this->actingAs($admin)->postJson('/api/expenses', $expense)->assertCreated();
        $expense['expense_description'] = 'cement delivery';
        $this->actingAs($admin)->postJson('/api/expenses', $expense)
            ->assertUnprocessable()->assertJsonValidationErrors('expense_description');
        $this->assertDatabaseCount('fin_expense_tbl', 1);
        $this->assertFalse(Schema::hasTable('expense_tbl'));
        $this->assertDatabaseHas('budgets_tbl', ['project_id' => $projectId, 'actual_amount' => 25000]);
    }

    public function test_only_admins_manage_users_and_email_duplicates_are_case_normalized(): void
    {
        $admin = $this->user('admin', 'admin@example.test');
        $operations = $this->user('operations', 'ops@example.test');

        $this->actingAs($operations)->postJson('/users', [
            'name' => 'Blocked User', 'email' => 'blocked@example.test', 'role' => 'operations', 'status' => 'Active',
        ])->assertForbidden();

        $this->actingAs($admin)->postJson('/users', [
            'name' => 'New User', 'email' => 'PERSON@EXAMPLE.TEST', 'role' => 'Operations', 'status' => 'Active',
        ])->assertOk()->assertJsonPath('user.email', 'person@example.test');
        $this->actingAs($admin)->postJson('/users', [
            'name' => 'Duplicate', 'email' => 'person@example.test', 'role' => 'operations', 'status' => 'Active',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_and_operations_project_views_render_working_filter_kpi_and_chart_hooks(): void
    {
        $adminView = $this->actingAs($this->user('admin'))->get('/projects')->assertOk();
        $operationsView = $this->actingAs($this->user('operations'))->get('/oprojects')->assertOk();

        foreach ([$adminView, $operationsView] as $response) {
            $response->assertSee('id="projectStatusFilter"', false)
                ->assertSee('id="projectPhaseFilter"', false)
                ->assertSee('id="projectDateFrom"', false)
                ->assertSee('id="projectDateTo"', false)
                ->assertSee('id="activeProjectsCount"', false)
                ->assertSee('id="avgCompletion"', false)
                ->assertSee('id="projectStatusChart"', false)
                ->assertSee('function filterProjects()', false)
                ->assertSee('function refreshProjectAnalytics(projects)', false)
                ->assertSee('function renderStatusChart(projects)', false);
        }
        $operationsView->assertSee('/odashboard', false)->assertSee('/oinventory', false);
    }

    public function test_every_role_dashboard_uses_the_shared_application_shell_and_philippine_clock(): void
    {
        $dashboards = [
            ['role' => 'admin', 'path' => '/dashboard', 'links' => ['/dashboard', '/finance', '/inventory']],
            ['role' => 'accounting', 'path' => '/adashboard', 'links' => ['/adashboard', '/afinance', '/areports']],
            ['role' => 'operations', 'path' => '/odashboard', 'links' => ['/odashboard', '/oprojects', '/oinventory']],
        ];

        foreach ($dashboards as $dashboard) {
            $response = $this->actingAs($this->user($dashboard['role']))
                ->get($dashboard['path'])
                ->assertOk()
                ->assertSee('<body class="dashboard-page">', false)
                ->assertSee('class="top-header"', false)
                ->assertSee('class="sidebar"', false)
                ->assertSee('class="bottom-nav"', false)
                ->assertSee('class="main-content"', false)
                ->assertSee('id="dashboardTime"', false)
                ->assertSee('id="dashboardDate"', false)
                ->assertSee('id="dashboardPagination"', false)
                ->assertSee('id="pageSize"', false)
                ->assertSee('id="dashboardRange"', false)
                ->assertSee('id="dashboardPaginationLinks"', false)
                ->assertSee('class="panel chart-card budget-panel"', false)
                ->assertSee('id="projectDetailModal"', false)
                ->assertSee('class="dashboard-project-row"', false)
                ->assertSee('function openProjectDetail(project, trigger)', false)
                ->assertSee("['Enter', ' ']", false)
                ->assertSee('Rows per page', false)
                ->assertSee("timeZone: 'Asia/Manila'", false)
                ->assertSee('action="http://localhost/logout"', false);

            foreach ($dashboard['links'] as $link) {
                $response->assertSee($link, false);
            }

            if (in_array($dashboard['role'], ['admin', 'accounting'], true)) {
                $response->assertSee('id="overviewTab"', false)
                    ->assertSee('id="predictionTab"', false)
                    ->assertSee('class="predictive-analytics-root embedded-ml-dashboard"', false)
                    ->assertDontSee('id="predictiveAnalyticsFrame"', false)
                    ->assertSee('Predictive analytics', false);
            } else {
                $response->assertDontSee('id="predictionTab"', false)
                    ->assertDontSee('class="predictive-analytics-root embedded-ml-dashboard"', false);
            }
        }
    }

    public function test_active_web_pagination_surfaces_use_the_shared_rows_per_page_contract(): void
    {
        $views = [
            'dashboard-centralized.blade.php',
            'projtracking.blade.php',
            'Oprojects.blade.php',
            'finance.blade.php',
            'Afinance.blade.php',
            'inventory.blade.php',
            'Oinventory.blade.php',
            'notifications.blade.php',
            'Anotifications.blade.php',
            'Onotifications.blade.php',
            'reports-centralized.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));

            $this->assertStringContainsString('pagination-wrapper', $contents, $view);
            $this->assertStringContainsString('Rows per page', $contents, $view);
            $this->assertStringNotContainsString('Rows Displayed:', $contents, $view);
            $this->assertMatchesRegularExpression('/<option value="10"[^>]*>10<\/option>/', $contents, $view);
            $this->assertMatchesRegularExpression('/<option value="25" selected>25<\/option>/', $contents, $view);
            $this->assertMatchesRegularExpression('/<option value="50"[^>]*>50<\/option>/', $contents, $view);
            $this->assertMatchesRegularExpression('/<option value="100"[^>]*>100<\/option>/', $contents, $view);
        }
    }

    private function user(string $role = 'admin', ?string $email = null): User
    {
        return User::query()->create([
            'name' => ucfirst($role).' User',
            'email' => $email ?? $role.'-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'Active',
        ]);
    }

    private function project(array $overrides = []): int
    {
        return DB::table('project_tbl')->insertGetId(array_merge([
            'project_name' => 'Project '.uniqid(),
            'client_name' => 'Client',
            'project_manager' => 'Manager',
            'start_date' => '2026-01-01',
            'estimated_end_date' => '2026-12-31',
            'actual_end_date' => null,
            'worker_count' => 10,
            'phase' => 'Planning',
            'completion_percentage' => 0,
            'status' => 'Pending',
        ], $overrides));
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->string('status')->nullable();
            $table->boolean('first_login_verification_required')->default(false);
            $table->string('first_login_otp')->nullable();
            $table->timestamp('first_login_otp_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('project_tbl', function (Blueprint $table) {
            $table->increments('project_id');
            $table->string('project_name');
            $table->string('client_name');
            $table->string('project_manager');
            $table->date('start_date');
            $table->date('estimated_end_date');
            $table->date('actual_end_date')->nullable();
            $table->integer('worker_count')->default(0);
            $table->string('phase');
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->string('status');
        });
        Schema::create('budgets_tbl', function (Blueprint $table) {
            $table->increments('budget_id');
            $table->unsignedInteger('project_id');
            $table->decimal('budget_amount', 14, 2);
            $table->decimal('actual_amount', 14, 2)->default(0);
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
        });
        Schema::create('fin_expense_category_tbl', function (Blueprint $table) {
            $table->increments('fin_category_id');
            $table->string('category_code');
            $table->string('category_name');
            $table->string('classification');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('unit_tbl', function (Blueprint $table) {
            $table->increments('unit_id');
            $table->string('unit_name');
        });
        Schema::create('inventory_transaction_tbl', function (Blueprint $table) {
            $table->increments('inventory_transaction_id');
        });
        Schema::create('fin_expense_tbl', function (Blueprint $table) {
            $table->increments('fin_expense_id');
            $table->unsignedInteger('project_id')->nullable();
            $table->unsignedInteger('fin_category_id');
            $table->unsignedInteger('inventory_transaction_id')->nullable();
            $table->string('project_cost_component', 20)->nullable();
            $table->string('expense_description');
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->text('remarks')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_file_name')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications_tbl', function (Blueprint $table) {
            $table->id('notification_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->string('kind');
            $table->string('filter');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }
}
