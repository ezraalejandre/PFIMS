<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\User;
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
            $table->string('role'); $table->string('status')->default('Active'); $table->rememberToken(); $table->timestamps();
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
            ->assertSee('data-filter-description=', false)
            ->assertSee('pagination-wrapper');
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
