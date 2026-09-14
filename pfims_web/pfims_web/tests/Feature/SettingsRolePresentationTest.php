<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SettingsRolePresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->string('status')->default('Active');
            $table->boolean('first_login_verification_required')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('notifications_tbl', function (Blueprint $table) {
            $table->id('notification_id');
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->string('kind')->default('info');
            $table->string('filter')->default('alerts');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notifications_tbl');
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_accounting_settings_uses_current_user_and_hides_user_management(): void
    {
        $user = User::factory()->create([
            'name' => 'Rodriguez Vanjo',
            'email' => 'accounting@example.com',
            'password' => Hash::make('password'),
            'role' => 'accounting',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($user)->get('/asettings');

        $response->assertOk()
            ->assertSee('Rodriguez Vanjo')
            ->assertSee('RV')
            ->assertSee('Accounting')
            ->assertDontSee('Elito V. Catapang')
            ->assertDontSee('Project Manager')
            ->assertDontSee('Configurations')
            ->assertDontSee('User Management');
    }

    public function test_admin_settings_still_exposes_user_management(): void
    {
        $user = User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
        ]);

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('System Administrator')
            ->assertSee('Configurations')
            ->assertSee('User Management');
    }

    public function test_admin_can_update_a_user_role_without_resubmitting_status(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-role-update@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
        ]);
        $target = User::factory()->create([
            'email' => 'target-role-update@example.com',
            'password' => Hash::make('password'),
            'role' => 'accounting',
            'status' => 'Inactive',
        ]);

        $this->actingAs($admin)
            ->patchJson('/users/'.$target->id, ['role' => 'operations'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'operations')
            ->assertJsonPath('user.status', 'Inactive');

        $this->assertSame('operations', $target->fresh()->role);
        $this->assertSame('Inactive', $target->fresh()->status);
    }

    public function test_admin_can_create_a_user_without_a_status_field(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-create-user@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)
            ->postJson('/users', [
                'name' => 'Accounting User',
                'email' => 'new-accounting@example.com',
                'role' => 'accounting',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'accounting')
            ->assertJsonPath('user.status', 'Active');

        $created = User::where('email', 'new-accounting@example.com')->firstOrFail();
        $this->assertSame('Active', $created->status);
        $this->assertTrue((bool) $created->first_login_verification_required);
    }
}
