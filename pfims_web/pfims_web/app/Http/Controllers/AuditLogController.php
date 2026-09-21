<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AuditFeatureSchema;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditFeatureSchema $schema) {}

    public function latest(Request $request)
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403);
        $this->schema->ensure();

        $latest = AuditLog::query()->latest('id')->first(['id', 'created_at']);

        return response()->json([
            'latest_id' => $latest?->id,
            'latest_created_at' => $latest?->created_at?->toISOString(),
        ]);
    }

    public function index(Request $request)
    {
        abort_unless(strtolower((string) $request->user()?->role) === 'admin', 403);
        $this->schema->ensure();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer'],
            'role' => ['nullable', 'in:ADMIN,ACCOUNTING,OPERATIONS'],
            'action' => ['nullable', 'in:CREATE,UPDATE,DELETE'],
            'module' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
        ]);

        $latestAuditId = (int) (AuditLog::query()->max('id') ?? 0);
        $query = AuditLog::query()->latest('created_at')->latest('id');
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $escaped = addcslashes($search, '%_\\');
            $query->where(fn ($q) => $q->where('details', 'like', "%{$escaped}%")
                ->orWhere('user_name', 'like', "%{$escaped}%")
                ->orWhere('user_email', 'like', "%{$escaped}%")
                ->orWhere('subject_id', 'like', "%{$escaped}%"));
        }
        if (! empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
        if (! empty($filters['user_id'])) $query->where('user_id', $filters['user_id']);
        if (! empty($filters['role'])) $query->where('user_role', $filters['role']);
        if (! empty($filters['action'])) $query->where('action_type', $filters['action']);
        if (! empty($filters['module'])) $query->where('module', $filters['module']);

        $logs = $query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
        $logs->getCollection()->transform(function (AuditLog $log) {
            $log->view_available = $log->view_url && $log->action_type !== 'DELETE'
                && DB::table($log->subject_table)->where($log->subject_key, $log->subject_id)->exists();
            return $log;
        });

        return view('audit-logs', [
            'portal' => 'admin', 'logs' => $logs, 'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'modules' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'latestAuditId' => $latestAuditId,
        ]);
    }
}
