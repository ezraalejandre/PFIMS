<?php

namespace Tests\Feature;

use App\Mail\FirstLoginVerificationMail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FirstLoginVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('first_login_otp')->nullable();
            $table->timestamp('first_login_otp_expires_at')->nullable();
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
            $table->string('ip_address', 45)->nullable();
            $table->string('location')->nullable();
            $table->string('device', 100);
            $table->string('browser', 100);
            $table->text('user_agent')->nullable();
            $table->timestamp('logged_in_at');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notifications_tbl');
        Schema::dropIfExists('login_histories');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_created_accounts_can_complete_first_login_with_the_email_prefix_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'Active']);
        foreach (['admin' => '/dashboard', 'operations' => '/odashboard', 'accounting' => '/adashboard'] as $role => $dashboard) {
            $this->travel(61)->seconds();
            $prefix = $role === 'admin' ? 'ab' : $role.'.new+staff';
            $created = $this->actingAs($admin)->postJson('/users', [
                'name' => 'New Staff', 'email' => '  '.strtoupper($prefix).'@Example.com  ', 'role' => $role,
            ])->assertOk()->assertJsonPath('password', $prefix);
            $user = User::findOrFail($created->json('user.id'));
            $this->assertTrue(Hash::check($prefix, $user->password));
            $this->assertTrue($user->first_login_verification_required);
            $this->post('/logout');
            $this->postJson('/login', ['email' => '  '.strtoupper($user->email).'  ', 'password' => $prefix])
                ->assertOk()->assertJsonPath('requires_first_login_verification', true);
            $this->assertGuest();
            $code = Mail::sent(FirstLoginVerificationMail::class)->last()->otp;
            $this->postJson('/login/verify-first-login', ['otp' => $code])
                ->assertOk()->assertJsonPath('redirect', url($dashboard));
            $this->assertAuthenticatedAs($user);
            $this->assertFalse($user->fresh()->first_login_verification_required);
            $this->post('/logout');
            $this->postJson('/login', ['email' => $user->email, 'password' => $prefix])
                ->assertOk()->assertJsonMissingPath('requires_first_login_verification')
                ->assertJsonPath('redirect', url($dashboard));
            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    public function test_api_login_rejects_unknown_accounts_and_requires_first_login_verification(): void
    {
        $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'missing'])->assertUnauthorized();
        $this->assertGuest();
        $user = User::factory()->create(['email' => 'new@example.com', 'password' => Hash::make('new'),
            'status' => 'Active', 'first_login_verification_required' => true]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'new'])->assertForbidden();
        $this->assertGuest();
        $user->forceFill(['first_login_verification_required' => false])->save();
        $this->postJson('/api/login', ['email' => ' NEW@EXAMPLE.COM ', 'password' => 'new'])
            ->assertOk()->assertJsonPath('success', true)->assertSessionHas('account_session_token');
        $this->assertAuthenticatedAs($user);
        $this->getJson('/api/session/status')->assertOk();
    }

    public function test_first_login_verification_email_template_renders(): void
    {
        $html = (new FirstLoginVerificationMail('123456', 'Test User'))->render();

        $this->assertStringContainsString('Test User', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('complete your first login', $html);
    }

    public function test_first_login_sends_a_code_and_does_not_authenticate_until_it_is_verified(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'newuser@gmail.com',
            'password' => Hash::make('newuser'),
            'role' => 'operations',
            'status' => 'Active',
            'first_login_verification_required' => true,
        ]);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'newuser',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_first_login_verification', true)
            ->assertJsonPath('masked_email', 'ne***@gmail.com')
            ->assertJsonMissingPath('email')
            ->assertSessionHas('first_login_user_id', $user->id);
        $this->assertGuest();

        $code = null;
        Mail::assertSent(FirstLoginVerificationMail::class, function ($mail) use ($user, &$code) {
            $code = $mail->otp;

            return $mail->hasTo($user->email) && preg_match('/^\d{6}$/', $code) === 1;
        });

        $this->postJson('/login/resend-first-login')
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->travel(61)->seconds();
        $this->postJson('/login/resend-first-login')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('retry_after', 60);
        Mail::assertSent(FirstLoginVerificationMail::class, 2);

        $code = Mail::sent(FirstLoginVerificationMail::class)->last()->otp;

        $this->postJson('/login/verify-first-login', ['otp' => $code])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect', url('/odashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->fresh()->first_login_verification_required);
        $this->assertNull($user->fresh()->first_login_otp);
        $this->assertDatabaseHas('login_histories', ['user_id' => $user->id]);
    }

    public function test_invalid_code_does_not_authenticate_the_user(): void
    {
        $user = User::factory()->create([
            'status' => 'Active',
            'first_login_verification_required' => true,
            'first_login_otp' => Hash::make('123456'),
            'first_login_otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->withSession(['first_login_user_id' => $user->id])
            ->postJson('/login/verify-first-login', ['otp' => '654321'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertGuest();
        $this->assertTrue($user->fresh()->first_login_verification_required);
    }

    public function test_verified_existing_user_signs_in_without_an_email_challenge(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'verified@gmail.com',
            'password' => Hash::make('verified'),
            'role' => 'accounting',
            'status' => 'Active',
            'first_login_verification_required' => false,
        ]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'verified',
        ])->assertOk()
            ->assertJsonMissing(['requires_first_login_verification' => true])
            ->assertJsonPath('redirect', url('/adashboard'));

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->get('/asettings')
            ->assertOk()
            ->assertSee('Login History')
            ->assertSee('127.0.0.1')
            ->assertSee(now()->timezone('Asia/Manila')->format('g:i A').' PHT');
    }

    public function test_public_login_ip_is_resolved_to_a_location(): void
    {
        Http::fake([
            'https://ipwho.is/*' => Http::response([
                'success' => true,
                'city' => 'Quezon City',
                'region' => 'Metro Manila',
                'country' => 'Philippines',
            ]),
        ]);

        $user = User::factory()->create([
            'email' => 'location@gmail.com',
            'password' => Hash::make('location-pass'),
            'role' => 'admin',
            'status' => 'Active',
            'first_login_verification_required' => false,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'location-pass',
            ])->assertOk();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'ip_address' => '8.8.8.8',
            'location' => 'Quezon City, Metro Manila, Philippines',
        ]);
    }
}
