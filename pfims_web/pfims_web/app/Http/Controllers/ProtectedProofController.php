<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
class ProtectedProofController extends Controller {
    public function show(Request $request, string $folder, string $file) {
        abort_unless(in_array($folder,['proofs','inventory-transaction-proofs'],true) && preg_match('/\A[a-zA-Z0-9_.-]+\z/',$file) && !str_contains($file,'..'),404);
        $path=$folder.'/'.$file;$role=strtolower($request->user()->role);
        $tables=match($role){'admin'=>['budgets_tbl','fin_expense_tbl','inventory_transaction_tbl','expense_tbl'],'accounting'=>['budgets_tbl','fin_expense_tbl'],'operations'=>['inventory_transaction_tbl','expense_tbl'],default=>[]};
        $allowed=false; foreach($tables as $table) if(Schema::hasColumn($table,'proof_file_path') && DB::table($table)->where('proof_file_path',$path)->exists()){$allowed=true;break;}
        abort_unless($allowed && Storage::disk('protected')->exists($path),404);
        return response()->file(Storage::disk('protected')->path($path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-store, private']);
    }
}
