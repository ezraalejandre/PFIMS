<?php
namespace App\Http\Controllers;
use App\Services\TrashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
class TrashController extends Controller
{
    public function index(Request $request, TrashService $trash) {
        $trash->purgeExpired();
        $query = DB::table('trash_entries')->select('id','label','deleted_at','expires_at')->where('expires_at','>',now());
        if (strtolower($request->user()->role) !== 'admin') $query->where('deleted_by',$request->user()->id);
        return response()->json(['data'=>$query->orderByDesc('id')->get()]);
    }
    public function restore(Request $request, int $id, TrashService $trash) {
        try { $trash->restore($id, $request->user()); }
        catch (QueryException $e) { return response()->json(['message'=>'Restore blocked by a missing dependency or duplicate record. Restore its related record first. No data was overwritten.'],409); }
        return response()->json(['message'=>'Restored successfully. The record is available in its original page.']);
    }

    public function restoreAll(Request $request, TrashService $trash) {
        $data = $this->bulkSelection($request);
        try { $count = $trash->restoreAll($data['ids'], $request->user()); }
        catch (QueryException $error) { return response()->json(['message'=>'Restore All was blocked by a missing dependency or duplicate record. No records were restored or overwritten.'], 409); }
        return response()->json(['message'=>'Restored '.$count.' Trash Bin records successfully.', 'restored_count'=>$count]);
    }

    public function empty(Request $request, TrashService $trash) {
        $data = $this->bulkSelection($request);
        $count = $trash->empty($data['ids'], $request->user());
        return response()->json(['message'=>'Permanently deleted '.$count.' Trash Bin records.', 'deleted_count'=>$count]);
    }

    private function bulkSelection(Request $request): array {
        return $request->validate(['confirmed'=>['required','accepted'], 'ids'=>['required','array','min:1','max:10000'], 'ids.*'=>['required','integer','min:1','distinct']]);
    }
}
