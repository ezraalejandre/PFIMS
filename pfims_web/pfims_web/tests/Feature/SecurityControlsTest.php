<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
class SecurityControlsTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp(); Schema::dropAllTables();
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->string('role');$t->string('status');$t->boolean('first_login_verification_required')->default(false);$t->rememberToken();$t->timestamps();});
    }
    private function user(string $role='admin',string $status='Active'): User {return User::create(['name'=>'Tester','email'=>uniqid().'@example.test','password'=>Hash::make('password123'),'role'=>$role,'status'=>$status]);}
    public function test_credential_arrays_and_oversized_passwords_are_rejected(): void {
        foreach(['/login','/api/login'] as $uri){
            $this->postJson($uri,['email'=>['invalid'],'password'=>['invalid']])->assertUnprocessable();
            $this->postJson($uri,['email'=>'valid@example.test','password'=>str_repeat('x',4097)])->assertUnprocessable();
        }
    }
    public function test_login_is_rate_limited(): void {
        for($i=0;$i<6;$i++)$this->postJson('/login',['email'=>'missing@example.test','password'=>'invalid'])->assertUnprocessable();
        $this->postJson('/login',['email'=>'missing@example.test','password'=>'invalid'])->assertTooManyRequests();
    }
    public function test_inactive_account_cannot_use_existing_session_or_legacy_login(): void {
        $user=$this->user('admin','Inactive'); $this->actingAs($user)->getJson('/api/projects')->assertUnauthorized();
        $this->assertGuest(); $this->postJson('/api/login',['email'=>$user->email,'password'=>'password123'])->assertUnauthorized();
    }
    public function test_all_operational_routes_reject_guests_before_querying_business_data(): void {
        $checked=0;
        foreach(Route::getRoutes() as $route){
            $uri=$route->uri();$middleware=$route->gatherMiddleware();
            if(!str_starts_with($uri,'api/')||!in_array('auth',$middleware,true))continue;
            $uri=preg_replace('/\{[^}]+\}/','1',$uri);
            $method=$route->methods()[0];
            $response=$this->json($method,'/'.$uri);
            $this->assertContains($response->status(),[401,404],$method.' /'.$uri);$checked++;
        }
        $this->assertGreaterThan(60,$checked);
    }
    public function test_operational_roles_cannot_access_admin_and_other_department_actions(): void {
        $this->actingAs($this->user('operations'));
        foreach(['/audit-logs','/api/config/units','/api/settings','/api/finance-expenses'] as $uri)$this->getJson($uri)->assertForbidden();
        $this->postJson('/users',[])->assertForbidden();
        $this->actingAs($this->user('accounting'))->postJson('/api/projects',[])->assertForbidden();
        $this->postJson('/api/inventory/item',[])->assertForbidden();
    }
    public function test_errors_are_generic_and_sensitive_pages_are_not_cached(): void {
        Route::middleware('web')->get('/security-test-error',fn()=>response()->json(['error'=>'SQLSTATE password private table'],500));
        $this->getJson('/security-test-error')->assertStatus(500)->assertDontSee('SQLSTATE')->assertDontSee('private table');
        Route::middleware('web')->get('/security-test-page',fn()=>response('Safe'));
        $this->actingAs($this->user())->get('/security-test-page')->assertHeader('X-Content-Type-Options','nosniff')->assertHeader('X-Frame-Options','SAMEORIGIN')->assertHeader('Cache-Control','must-revalidate, no-cache, no-store, private');
    }
    public function test_executable_disguised_as_profile_photo_is_rejected(): void {
        $this->actingAs($this->user())->postJson('/api/profile/photo',['photo'=>UploadedFile::fake()->createWithContent('avatar.php','<?php echo 1;')])->assertUnprocessable();
    }

    public function test_recovery_codes_are_hashed_and_single_use(): void {
        Schema::create('password_otps',function(Blueprint $t){$t->id();$t->string('email');$t->string('otp');$t->boolean('verified')->default(false);$t->timestamp('expires_at');$t->timestamps();});
        \Illuminate\Support\Facades\Notification::fake();$user=$this->user();
        $this->postJson('/api/forgot-password/send-otp',['email'=>$user->email])->assertOk();
        $notice=\Illuminate\Support\Facades\Notification::sent($user,\App\Notifications\ForgotPasswordOtpNotification::class)->first();
        $record=DB::table('password_otps')->first();$this->assertNotSame($notice->otp,$record->otp);$this->assertTrue(Hash::check($notice->otp,$record->otp));
        $this->postJson('/api/forgot-password/verify-otp',['email'=>$user->email,'otp'=>$notice->otp])->assertOk();
        $this->postJson('/api/forgot-password/reset',['email'=>$user->email,'otp'=>$notice->otp,'password'=>'new-password123','password_confirmation'=>'new-password123'])->assertOk();
        $this->postJson('/api/forgot-password/reset',['email'=>$user->email,'otp'=>$notice->otp,'password'=>'another-pass','password_confirmation'=>'another-pass'])->assertUnprocessable();
    }
    public function test_proofs_are_private_and_role_scoped_including_after_deletion(): void {
        \Illuminate\Support\Facades\Storage::fake('protected');
        Schema::create('budgets_tbl',function(Blueprint $t){$t->id('budget_id');$t->string('proof_file_path');});
        DB::table('budgets_tbl')->insert(['proof_file_path'=>'proofs/test.pdf']);\Illuminate\Support\Facades\Storage::disk('protected')->put('proofs/test.pdf','evidence');
        $this->get('/storage/proofs/test.pdf')->assertRedirect('/');
        $this->actingAs($this->user('operations'))->get('/storage/proofs/test.pdf')->assertNotFound();
        $this->actingAs($this->user('accounting'))->get('/storage/proofs/test.pdf')->assertOk();
        DB::table('budgets_tbl')->delete();$this->get('/storage/proofs/test.pdf')->assertNotFound();
    }

    public function test_missing_csrf_token_cannot_mutate_a_web_route(): void {
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,function($app){return new class($app,$app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken { protected function runningUnitTests(){return false;} };});
        Route::middleware('web')->post('/csrf-test-write',fn()=>response()->json(['changed'=>true]));
        $this->postJson('/csrf-test-write',['value'=>'safe'])->assertStatus(419);
    }

    public function test_unsafe_input_is_rejected_before_any_write(): void {
        $this->actingAs($this->user());
        $this->postJson('/api/profile/update',['field'=>'name','value'=>'<script>alert(1)</script>'])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->assertDatabaseMissing('users',['name'=>'<script>alert(1)</script>']);
        $this->getJson('/api/projects?search='.urlencode("x' OR 1=1 --"))->assertUnprocessable();
        $this->postJson('/api/profile/update',['field'=>'name','value'=>"O'Connor & Sons"])->assertOk();
        $this->assertDatabaseHas('users',['name'=>"O'Connor & Sons"]);
    }
}
