<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\AccountSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
class AccountSessionTest extends FirstLoginVerificationTest {
    public function test_a_second_browser_cannot_sign_in_until_the_first_signs_out(): void {
        $user=User::factory()->create(['email'=>'single@example.test','password'=>Hash::make('password123'),'role'=>'admin','status'=>'Active','first_login_verification_required'=>false]);
        $credentials=['email'=>$user->email,'password'=>'password123'];
        $this->postJson('/login',$credentials)->assertOk();
        $firstToken=session('account_session_token');$this->assertNotEmpty($firstToken);
        $this->getJson('/api/session/status')->assertOk();
        Auth::forgetGuards();$this->flushSession();
        $this->postJson('/login',$credentials)->assertStatus(409);$this->assertGuest();
        $this->assertDatabaseCount('account_sessions',1);
        Auth::guard()->setUser($user);$this->withSession(['account_session_token'=>$firstToken]);
        $this->post('/logout')->assertRedirect('/');$this->assertDatabaseCount('account_sessions',0);
        $this->postJson('/login',$credentials)->assertOk();
        $this->assertNotSame($firstToken,session('account_session_token'));
    }
    public function test_stale_token_and_changed_password_cannot_access_data(): void {
        $user=User::factory()->create(['role'=>'admin','status'=>'Active']);$this->actingAs($user);
        $this->withSession(['account_session_token'=>'wrong']);$this->getJson('/api/session/status')->assertUnauthorized();
        $this->assertGuest();
        $this->actingAs($user);$user->password=Hash::make('changed-password');$user->save();Auth::guard()->setUser($user->fresh());
        $this->getJson('/api/session/status')->assertUnauthorized();
    }
    public function test_session_lease_expires_and_can_be_reclaimed(): void {
        $user=User::factory()->create(['role'=>'admin','status'=>'Active']);$this->actingAs($user);
        $this->travel(config('session.account_lease_seconds')+1)->seconds();
        $this->getJson('/api/session/status')->assertUnauthorized();$this->actingAs($user);$this->getJson('/api/session/status')->assertOk();
    }
}
