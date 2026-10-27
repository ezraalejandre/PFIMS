<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use App\Services\TrashService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class TrashBinTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp(); Schema::dropAllTables();
        Schema::create('users', function(Blueprint $t) { $t->id(); $t->string('name'); $t->string('email'); $t->string('password'); $t->string('role'); $t->string('status')->default('Active'); $t->rememberToken(); $t->timestamps(); });
        Schema::create('supplier_tbl', function(Blueprint $t) { $t->increments('supplier_id'); $t->string('supplier_name'); });
        Schema::create('inventory_item_tbl', function(Blueprint $t) { $t->increments('item_id'); $t->unsignedInteger('supplier_id')->nullable(); $t->foreign('supplier_id')->references('supplier_id')->on('supplier_tbl'); $t->integer('current_stock')->default(0); });
        (require database_path('migrations/2026_10_18_000001_create_trash_entries_table.php'))->up();
    }
    private function user(string $role='admin'): User { return User::create(['name'=>'Tester','email'=>uniqid().'@test.example','password'=>'password','role'=>$role,'status'=>'Active']); }
    public function test_delete_is_recoverable_with_same_id_for_30_days(): void {
        $this->actingAs($this->user()); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Recovery Supplier']);
        $id=$this->deleteJson('/api/suppliers/7')->assertOk()->assertJsonFragment(['message'=>'Moved to Trash Bin. You can restore it from Settings for 30 days before permanent deletion.'])->json('trash_id');
        $this->assertDatabaseMissing('supplier_tbl',['supplier_id'=>7]);
        $this->getJson('/api/trash')->assertOk()->assertJsonCount(1,'data')->assertJsonMissingPath('data.0.payload');
        $this->postJson('/api/trash/'.$id.'/restore')->assertOk();
        $this->assertDatabaseHas('supplier_tbl',['supplier_id'=>7,'supplier_name'=>'Recovery Supplier']);
        $this->assertDatabaseCount('trash_entries',0);
    }
    public function test_dependency_guards_still_block_deletion_and_do_not_create_trash(): void {
        $this->actingAs($this->user()); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Used']); DB::table('inventory_item_tbl')->insert(['supplier_id'=>7]);
        $this->deleteJson('/api/suppliers/7')->assertStatus(409); $this->assertDatabaseCount('trash_entries',0);
    }
    public function test_restore_cannot_overwrite_reused_id_and_is_owner_scoped(): void {
        $owner=$this->user('operations'); $this->actingAs($owner); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Original']);
        $id=$this->deleteJson('/api/suppliers/7')->assertOk()->json('trash_id');
        $this->actingAs($this->user('operations'))->getJson('/api/trash')->assertJsonCount(0,'data');
        $this->postJson('/api/trash/'.$id.'/restore')->assertNotFound();
        $this->actingAs($owner); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Replacement']);
        $this->postJson('/api/trash/'.$id.'/restore')->assertUnprocessable();
        $this->assertDatabaseHas('supplier_tbl',['supplier_name'=>'Replacement']);
    }
    public function test_expiry_boundary_and_command_purge(): void {
        $actor=$this->user();$this->actingAs($actor); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Expires']);
        $id=$this->deleteJson('/api/suppliers/7')->json('trash_id'); $this->travel(30)->days();$this->actingAs($actor);
        $this->postJson('/api/trash/'.$id.'/restore')->assertStatus(410);
        $this->artisan('trash:purge')->assertSuccessful(); $this->assertDatabaseCount('trash_entries',0);
    }
    public function test_file_retention_and_expiry_cleanup(): void {
        Storage::fake('protected'); Storage::disk('protected')->put('proofs/test.pdf','evidence');
        Schema::table('supplier_tbl',fn(Blueprint $t)=>$t->string('proof_file_path')->nullable());
        $this->actingAs($this->user()); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'File','proof_file_path'=>'proofs/test.pdf']);
        $this->deleteJson('/api/suppliers/7')->assertOk(); Storage::disk('protected')->assertExists('proofs/test.pdf');
        $this->travel(30)->days(); $this->artisan('trash:purge')->assertSuccessful(); Storage::disk('protected')->assertMissing('proofs/test.pdf');
    }
    public function test_deletion_rollback_if_archive_storage_fails(): void {
        $this->actingAs($this->user()); DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Must Survive']);
        $this->mock(TrashService::class, function($mock) { $mock->shouldReceive('snapshot')->twice()->andReturn([]); $mock->shouldReceive('archive')->andThrow(new \RuntimeException('Archive failed')); });
        $this->deleteJson('/api/suppliers/7')->assertStatus(500); $this->assertDatabaseHas('supplier_tbl',['supplier_id'=>7]);
    }
}
