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

    public function test_admin_and_operations_project_views_render_working_filter_and_kpi_hooks_without_module_chart(): void
    {
        $adminView = $this->actingAs($this->user('admin'))->get('/projects')->assertOk();
        $operationsView = $this->actingAs($this->user('operations'))->get('/oprojects')->assertOk();

        foreach ([$adminView, $operationsView] as $response) {
            $response->assertSee('id="projectStatusFilter"', false)
                ->assertSee('id="projectPhaseFilter"', false)
                ->assertSee('id="projectDateFrom"', false)
                ->assertSee('id="projectDateTo"', false)
                ->assertSee('id="activeProjectsCount"', false)
                ->assertDontSee('id="avgCompletion"', false)
                ->assertDontSee('id="projectStatusChart"', false)
                ->assertSee('function filterProjects()', false)
                ->assertSee('function refreshProjectAnalytics(projects)', false)
                ->assertSee('function renderStatusChart(projects)', false);
        }
        $operationsView
            ->assertSee('data-portal="operations"', false)
            ->assertSee('pfims-system-ui.js', false);

        $sharedUi = file_get_contents(public_path('js/pfims-system-ui.js'));
        $this->assertStringContainsString("'/dashboard': '/odashboard'", $sharedUi);
        $this->assertStringContainsString("'/inventory': '/oinventory'", $sharedUi);
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
                ->assertSee('<body class="dashboard-page" data-portal="'.$dashboard['role'].'">', false)
                ->assertSee('class="top-header"', false)
                ->assertSee('class="sidebar"', false)
                ->assertSee('class="bottom-nav"', false)
                ->assertSee('class="main-content"', false)
                ->assertSee('class="header-clock"', false)
                ->assertSee('id="dashboardPagination"', false)
                ->assertSee('id="pageSize"', false)
                ->assertSee('id="dashboardRange"', false)
                ->assertSee('id="dashboardPaginationLinks"', false)
                ->assertSee('class="panel chart-card budget-panel"', false)
                ->assertSee('id="projectDetailModal"', false)
                ->assertSee('class="dashboard-project-row"', false)
                ->assertSee('function openProjectDetail(project, trigger)', false)
                ->assertDontSee('data-project-action="view"', false)
                ->assertSee("['Enter', ' ']", false)
                ->assertSee('Rows per page', false)
                ->assertSee("timeZone: 'Asia/Manila'", false)
                ->assertSee('action="http://localhost/logout"', false);

            if ($dashboard['role'] === 'admin') {
                $response->assertSee('>Project Cost Prediction</a>', false)
                    ->assertDontSee('>Predictive analytics</a>', false);
            }

            if ($dashboard['role'] === 'operations') {
                $response->assertDontSee('>Project Cost Prediction</a>', false);
            }

            if (in_array($dashboard['role'], ['admin', 'accounting'], true)) {
                foreach (['Expenses', 'Budgets', 'Contracts', 'AR / AP', 'Cash Position', 'Equipment', 'Bonds', 'Budget-Spending Comparison'] as $financeTab) {
                    $response->assertSee('>'.$financeTab.'</a>', false);
                }
            }

            foreach ($dashboard['links'] as $link) {
                $response->assertSee($link, false);
            }

            $response->assertDontSee('id="overviewTab"', false)
                ->assertDontSee('id="predictionTab"', false)
                ->assertDontSee('class="predictive-analytics-root embedded-ml-dashboard"', false)
                ->assertSee('nav-parent-toggle', false);
        }
    }

    public function test_active_web_pagination_surfaces_use_the_shared_rows_per_page_contract(): void
    {
        $views = [
            'dashboard.blade.php',
            'projtracking.blade.php',
            'finance.blade.php',
            'inventory.blade.php',
            'notifications.blade.php',
            'reports.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));

            $this->assertStringContainsString('pagination-wrapper', $contents, $view);
            $this->assertStringContainsString('Rows per page', $contents, $view);
            $this->assertStringNotContainsString('Rows Displayed:', $contents, $view);
            $this->assertDoesNotMatchRegularExpression('/<option value="25" selected>25<\/option>/', $contents, $view);
            $this->assertMatchesRegularExpression('/<option value="50"[^>]*>50<\/option>/', $contents, $view);
            $this->assertMatchesRegularExpression('/<option value="100"[^>]*>100<\/option>/', $contents, $view);
        }
    }

    public function test_settings_routes_preserve_the_authenticated_users_portal(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/asettings')->assertRedirect('/settings');
        $this->get('/osettings')->assertRedirect('/settings');
        $this->get('/finance')->assertOk()->assertSee('href="http://localhost/settings"', false);
        $this->assertSame('admin', $admin->fresh()->role);

        $accounting = $this->user('accounting');
        $this->actingAs($accounting)->get('/settings')->assertRedirect('/asettings');

        $operations = $this->user('operations');
        $this->actingAs($operations)->get('/settings')->assertRedirect('/osettings');
    }

    public function test_reported_ui_regressions_use_shared_controls_and_removed_two_factor_ui(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('id="materialForecastPagination"', false)
            ->assertDontSee('id="project"', false)
            ->assertDontSee('id="start"', false)
            ->assertDontSee('id="end"', false)
            ->assertSee('data-dashboard-period="all"', false)
            ->assertSee('data-dashboard-period="monthly"', false)
            ->assertSee('data-dashboard-period="yearly"', false)
            ->assertSee("state.period !== 'all'", false)
            ->assertSee("timeZone: 'Asia/Manila'", false)
            ->assertSee('id="status"', false)
            ->assertSee('id="stockStatus"', false)
            ->assertDontSee('id="projectStatusChart"', false)
            ->assertDontSee('id="stockStatusChart"', false);

        $dashboardCss = file_get_contents(public_path('css/centralized-dashboard.css'));
        $this->assertStringContainsString('grid-template-columns: repeat(2, minmax(0, 1fr));', $dashboardCss);
        $this->assertMatchesRegularExpression('/\.budget-panel\s*\{\s*grid-column:\s*auto;/', $dashboardCss);

        $sharedUi = file_get_contents(public_path('js/pfims-system-ui.js'));
        $this->assertStringContainsString('window.showPfimsAlert = function', $sharedUi);
        $this->assertStringContainsString("window.showSuccess = function", $sharedUi);
        $this->assertStringContainsString("['Project Cost Prediction', '/ml-dashboard-test?section=predictive']", $sharedUi);
        $this->assertStringContainsString("entry[0] === 'Project Cost Prediction' && portal !== 'admin'", $sharedUi);
        $this->assertStringNotContainsString("['Predictive Analytics', '/ml-dashboard-test?section=predictive']", $sharedUi);
        $this->assertStringContainsString("localStorage.setItem('pfims-nav-' + module", $sharedUi);
        $this->assertStringContainsString("item.classList.toggle('has-active-child', !!activeChild)", $sharedUi);
        $this->assertStringContainsString("var shouldOpen = !!activeChild || savedState === 'open'", $sharedUi);
        $this->assertStringContainsString("selected ? 'open' : 'closed'", $sharedUi);
        $this->assertStringContainsString('function installExpandableActionButtons()', $sharedUi);
        $this->assertStringContainsString("control.dataset.iconLabel = type", $sharedUi);
        $this->assertStringContainsString("type === 'export'", $sharedUi);
        $this->assertStringContainsString("type === 'clear' && control.closest('.modal-overlay, dialog, [role=\"dialog\"]')", $sharedUi);
        $this->assertStringContainsString('function removeProjectFilterControls()', $sharedUi);
        $this->assertStringContainsString('function installTablePagination(table)', $sharedUi);
        $this->assertStringContainsString("['5', '25', '50', '100']", $sharedUi);
        $this->assertStringNotContainsString('var AUTO_REFRESH_MS = 15000', $sharedUi);
        $this->assertStringNotContainsString("document.dispatchEvent(new CustomEvent('pfims:autorefresh'))", $sharedUi);
        $this->assertStringContainsString('function installHeaderClock()', $sharedUi);
        $this->assertStringContainsString("timeZone: 'Asia/Manila'", $sharedUi);
        $this->assertStringContainsString(".format(now) + ' PST'", $sharedUi);
        $this->assertStringContainsString("time.className = 'header-clock-time'", $sharedUi);
        $this->assertStringContainsString("}).format(now) + ' ';", $sharedUi);
        $this->assertStringNotContainsString("}).format(now) + ' at ';", $sharedUi);
        $this->assertStringContainsString("host.classList.add('pfims-search-host')", $sharedUi);
        $this->assertStringContainsString("cell.offsetParent === null", $sharedUi);
        $this->assertStringNotContainsString("forEach(installCustomSelect)", $sharedUi);

        $sharedCss = file_get_contents(public_path('css/ui-refresh.css'));
        $this->assertStringContainsString('.finance-page .pfims-add-modal .modal-container', $sharedCss);
        $this->assertStringContainsString('.sidebar .nav-parent-toggle .nav-chevron', $sharedCss);
        $this->assertStringContainsString('.sidebar .nav-parent.has-active-child.is-open', $sharedCss);
        $this->assertStringContainsString('.sidebar .nav-parent.has-active-child:not(.is-open)', $sharedCss);
        $this->assertStringContainsString('.pfims-expand-action:hover .button-label', $sharedCss);
        $this->assertStringContainsString('max-width: 220px', $sharedCss);
        $this->assertStringContainsString('.pfims-filter-heading', $sharedCss);
        $this->assertStringContainsString('.pfims-search-suggestions[hidden]', $sharedCss);
        $this->assertStringContainsString('.pfims-search-suggestions button', $sharedCss);
        $this->assertStringContainsString('select option', $sharedCss);
        $this->assertStringContainsString('--pfims-desktop-scale: 75%;', $sharedCss);
        $this->assertStringContainsString('@media (min-width: 1025px)', $sharedCss);
        $this->assertStringContainsString('zoom: var(--pfims-desktop-scale);', $sharedCss);
        $this->assertStringNotContainsString('@media (max-width: 1100px)', $sharedCss);
        $this->assertStringContainsString('height: 34px !important', $sharedCss);
        $this->assertStringContainsString('.top-header .right .header-clock', $sharedCss);
        $this->assertStringContainsString('.top-header .right .header-clock-date', $sharedCss);
        $this->assertStringContainsString('.top-header .right .header-clock-time', $sharedCss);
        $this->assertStringContainsString('margin-left: 0.35rem;', $sharedCss);
        $this->assertStringContainsString('font-weight: 800 !important', $sharedCss);
        $this->assertStringContainsString('One KPI card contract with a top accent', $sharedCss);
        $this->assertStringNotContainsString('stat cards get a side accent', $sharedCss);
        $this->assertStringNotContainsString('Inventory card accent: side bar', $sharedCss);
        $this->assertStringContainsString('Dashboard pagination is the final visual and sizing standard everywhere', $sharedCss);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $sharedCss);
        $this->assertStringContainsString('Flat system surfaces', $sharedCss);
        $this->assertStringContainsString('box-shadow: none !important', $sharedCss);

        $settingsView = file_get_contents(resource_path('views/settings.blade.php'));
        $this->assertStringContainsString('js/pfims-system-ui.js', $settingsView);

        $this->get('/inventory?section=transactions')
            ->assertOk()
            ->assertDontSee('class="inventory-tabs"', false)
            ->assertSee("requestedSection === 'transactions'", false);

        $this->get('/ml-dashboard-test?section=material-projection')
            ->assertOk()
            ->assertSee('id="materialForecastPagination"', false)
            ->assertSee('id="materialForecastPageSize"', false)
            ->assertSee('function compactPaginationItems', false);

        $this->get('/finance')
            ->assertOk()
            ->assertDontSee('>Refresh</button>', false)
            ->assertSee("document.addEventListener('pfims:autorefresh'", false)
            ->assertSee('finance-review-flow.js', false)
            ->assertSeeInOrder([
                'id="inventoryExpenseModal" class="modal-overlay pfims-add-modal"',
                'id="addExpenseModal" class="modal-overlay pfims-add-modal"',
                'id="addBudgetModal" class="modal-overlay pfims-add-modal"',
                'id="addContractModal" class="modal-overlay pfims-add-modal"',
                'id="addReceivableModal" class="modal-overlay pfims-add-modal"',
                'id="addCashModal" class="modal-overlay pfims-add-modal"',
                'id="addRepairModal" class="modal-overlay pfims-add-modal"',
                'id="addBackhoeExpenseModal" class="modal-overlay pfims-add-modal"',
                'id="addBackhoeRentalModal" class="modal-overlay pfims-add-modal"',
                'id="addBondModal" class="modal-overlay pfims-add-modal"',
            ], false)
            ->assertDontSee('Report View', false)
            ->assertDontSee('id="reportDropdown"', false)
            ->assertSee('data-finance-tab=', false)
            ->assertSee('openContractViewModal(this)', false)
            ->assertSee('id="contractEditBtn"', false)
            ->assertSee('then(function() { return fetchBudgetData(); })', false);

        $financeAnalytics = file_get_contents(public_path('js/finance-analytics.js'));
        $this->assertStringContainsString("window.updateBudgetActualAmounts", $financeAnalytics);

        foreach (glob(resource_path('views/*.blade.php')) as $view) {
            $this->assertStringNotContainsString('>SUPPLIERS</a>', file_get_contents($view), basename($view));
        }

        foreach (['settings.blade.php'] as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));
            $this->assertStringNotContainsString('Two Factor Authentication', $contents, $view);
            $this->assertStringNotContainsString('twofaModal', $contents, $view);
            $this->assertStringContainsString('<li class="active">', $contents, $view);
        }
    }

    public function test_every_role_uses_shared_module_blades_and_one_role_stylesheet(): void
    {
        $moduleViews = [
            'dashboard.blade.php',
            'projtracking.blade.php',
            'finance.blade.php',
            'inventory.blade.php',
            'suppliers.blade.php',
            'reports.blade.php',
            'notifications.blade.php',
            'profile.blade.php',
            'settings.blade.php',
            'ml-dashboard-test.blade.php',
        ];

        foreach ($moduleViews as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString("asset('css/'.\$portal.'.css')", $contents, $view);
            $this->assertStringContainsString('data-portal="{{ $portal }}"', $contents, $view);
        }

        foreach (['admin', 'accounting', 'operations'] as $role) {
            $contents = file_get_contents(public_path('css/'.$role.'.css'));
            $this->assertStringContainsString("@import url('./ui-refresh.css');", $contents, $role);
            $this->assertStringContainsString('--pfims-role: '.$role, $contents, $role);
        }

        foreach ([
            'Adashboard.blade.php', 'Odashboard.blade.php', 'Afinance.blade.php',
            'Oprojects.blade.php', 'Oinventory.blade.php', 'Osuppliers.blade.php',
            'Areports.blade.php', 'Oreports.blade.php', 'Anotifications.blade.php',
            'Onotifications.blade.php', 'Aprofile.blade.php', 'Oprofile.blade.php',
            'Asettings.blade.php', 'Osettings.blade.php',
        ] as $obsoleteView) {
            $this->assertFileDoesNotExist(resource_path('views/'.$obsoleteView));
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
