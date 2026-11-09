<?php
namespace App\Http\Middleware;
use App\Services\TrashService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Group the archive deleted records actions.
class ArchiveDeletedRecords
{
    // Run the archive deleted records checks or action.
    public function handle(Request $request, Closure $next)
    {
        // Return early if the is method and is checks match.
        if ((!$request->isMethod('DELETE') && !$request->is('api/notifications/*/read')) || !$request->user() || $request->is('api/trash*')) return $next($request);
        abort_unless(Schema::hasTable('trash_entries'), 503, 'Trash storage is not ready. No records were deleted.');
        // Run these database steps together. Undo changes if a step fails.
        return DB::transaction(function () use ($request, $next) {
            $service = app(TrashService::class);
            $before = $service->snapshot();
            $response = $next($request);
            // Run this step if the get status code checks match.
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $id = $service->archive($before, $service->snapshot(), (int)$request->user()->id, 'Deleted records: '.$request->path());
                // Run this step if the id and response checks match.
                if ($id && $response instanceof \Illuminate\Http\JsonResponse) {
                    $data = $response->getData(true);
                    $data['message'] = 'Moved to Trash Bin. You can restore it from Settings for 30 days before permanent deletion.';
                    $data['trash_id'] = $id;
                    $response->setData($data);
                }
            }
            return $response;
        });
    }
}
