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
            $table->rememberToken();
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
}
