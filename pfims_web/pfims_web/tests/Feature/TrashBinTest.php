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

    private function deletedSupplier(int $id): int {
        DB::table('supplier_tbl')->insert(['supplier_id'=>$id, 'supplier_name'=>'Recoverable '.$id]);
        return $this->deleteJson('/api/suppliers/'.$id)->assertOk()->json('trash_id');
    }

    public function test_restore_all_restores_selected_records_and_excludes_new_arrivals(): void {
        $this->actingAs($this->user());
        $ids=[$this->deletedSupplier(7), $this->deletedSupplier(8)];
        $new=$this->deletedSupplier(9);
        $this->postJson('/api/trash/restore-all',['confirmed'=>true,'ids'=>$ids])->assertOk()->assertJsonPath('restored_count',2);
        $this->assertDatabaseHas('supplier_tbl',['supplier_id'=>7]);
        $this->assertDatabaseHas('supplier_tbl',['supplier_id'=>8]);
        $this->assertDatabaseMissing('supplier_tbl',['supplier_id'=>9]);
        $this->assertDatabaseHas('trash_entries',['id'=>$new]);
    }

    public function test_restore_all_rolls_back_every_restore_when_one_record_conflicts(): void {
        $this->actingAs($this->user());
        $ids=[$this->deletedSupplier(7), $this->deletedSupplier(8)];
        DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'Replacement']);
        $this->postJson('/api/trash/restore-all',['confirmed'=>true,'ids'=>$ids])->assertUnprocessable();
        $this->assertDatabaseHas('supplier_tbl',['supplier_id'=>7,'supplier_name'=>'Replacement']);
        $this->assertDatabaseMissing('supplier_tbl',['supplier_id'=>8]);
        $this->assertDatabaseCount('trash_entries',2);
    }

    public function test_restore_all_reverses_deletion_order_to_restore_dependencies(): void {
        $user=$this->user(); $this->actingAs($user);
        $make=function($table,$key,$row) use($user) {
            return DB::table('trash_entries')->insertGetId(['deleted_by'=>$user->id,'label'=>'Dependency fixture','deleted_at'=>now(),'expires_at'=>now()->addDays(30),'payload'=>\Illuminate\Support\Facades\Crypt::encrypt(['role'=>'admin','tables'=>[$table=>[['key'=>$key,'id'=>$row[$key],'before'=>$row,'after'=>null]]]])]);
        };
        $child=$make('inventory_item_tbl','item_id',['item_id'=>1,'supplier_id'=>7,'current_stock'=>0]);
        $parent=$make('supplier_tbl','supplier_id',['supplier_id'=>7,'supplier_name'=>'Parent']);
        $this->postJson('/api/trash/restore-all',['confirmed'=>true,'ids'=>[$child,$parent]])->assertOk();
        $this->assertDatabaseHas('supplier_tbl',['supplier_id'=>7]);
        $this->assertDatabaseHas('inventory_item_tbl',['item_id'=>1,'supplier_id'=>7]);
        $this->assertDatabaseCount('trash_entries',0);
    }

    public function test_bulk_actions_require_confirmation_and_cannot_touch_another_users_trash(): void {
        $owner=$this->user('operations'); $this->actingAs($owner); $first=$this->deletedSupplier(7);
        $other=$this->user('operations'); $this->actingAs($other); $second=$this->deletedSupplier(8);
        foreach ([['POST','/api/trash/restore-all'],['DELETE','/api/trash']] as [$method,$url]) {
            $this->json($method,$url,['ids'=>[$second]])->assertUnprocessable();
            $this->json($method,$url,['confirmed'=>false,'ids'=>[$second]])->assertUnprocessable();
            $this->json($method,$url,['confirmed'=>true,'ids'=>[$first,$second]])->assertNotFound();
        }
        $this->assertDatabaseCount('trash_entries',2);
        $this->assertDatabaseCount('supplier_tbl',0);
        $this->deleteJson('/api/trash',['confirmed'=>true,'ids'=>[$second]])->assertOk()->assertJsonPath('deleted_count',1);
        $this->assertDatabaseHas('trash_entries',['id'=>$first]);
    }

    public function test_empty_trash_is_permanent_and_only_deletes_the_confirmed_selection(): void {
        $this->actingAs($this->user());
        $first=$this->deletedSupplier(7); $second=$this->deletedSupplier(8);
        $this->deleteJson('/api/trash',['confirmed'=>true,'ids'=>[$first]])->assertOk()->assertJsonPath('deleted_count',1);
        $this->postJson('/api/trash/'.$first.'/restore')->assertNotFound();
        $this->assertDatabaseMissing('supplier_tbl',['supplier_id'=>7]);
        $this->assertDatabaseHas('trash_entries',['id'=>$second]);
    }

    public function test_empty_trash_preserves_shared_attachments_until_no_record_needs_them(): void {
        Storage::fake('protected'); Storage::disk('protected')->put('proofs/shared.pdf','proof');
        Schema::table('supplier_tbl',fn(Blueprint $table)=>$table->string('proof_file_path')->nullable());
        $owner=$this->user('operations'); $this->actingAs($owner);
        DB::table('supplier_tbl')->insert(['supplier_id'=>7,'supplier_name'=>'First','proof_file_path'=>'proofs/shared.pdf']);
        $first=$this->deleteJson('/api/suppliers/7')->assertOk()->json('trash_id');
        $other=$this->user('operations'); $this->actingAs($other);
        DB::table('supplier_tbl')->insert(['supplier_id'=>8,'supplier_name'=>'Second','proof_file_path'=>'proofs/shared.pdf']);
        $second=$this->deleteJson('/api/suppliers/8')->assertOk()->json('trash_id');
        $this->actingAs($owner)->deleteJson('/api/trash',['confirmed'=>true,'ids'=>[$first]])->assertOk();
        Storage::disk('protected')->assertExists('proofs/shared.pdf');
        $this->actingAs($other)->deleteJson('/api/trash',['confirmed'=>true,'ids'=>[$second]])->assertOk();
        Storage::disk('protected')->assertMissing('proofs/shared.pdf');
    }

    public function test_bulk_restore_cannot_restore_expired_records_or_make_partial_changes(): void {
        $this->actingAs($this->user());
        $expired=$this->deletedSupplier(7); $fresh=$this->deletedSupplier(8);
        DB::table('trash_entries')->where('id',$expired)->update(['expires_at'=>now()->subSecond()]);
        $this->postJson('/api/trash/restore-all',['confirmed'=>true,'ids'=>[$expired,$fresh]])->assertStatus(410);
        $this->assertDatabaseCount('supplier_tbl',0);
        $this->assertDatabaseCount('trash_entries',2);
    }

    public function test_role_changes_do_not_grant_bulk_access_to_previous_role_records(): void {
        $user=$this->user('operations'); $this->actingAs($user); $id=$this->deletedSupplier(7);
        $user->update(['role'=>'accounting']); $this->actingAs($user->fresh());
        $this->postJson('/api/trash/restore-all',['confirmed'=>true,'ids'=>[$id]])->assertForbidden();
        $this->deleteJson('/api/trash',['confirmed'=>true,'ids'=>[$id]])->assertForbidden();
        $this->assertDatabaseHas('trash_entries',['id'=>$id]);
        $this->assertDatabaseCount('supplier_tbl',0);
    }
}
