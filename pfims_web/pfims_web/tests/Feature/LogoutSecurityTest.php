<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class LogoutSecurityTest extends TestCase
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
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_logged_out_user_cannot_reopen_a_protected_page(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $protectedPage = $this->actingAs($user)->get('/aprofile');
        $protectedPage->assertOk();
        $this->assertStringContainsString('no-store', (string) $protectedPage->headers->get('Cache-Control'));

        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect('/');
        $this->assertSame('"cache"', $logoutResponse->headers->get('Clear-Site-Data'));
        $this->assertGuest();

        $this->get('/aprofile')->assertRedirect('/');
    }

    public function test_expired_logout_submission_redirects_to_login_instead_of_showing_419(): void
    {
        $this->withMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_expired_sign_in_page_explains_that_it_must_be_refreshed(): void
    {
        Route::post('/login', function () {
            throw new TokenMismatchException;
        });

        $message = 'This sign-in page has been open too long. Refresh the page, then try signing in again.';
        $this->postJson('/login')->assertStatus(419)->assertJsonPath('message', $message);
        $this->post('/login')->assertRedirect(route('login'))->assertSessionHasErrors(['email' => $message]);
        $this->get('/')->assertOk()->assertSee($message);
        $landing = file_get_contents(resource_path('views/landing.blade.php'));
        $this->assertStringContainsString('if (res.status === 419)', $landing);
        $this->assertStringContainsString($message, $landing);
    }
}
