<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    private const PHASES = ['Planning', 'Foundation', 'Structure', 'Finishing', 'Complete'];

    private const STATUSES = ['Pending', 'On Track', 'At Risk', 'Delayed', 'Completed'];

    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'phase' => ['nullable', 'in:'.implode(',', self::PHASES)],
            'start_date' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'],
        ]);

        $latestBudgets = DB::table('budgets_tbl')
            ->selectRaw('project_id, MAX(budget_id) AS budget_id')
            ->groupBy('project_id');

        $query = DB::table('project_tbl as p')
            ->leftJoinSub($latestBudgets, 'latest_budget', fn ($join) => $join->on('p.project_id', '=', 'latest_budget.project_id'))
            ->leftJoin('budgets_tbl as b', 'b.budget_id', '=', 'latest_budget.budget_id')
            ->select(
                'p.project_id', 'p.project_name', 'p.client_name',
                DB::raw('COALESCE(b.budget_amount, 0) as budget'),
                'p.project_manager', 'p.start_date', 'p.estimated_end_date', 'p.actual_end_date',
                'p.worker_count', 'p.phase', 'p.completion_percentage', 'p.status'
            );

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($inner) use ($search) {
                $inner->where('p.project_name', 'like', "%{$search}%")
                    ->orWhere('p.client_name', 'like', "%{$search}%")
                    ->orWhere('p.project_manager', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }
        if (! empty($filters['phase'])) {
            $query->where('p.phase', $filters['phase']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('p.start_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('p.start_date', '<=', $filters['end_date']);
        }

        return response()->json($query->orderByDesc('p.start_date')->orderByDesc('p.project_id')->get());
    }

    public function list(): JsonResponse
    {
        return response()->json(
            DB::table('project_tbl')
                ->select('project_id', 'project_name', 'client_name', 'project_manager', 'start_date',
                    'estimated_end_date', 'actual_end_date', 'worker_count', 'phase',
                    'completion_percentage', 'status')
                ->orderByDesc('project_id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateProject($request, false);
        $data['project_name'] = $this->normalizeLabel($data['project_name']);
        $data['client_name'] = $this->normalizeLabel($data['client_name']);
        $data['project_manager'] = $this->normalizeLabel($data['project_manager']);

        $projectId = DB::transaction(function () use ($data) {
            $this->assertUniqueNaturalKey($data['project_name'], $data['client_name'], $data['start_date']);

            $projectId = DB::table('project_tbl')->insertGetId([
                'project_name' => $data['project_name'],
                'client_name' => $data['client_name'],
                'project_manager' => $data['project_manager'],
                'start_date' => $data['start_date'],
                'estimated_end_date' => $data['estimated_end_date'],
                'actual_end_date' => $data['actual_end_date'] ?? null,
                'worker_count' => $data['worker_count'] ?? 0,
                'phase' => $data['phase'] ?? 'Planning',
                'completion_percentage' => $data['completion_percentage'] ?? 0,
                'status' => $data['status'] ?? 'Pending',
            ]);

            if (array_key_exists('budget', $data) && $data['budget'] !== null) {
                DB::table('budgets_tbl')->insert([
                    'project_id' => $projectId,
                    'budget_amount' => $data['budget'],
                    'actual_amount' => 0,
                ]);
            }

            return $projectId;
        });

        return response()->json($this->findPresented($projectId), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $existing = DB::table('project_tbl')->where('project_id', $id)->first();
        if (! $existing) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $data = $this->validateProject($request, true, $existing);
        foreach (['project_name', 'client_name', 'project_manager'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->normalizeLabel($data[$field]);
            }
        }

        $oldStatus = strtolower((string) $existing->status);
        DB::transaction(function () use ($data, $existing, $id) {
            $name = $data['project_name'] ?? $existing->project_name;
            $client = $data['client_name'] ?? $existing->client_name;
            $start = $data['start_date'] ?? $existing->start_date;
            $this->assertUniqueNaturalKey($name, $client, $start, $id);

            $projectData = array_intersect_key($data, array_flip([
                'project_name', 'client_name', 'project_manager', 'start_date', 'estimated_end_date',
                'actual_end_date', 'worker_count', 'phase', 'status', 'completion_percentage',
            ]));
            if ($projectData !== []) {
                DB::table('project_tbl')->where('project_id', $id)->update($projectData);
            }

            if (array_key_exists('budget', $data) && $data['budget'] !== null) {
                $budget = DB::table('budgets_tbl')->where('project_id', $id)->orderByDesc('budget_id')->first();
                if ($budget) {
                    DB::table('budgets_tbl')->where('budget_id', $budget->budget_id)
                        ->update(['budget_amount' => $data['budget']]);
                } else {
                    DB::table('budgets_tbl')->insert([
                        'project_id' => $id,
                        'budget_amount' => $data['budget'],
                        'actual_amount' => 0,
                    ]);
                }
            }
        });

        $project = $this->findPresented($id);
        $newStatus = strtolower((string) $project->status);
        if ($newStatus !== $oldStatus) {
            $this->notifyStatusChange($project, $newStatus);
        }

        return response()->json($project);
    }

    public function destroy(int $id): JsonResponse
    {
        if (! DB::table('project_tbl')->where('project_id', $id)->exists()) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $hasBudget = DB::table('budgets_tbl')->where('project_id', $id)->where('budget_amount', '>', 0)->exists();
        $hasFinanceExpenses = DB::table('fin_expense_tbl')
            ->where('project_id', $id)->where('amount', '>', 0)->exists();

        if ($hasBudget || $hasFinanceExpenses) {
            return response()->json([
                'message' => 'Project cannot be deleted while it has budget allocations or expense records. Remove those records first.',
            ], 409);
        }

        DB::transaction(function () use ($id) {
            DB::table('budgets_tbl')->where('project_id', $id)->delete();
            DB::table('project_tbl')->where('project_id', $id)->delete();
        });

        return response()->json(['message' => 'Project deleted']);
    }

    private function validateProject(Request $request, bool $updating, ?object $existing = null): array
    {
        $required = $updating ? ['sometimes', 'required'] : ['required'];
        $optionalOnCreate = $updating ? ['sometimes', 'required'] : ['nullable'];
        $validator = Validator::make($request->all(), [
            'project_name' => [...$required, 'string', 'max:150'],
            'client_name' => [...$required, 'string', 'max:150'],
            'project_manager' => [...$required, 'string', 'max:150'],
            'start_date' => [...$required, 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'estimated_end_date' => [...$required, 'date', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'worker_count' => [...$optionalOnCreate, 'integer', 'min:0', 'max:100000'],
            'phase' => [...$optionalOnCreate, 'string', 'in:'.implode(',', self::PHASES)],
            'status' => [...$optionalOnCreate, 'string', 'in:'.implode(',', self::STATUSES)],
            'completion_percentage' => [...$optionalOnCreate, 'numeric', 'min:0', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        $validator->after(function ($validator) use ($request, $existing) {
            $start = $request->input('start_date', $existing?->start_date);
            $estimated = $request->input('estimated_end_date', $existing?->estimated_end_date);
            $actual = $request->input('actual_end_date', $existing?->actual_end_date);
            if ($start && $estimated && strtotime($estimated) < strtotime($start)) {
                $validator->errors()->add('estimated_end_date', 'The estimated end date must be on or after the start date.');
            }
            if ($start && $actual && strtotime($actual) < strtotime($start)) {
                $validator->errors()->add('actual_end_date', 'The actual end date must be on or after the start date.');
            }
        });

        return $validator->validate();
    }

    private function assertUniqueNaturalKey(string $name, string $client, string $startDate, ?int $ignoreId = null): void
    {
        $normalizedName = $this->normalizeKey($name);
        $normalizedClient = $this->normalizeKey($client);
        $candidates = DB::table('project_tbl')->whereDate('start_date', $startDate);
        if ($ignoreId !== null) {
            $candidates->where('project_id', '!=', $ignoreId);
        }

        $duplicate = $candidates->lockForUpdate()->get(['project_name', 'client_name'])->contains(
            fn ($project) => $this->normalizeKey((string) $project->project_name) === $normalizedName
                && $this->normalizeKey((string) $project->client_name) === $normalizedClient
        );

        if ($duplicate) {
            throw ValidationException::withMessages([
                'project_name' => ['A project with the same name, client, and start date already exists.'],
            ]);
        }
    }

    private function normalizeLabel(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower($this->normalizeLabel($value));
    }

    private function findPresented(int $id): object
    {
        $latestBudget = DB::table('budgets_tbl')->where('project_id', $id)->orderByDesc('budget_id')->first();
        $project = DB::table('project_tbl')->where('project_id', $id)->first();
        $project->budget = (float) ($latestBudget->budget_amount ?? 0);

        return $project;
    }

    private function notifyStatusChange(object $project, string $newStatus): void
    {
        $type = match ($newStatus) {
            'delayed' => ['project_delayed', 'Project Delayed', 'overdue'],
            'at risk' => ['project_at_risk', 'Project At Risk', 'warning'],
            default => null,
        };
        if (! $type || $this->notifications->alreadyNotified($type[0], 'project', (int) $project->project_id)) {
            return;
        }
        $this->notifications->notify(
            title: $type[1],
            message: "\"{$project->project_name}\" has been marked as {$newStatus}.",
            type: $type[0], kind: $type[2], filter: 'alerts',
            referenceType: 'project', referenceId: (int) $project->project_id,
        );
    }
}
