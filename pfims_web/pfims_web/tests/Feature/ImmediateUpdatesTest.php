<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
class ImmediateUpdatesTest extends TestCase {
    protected function setUp(): void {
        parent::setUp(); Schema::dropAllTables();
        Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->string('role');$t->string('status')->default('Active');$t->string('phone')->nullable();$t->string('location')->nullable();$t->rememberToken();$t->timestamps();});
    }
    private function user(string $role='admin'): User { return User::create(['name'=>'Local tester','email'=>uniqid().'@example.test','password'=>Hash::make('password123'),'role'=>$role]); }
    public function test_user_list_is_current_and_excludes_credentials(): void {
        $admin=$this->user();$other=$this->user('operations');$this->actingAs($admin);
        $this->getJson('/users')->assertOk()->assertJsonCount(2,'data')->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token');
        $other->update(['name'=>'Changed name']);
        $this->getJson('/users')->assertOk()->assertJsonFragment(['name'=>'Changed name']);
        $this->actingAs($other)->getJson('/users')->assertForbidden();
    }
    public function test_profile_returns_saved_fields_and_failed_save_preserves_values(): void {
        $user=$this->user('operations');$this->actingAs($user);
        $this->patchJson('/profile',['name'=>"O'Connor",'email'=>$user->email,'phone'=>'09123456789','location'=>'Local test location'])
            ->assertOk()->assertJsonPath('user.name',"O'Connor")->assertJsonPath('user.phone','09123456789')->assertJsonMissingPath('user.password');
        $this->assertSame("O'Connor",$user->fresh()->name);
        $this->patchJson('/profile',['name'=>'<script>invalid</script>','email'=>$user->email,'phone'=>'09123456789','location'=>'Local test location'])->assertUnprocessable();
        $this->assertSame("O'Connor",$user->fresh()->name);
    }
    public function test_protected_pages_have_a_csrf_token_before_the_session_gate(): void {
        $checked=0;
        foreach(glob(resource_path('views/*.blade.php')) as $file) {
            $view=file_get_contents($file);
            if(!str_contains($view,'protected-navigation.js'))continue;
            $head=substr($view,0,strpos($view,'protected-navigation.js'));
            $this->assertStringContainsString('<meta name="csrf-token" content="{{ csrf_token() }}">',$head,basename($file));
            $checked++;
        }
        $this->assertGreaterThan(8,$checked);
    }

}
