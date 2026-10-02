<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Report;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuditLogsAndDefaultFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['audit_logs', 'user_default_filters', 'supplier_tbl', 'users'] as $table) Schema::dropIfExists($table);
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('email')->unique(); $table->string('password');
            $table->string('role'); $table->string('status')->default('Active');
            $table->longText('profile_photo_data')->nullable(); $table->string('profile_photo_mime')->nullable();
            $table->rememberToken(); $table->timestamps();
        });
        Schema::create('supplier_tbl', function (Blueprint $table) {
            $table->id('supplier_id'); $table->string('supplier_name'); $table->string('address'); $table->string('contact_number');
        });
        Schema::create('user_default_filters', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id'); $table->string('module'); $table->json('filters'); $table->timestamps();
            $table->unique(['user_id', 'module']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable(); $table->string('user_name'); $table->string('user_email');
            $table->string('user_role'); $table->string('action_type'); $table->string('module'); $table->string('subject_type');
            $table->string('subject_table'); $table->string('subject_key'); $table->string('subject_id')->nullable();
            $table->string('record_label')->nullable(); $table->text('details'); $table->json('changes')->nullable();
            $table->string('view_url')->nullable(); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['audit_logs', 'user_default_filters', 'supplier_tbl', 'users'] as $table) Schema::dropIfExists($table);
        parent::tearDown();
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => ucfirst($role).' User', 'email' => $email, 'password' => Hash::make('password'), 'role' => $role, 'status' => 'Active']);
    }

    public function test_default_filters_are_private_to_each_user_and_can_be_removed(): void
    {
        $first = $this->user('operations', 'first@example.test');
        $second = $this->user('operations', 'second@example.test');

        $this->actingAs($first)->putJson('/api/default-filters/projects', [
            'filters' => ['projectStatusFilter' => 'Delayed', 'projectDateFrom' => '2026-01-01'],
        ])->assertOk()->assertJsonPath('filters.projectStatusFilter', 'Delayed');
        $this->actingAs($second)->getJson('/api/default-filters')->assertOk()->assertExactJson([]);
        $this->actingAs($first)->getJson('/api/default-filters')->assertJsonPath('projects.projectStatusFilter', 'Delayed');
        $this->actingAs($first)->deleteJson('/api/default-filters/projects')->assertOk();
        $this->actingAs($first)->getJson('/api/default-filters')->assertExactJson([]);
        $this->assertSame(0, AuditLog::count(), 'Saving or using filters must not create audit activity.');
    }

    public function test_account_changes_are_logged_without_password_or_photo_contents(): void
    {
        $admin = $this->user('admin', 'security@example.test');
        $this->actingAs($admin);

        $admin->update(['name' => 'Renamed Admin', 'role' => 'operations']);
        $admin->update(['password' => Hash::make('new-password')]);
        $admin->forceFill(['profile_photo_data' => base64_encode('private image'), 'profile_photo_mime' => 'image/png'])->save();

        $logs = AuditLog::orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame(['name', 'role'], array_keys($logs[0]->changes));
        $this->assertStringContainsString('changed password', $logs[1]->details);
        $this->assertNull($logs[1]->changes);
        $this->assertStringContainsString('changed profile photo', $logs[2]->details);
        $this->assertNull($logs[2]->changes);
        $this->assertStringNotContainsString('private image', $logs->toJson());
        $this->assertStringNotContainsString('new-password', $logs->toJson());
    }

    public function test_successful_imports_have_one_summary_entry(): void
    {
        $admin = $this->user('admin', 'importer@example.test');
        $this->actingAs($admin);

        app(AuditLogService::class)->recordOperation('IMPORT', 'Projects', 'project_tbl', 'Imported 12 Projects record(s)');

        $log = AuditLog::sole();
        $this->assertSame('IMPORT', $log->action_type);
        $this->assertSame('Imported 12 Projects record(s)', $log->details);
        $this->assertNull($log->view_url);
    }

    public function test_report_generation_and_password_reset_are_audited_without_secrets(): void
    {
        $admin = $this->user('admin', 'reports@example.test');
        $this->actingAs($admin);
        $report = new Report(['title' => 'Project Summary', 'generation_method' => 'system_export']);
        $report->report_id = 'RPT-TEST';
        app(AuditLogService::class)->record($report, 'CREATE', [], $report->getAttributes());

        $this->assertSame('EXPORT', AuditLog::firstOrFail()->action_type);

        auth()->logout();
        app(AuditLogService::class)->recordPasswordReset($admin);

        $reset = AuditLog::latest('id')->firstOrFail();
        $this->assertSame('UPDATE', $reset->action_type);
        $this->assertSame($admin->id, $reset->user_id);
        $this->assertStringContainsString('Password reset', $reset->details);
        $this->assertNull($reset->changes);
    }

    public function test_supplier_crud_is_audited_with_field_level_changes(): void
    {
        $admin = $this->user('admin', 'admin@example.test');
        $this->actingAs($admin);
        $supplier = Supplier::create(['supplier_name' => 'Batangas Builders Supply', 'address' => 'Batangas City', 'contact_number' => '09171234567']);
        $supplier->update(['address' => 'Lipa City']);
        $supplier->delete();

        $this->assertSame(['CREATE', 'UPDATE', 'DELETE'], AuditLog::orderBy('id')->pluck('action_type')->all());
        $update = AuditLog::where('action_type', 'UPDATE')->firstOrFail();
        $this->assertSame('Batangas City', $update->changes['address']['from']);
        $this->assertSame('Lipa City', $update->changes['address']['to']);
        $this->assertStringContainsString('changed Address', $update->details);
    }

    public function test_only_admin_can_open_the_audit_log_module(): void
    {
        $admin = $this->user('admin', 'admin2@example.test');
        $operations = $this->user('operations', 'ops@example.test');
        $this->actingAs($operations)->get('/audit-logs')->assertRedirect('/odashboard');
        $this->actingAs($admin)->get('/audit-logs')
            ->assertOk()
            ->assertSee('centralized-dashboard.css')
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('AUDIT LOGS')
            ->assertSee('Activity history')
            ->assertSee('Date &amp; Time', false)
            ->assertSee('panel filters filters-grid audit-log-filter-panel', false)
            ->assertSee('id="auditLogFilters"', false)
            ->assertSee('<th>Actions</th>', false)
            ->assertDontSee('Apply filters')
            ->assertSee("form.requestSubmit()", false)
            ->assertSee('data-pfims-page-size="ready"', false)
            ->assertSee('data-pfims-wait-for-ready="true"', false)
            ->assertSee('if (requested === current) return false;', false)
            ->assertSee('/audit-logs/latest', false)
            ->assertSee('pagination-wrapper');

        $view = file_get_contents(resource_path('views/audit-logs.blade.php'));
        $this->assertStringContainsString('class="pfims-row-action"', $view);
        $this->assertStringContainsString("asset('images/view.jpg')", $view);
    }

    public function test_latest_audit_log_endpoint_only_reports_database_freshness_to_admins(): void
    {
        $admin = $this->user('admin', 'fresh-admin@example.test');
        $operations = $this->user('operations', 'fresh-ops@example.test');

        $this->actingAs($operations)->getJson('/audit-logs/latest')->assertForbidden();
        $this->actingAs($admin)->getJson('/audit-logs/latest')
            ->assertOk()
            ->assertJsonStructure(['latest_id', 'latest_created_at']);
    }

    public function test_feature_tables_are_bootstrapped_when_git_deployment_has_not_run_migrations(): void
    {
        $admin = $this->user('admin', 'bootstrap@example.test');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_default_filters');

        $this->actingAs($admin)->get('/audit-logs')->assertOk();

        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasTable('user_default_filters'));
        $this->actingAs($admin)->getJson('/api/default-filters')->assertOk()->assertExactJson([]);
    }
}
