<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureRoleAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('admin');
            $table->string('status')->default('Active');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_portal_pages_redirect_roles_to_their_own_dashboard(): void
    {
        $operations = $this->user('operations');
        $accounting = $this->user('accounting');

        $this->actingAs($operations)->get('/finance')->assertRedirect('/odashboard');
        $this->actingAs($accounting)->get('/projects')->assertRedirect('/adashboard');
        $this->actingAs($accounting)->get('/inventory')->assertRedirect('/adashboard');
    }

    public function test_finance_and_operations_api_boundaries_are_enforced(): void
    {
        $operations = $this->user('operations');
        $accounting = $this->user('accounting');

        $this->actingAs($operations)->getJson('/api/finance-expenses')->assertForbidden();
        $this->actingAs($accounting)->postJson('/api/projects', [])->assertForbidden();
        $this->actingAs($accounting)->postJson('/api/finance-expenses/from-inventory/1', [])->assertUnprocessable();
    }

    public function test_accounting_project_lookup_is_allowed_by_the_api_role_middleware(): void
    {
        $middleware = new EnsureRoleAccess();
        $request = Request::create('/api/projects', 'GET');
        $request->setUserResolver(fn () => $this->user('accounting'));

        $response = $middleware->handle($request, fn () => response()->json(['allowed' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_can_still_open_the_admin_finance_page(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get('/finance')
            ->assertOk()
            ->assertSee('data-portal="admin"', false);
    }

    public function test_legacy_profile_apis_require_authentication(): void
    {
        $this->postJson('/api/profile', ['email' => 'admin@example.test'])->assertUnauthorized();
        $this->postJson('/api/profile/update', [
            'email' => 'admin@example.test',
            'field' => 'name',
            'value' => 'Changed',
        ])->assertUnauthorized();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role),
            'email' => $role.'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'Active',
        ]);
    }
}
