<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class SessionLifecycleTest extends TestCase {
    protected bool $simulateInternalNavigation=false;
    protected function setUp(): void {
        parent::setUp();config(['session.strict_navigation'=>true]);Schema::dropAllTables();
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email');$t->string('password');$t->string('role');$t->string('status')->default('Active');$t->boolean('first_login_verification_required')->default(false);$t->rememberToken();$t->timestamps();});
        Schema::create('login_histories',function(Blueprint $t){$t->id();$t->unsignedBigInteger('user_id');$t->string('ip_address')->nullable();$t->string('location')->nullable();$t->string('device');$t->string('browser');$t->text('user_agent')->nullable();$t->timestamp('logged_in_at');});
        Route::middleware(['web','auth'])->get('/strict-protected',fn()=>response('Protected record'));
        Route::middleware(['web','auth'])->post('/strict-write',fn()=>redirect('/strict-protected'));
    }
    private function signInFixture(): User {
        $user=User::create(['name'=>'Test','email'=>'strict@example.test','password'=>Hash::make('password123'),'role'=>'admin','status'=>'Active']);
        $this->actingAs($user);$this->withSession(['navigation_epoch'=>'test-epoch']);return $user;
    }
    public function test_old_url_or_refresh_cannot_reopen_an_authenticated_page(): void {
        $this->signInFixture();$this->get('/strict-protected')->assertRedirect('/');$this->assertGuest();$this->assertDatabaseCount('account_sessions',0);
    }
    public function test_internal_navigation_is_one_use_and_replay_signs_out(): void {
        $this->signInFixture();$this->postJson('/session/navigate',['target'=>'/strict-protected'])->assertOk();
        $this->get('/strict-protected')->assertOk()->assertSee('Protected record');
        $this->get('/strict-protected')->assertRedirect('/');$this->assertGuest();
    }
    public function test_close_message_releases_the_account_and_stale_close_cannot_end_a_new_login(): void {
        $this->signInFixture();$this->post('/session/close',['epoch'=>'old-epoch'])->assertNoContent();$this->assertAuthenticated();
        $this->post('/session/close',['epoch'=>'test-epoch'])->assertNoContent();$this->assertGuest();$this->assertDatabaseCount('account_sessions',0);
        $this->get('/strict-protected')->assertRedirect('/');
    }
    public function test_expired_grants_and_external_targets_are_rejected(): void {
        $this->signInFixture();$this->postJson('/session/navigate',['target'=>'https://evil.example/strict-protected'])->assertUnprocessable();
        $this->postJson('/session/navigate',['target'=>'/strict-protected'])->assertOk();$this->travel(16)->seconds();
        $this->get('/strict-protected')->assertRedirect('/');$this->assertGuest();
    }
    public function test_successful_login_provides_only_the_initial_dashboard_grant(): void {
        User::create(['name'=>'Test','email'=>'strict@example.test','password'=>Hash::make('password123'),'role'=>'admin','status'=>'Active']);
        $this->postJson('/login',['email'=>'strict@example.test','password'=>'password123'])->assertOk()->assertSessionHas('navigation_grant.target','/dashboard')->assertSessionHas('navigation_epoch');
    }
    public function test_normal_form_redirect_stays_authenticated(): void {
        $this->signInFixture();$this->post('/strict-write')->assertRedirect('/strict-protected');$this->get('/strict-protected')->assertOk();$this->assertAuthenticated();
    }

    public function test_normal_refresh_resumes_but_closed_session_expires_after_five_seconds(): void {
        config(['session.strict_navigation'=>false]);$this->signInFixture();
        $this->postJson('/session/resume',['mode'=>'reload'])->assertOk();$nonce=$this->getSessionNonce();
        $this->post('/session/close',['epoch'=>'test-epoch','closing'=>true,'page_nonce'=>$nonce])->assertNoContent();
        $this->get('/strict-protected')->assertOk();
        $this->postJson('/session/resume',['mode'=>'reload'])->assertOk();$newNonce=$this->getSessionNonce();$this->assertNotSame($nonce,$newNonce);
        $this->post('/session/close',['epoch'=>'test-epoch','closing'=>true,'page_nonce'=>$nonce])->assertNoContent();
        $this->assertNull(DB::table('account_sessions')->first()->closing_at);
        $this->post('/session/close',['epoch'=>'test-epoch','closing'=>true,'page_nonce'=>$newNonce])->assertNoContent();
        $this->travel(6)->seconds();$this->getJson('/api/session/status')->assertUnauthorized();$this->assertGuest();
    }
    private function getSessionNonce(): string {return DB::table('account_sessions')->value('page_nonce');}
    public function test_reopened_page_without_navigation_permission_cannot_resume(): void {
        config(['session.strict_navigation'=>false]);$this->signInFixture();
        $this->get('/strict-protected')->assertOk();$this->postJson('/session/resume',['mode'=>'navigate'])->assertUnauthorized();
    }
}
