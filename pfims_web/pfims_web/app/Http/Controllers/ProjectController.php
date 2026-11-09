<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Budget;
use App\Services\BudgetHistoryService;
use App\Services\AutomaticModelRetraining;
use App\Services\NotificationService;
use App\Services\AuditLogService;
use App\Services\ProjectPhaseProgressService;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

// Group the project actions.
class ProjectController extends Controller
{
    private const STATUSES = ['Ongoing', 'Pending', 'At Risk', 'Delayed', 'Completed'];

    // Set up the values this class needs.
    public function __construct(
        private NotificationService $notifications,
        private AutomaticModelRetraining $modelRetraining,
        private AuditLogService $audit,
        private ProjectPhaseProgressService $phaseProgress,
    ) {}

    // List the project records.
    public function index(Request $request): JsonResponse
    {
        $this->flagProjectsNearDeadline();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'phase' => ['nullable', Rule::exists('project_phase_tbl', 'phase_name')],
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

        // Run this step if the filters search has a value.
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            // Group these checks when finding matching records.
            $query->where(function ($inner) use ($search) {
                $inner->where('p.project_name', 'like', "%{$search}%")
                    ->orWhere('p.client_name', 'like', "%{$search}%")
                    ->orWhere('p.project_manager', 'like', "%{$search}%");
            });
        }
        // Run this step if the filters status has a value.
        if (! empty($filters['status'])) {
            $query->where('p.status', $filters['status']);
        }
        // Run this step if the filters phase has a value.
        if (! empty($filters['phase'])) {
            $query->where('p.phase', $filters['phase']);
        }
        // Run this step if the filters start date has a value.
        if (! empty($filters['start_date'])) {
            $query->whereDate('p.start_date', '>=', $filters['start_date']);
        }
        // Run this step if the filters end date has a value.
        if (! empty($filters['end_date'])) {
            $query->whereDate('p.start_date', '<=', $filters['end_date']);
        }

        return response()->json($query->orderByDesc('p.start_date')->orderByDesc('p.project_id')->get());
    }

    // Get the list.
    public function list(): JsonResponse
    {
        $this->flagProjectsNearDeadline();
        return response()->json(
            DB::table('project_tbl')
                ->select('project_id', 'project_name', 'client_name', 'project_manager', 'start_date',
                    'estimated_end_date', 'actual_end_date', 'worker_count', 'phase',
                    'completion_percentage', 'status')
                ->orderByDesc('project_id')
                ->get()
        );
    }

    // Get the managers.
    public function managers(): JsonResponse
    {
        return response()->json(\App\Models\ProjectManager::orderBy('manager_name')->get(['manager_id', 'manager_name']));
    }

    // Get the phases.
    public function phases(): JsonResponse
    {
        return response()->json($this->phaseProgress->phases());
    }

    // Save a new project record.
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateProject($request, false);
        $data['project_name'] = $this->normalizeLabel($data['project_name']);
        $data['client_name'] = $this->normalizeLabel($data['client_name']);
        $data['project_manager'] = trim($data['project_manager']);

        // Run these database steps together. Undo changes if a step fails.
        $projectId = DB::transaction(function () use ($data) {
            $this->assertUniqueNaturalKey($data['project_name'], $data['client_name'], $data['start_date']);

            $phase = isset($data['phase'])
                ? $this->phaseProgress->canonicalPhaseName($data['phase'])
                : $this->phaseProgress->firstPhaseName();
            // Stop if the phase is missing or false.
            if (! $phase) {
                throw ValidationException::withMessages(['phase' => 'Configure at least one project phase before creating a project.']);
            }

            $projectId = DB::table('project_tbl')->insertGetId([
                'project_name' => $data['project_name'],
                'client_name' => $data['client_name'],
                'project_manager' => $data['project_manager'],
                'start_date' => $data['start_date'],
                'estimated_end_date' => $data['estimated_end_date'],
                'actual_end_date' => $data['actual_end_date'] ?? null,
                'worker_count' => $data['worker_count'] ?? 0,
                'phase' => $phase,
                'completion_percentage' => $this->phaseProgress->completionForPhase($phase),
                'status' => $data['status'] ?? 'Pending',
            ]);

            // Run this step if the data and data budget checks match.
            if (array_key_exists('budget', $data) && $data['budget'] !== null) {
                app(BudgetHistoryService::class)->create([
                    'project_id' => $projectId,
                    'budget_amount' => $data['budget'],
                    'actual_amount' => 0,
                ], $data['budget_revision_reason'] ?? 'Initial budget entered with a new project.', ($data['status'] ?? 'Pending') !== 'Completed');
            }

            return $projectId;
        });

        $this->modelRetraining->afterDataChange([(int) $projectId]);
        // Run this step if the project id check matches.
        if ($created = Project::find($projectId)) $this->audit->record($created, 'CREATE', [], $created->getAttributes());

        return response()->json($this->findPresented($projectId), 201);
    }

    // Save changes to the project record.
    public function update(Request $request, int $id): JsonResponse
    {
        $existing = DB::table('project_tbl')->where('project_id', $id)->first();
        // Return early if the existing is missing or false.
        if (! $existing) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $data = $this->validateProject($request, true, $existing);
        // Process each field.
        foreach (['project_name', 'client_name', 'project_manager'] as $field) {
            // Run this step if the data check matches.
            if (array_key_exists($field, $data)) {
                $data[$field] = $field === 'project_manager' ? trim($data[$field]) : $this->normalizeLabel($data[$field]);
            }
        }

        $oldStatus = strtolower((string) $existing->status);
        // Run these database steps together. Undo changes if a step fails.
        DB::transaction(function () use ($data, $existing, $id) {
            $name = $data['project_name'] ?? $existing->project_name;
            $client = $data['client_name'] ?? $existing->client_name;
            $start = $data['start_date'] ?? $existing->start_date;
            $this->assertUniqueNaturalKey($name, $client, $start, $id);

            // Run this step if the data phase is set.
            if (isset($data['phase'])) {
                $data['phase'] = $this->phaseProgress->canonicalPhaseName($data['phase']);
            }
            $phase = $data['phase'] ?? $existing->phase;
            $data['completion_percentage'] = $this->phaseProgress->completionForPhase((string) $phase);
            $projectData = array_intersect_key($data, array_flip([
                'project_name', 'client_name', 'project_manager', 'start_date', 'estimated_end_date',
                'actual_end_date', 'worker_count', 'phase', 'status', 'completion_percentage',
            ]));
            // Run this step if the project data is not empty.
            if ($projectData !== []) {
                DB::table('project_tbl')->where('project_id', $id)->update($projectData);
            }

            // Run this step if the data and data budget checks match.
            if (array_key_exists('budget', $data) && $data['budget'] !== null) {
                $budget = Budget::where('project_id', $id)->orderByDesc('budget_id')->first();
                // Run this step if the budget is set.
                if ($budget) {
                    app(BudgetHistoryService::class)->revise($budget, ['budget_amount' => $data['budget']], $data['budget_revision_reason'] ?? 'Budget updated through Projects.');
                } else {
                    app(BudgetHistoryService::class)->create([
                        'project_id' => $id,
                        'budget_amount' => $data['budget'],
                        'actual_amount' => 0,
                    ], $data['budget_revision_reason'] ?? 'Budget first entered for an existing project.');
                }
            }
        });

        $project = $this->findPresented($id);
        $newStatus = strtolower((string) $project->status);
        // Run this step if the new status does not equal old status.
        if ($newStatus !== $oldStatus) {
            $this->notifyStatusChange($project, $newStatus);
        }
        $this->modelRetraining->afterDataChange([(int) $id]);
        // Run this step if the id check matches.
        if ($updated = Project::find($id)) $this->audit->record($updated, 'UPDATE', (array) $existing, $updated->getAttributes());

        return response()->json($project);
    }

    // Delete the selected project record.
    public function destroy(int $id): JsonResponse
    {
        $existing = DB::table('project_tbl')->where('project_id', $id)->first();
        // Return early if the existing is missing or false.
        if (! $existing) {
            return response()->json(['message' => 'Project not found'], 404);
        }

        $hasBudget = DB::table('budgets_tbl')->where('project_id', $id)->where('budget_amount', '>', 0)->exists();
        $hasFinanceExpenses = DB::table('fin_expense_tbl')
            ->where('project_id', $id)->exists();

        // Return early if the has budget and has finance expenses checks match.
        if ($hasBudget || $hasFinanceExpenses) {
            return response()->json([
                'message' => 'Project cannot be deleted while it has budget allocations or expense records. Remove those records first.',
            ], 409);
        }

        $linkedTables = [
            'inventory_transaction_tbl' => 'inventory transactions',
            'inventory_cost_allocation_tbl' => 'inventory cost allocations',
            'fin_project_contract_tbl' => 'contracts',
            'fin_construction_bond_tbl' => 'construction bonds',
            'fin_receivable_payable_tbl' => 'receivable/payable records',
            'fin_equipment_expense_tbl' => 'equipment expenses',
            'fin_equipment_rental_income_tbl' => 'equipment rental income',
            'expense_tbl' => 'legacy expense records',
        ];
        // Process each label.
        foreach ($linkedTables as $table => $label) {
            // Return early if the table and id checks match.
            if (Schema::hasColumn($table, 'project_id') && DB::table($table)->where('project_id', $id)->exists()) {
                return response()->json([
                    'message' => "Project cannot be deleted while it has {$label}. Remove those records first.",
                ], 409);
            }
        }

        // Try this action and handle any errors below.
        try {
            // Run these database steps together. Undo changes if a step fails.
            DB::transaction(function () use ($id, $existing) {
                DB::table('budgets_tbl')->where('project_id', $id)->delete();
                DB::table('project_tbl')->where('project_id', $id)->delete();
                $deleted = new Project();
                $deleted->setRawAttributes((array) $existing, true);
                $this->audit->record($deleted, 'DELETE', (array) $existing, []);
            });
        } catch (QueryException $exception) {
            // A linked record may be added after the checks, or exist in a legacy table.
            if (in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 787], true)) {
                return response()->json([
                    'message' => 'Project cannot be deleted while it has linked records. Remove those records first.',
                ], 409);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Project deleted']);
    }

    // Check project.
    private function validateProject(Request $request, bool $updating, ?object $existing = null): array
    {
        $required = $updating ? ['sometimes', 'required'] : ['required'];
        $optionalOnCreate = $updating ? ['sometimes', 'required'] : ['nullable'];
        $validator = Validator::make($request->all(), [
            'project_name' => [...$required, 'string', 'max:150'],
            'client_name' => [...$required, 'string', 'max:150'],
            'project_manager' => [...$required, 'string', 'max:150', function ($attribute, $value, $fail) use ($existing) {
                // Run this step if the existing and project manager checks match.
                if ($existing && trim((string) $existing->project_manager) === trim((string) $value)) return;
                // Run this step if the exists check matches.
                if (! DB::table('project_manager_tbl')->where('manager_name', trim((string) $value))->exists()) {
                    $fail('Select a project manager configured in Settings.');
                }
            }],
            'start_date' => [...$required, 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'estimated_end_date' => [...$required, 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'actual_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'worker_count' => [...$optionalOnCreate, 'integer', 'min:1', 'max:100000'],
            'phase' => [...$optionalOnCreate, 'string', Rule::exists('project_phase_tbl', 'phase_name')],
            'status' => [...$optionalOnCreate, 'string', 'in:'.implode(',', self::STATUSES)],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'budget_revision_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $validator->after(function ($validator) use ($request, $existing) {
            $start = $request->input('start_date', $existing?->start_date);
            $estimated = $request->input('estimated_end_date', $existing?->estimated_end_date);
            $actual = $request->input('actual_end_date', $existing?->actual_end_date);
            $today = today()->toDateString();
            // Run this step if the estimated and today checks match.
            if ($estimated && $estimated < $today && (! $existing || $estimated !== $existing->estimated_end_date)) {
                $validator->errors()->add('estimated_end_date', 'The estimated end date cannot be in the past.');
            }
            // Run this step if the actual and today checks match.
            if ($actual && $actual < $today && (! $existing || $actual !== $existing->actual_end_date)) {
                $validator->errors()->add('actual_end_date', 'The actual end date cannot be in the past.');
            }
            // Run this step if the start and estimated checks match.
            if ($start && $estimated && strtotime($estimated) <= strtotime($start)) {
                $validator->errors()->add('estimated_end_date', 'The estimated end date must be after the start date.');
            }
            // Run this step if the start and actual checks match.
            if ($start && $actual && strtotime($actual) <= strtotime($start)) {
                $validator->errors()->add('actual_end_date', 'The actual end date must be after the start date.');
            }
        });

        return $validator->validate();
    }

    // Check unique natural key.
    private function assertUniqueNaturalKey(string $name, string $client, string $startDate, ?int $ignoreId = null): void
    {
        $normalizedName = $this->normalizeKey($name);
        $normalizedClient = $this->normalizeKey($client);
        $candidates = DB::table('project_tbl')->whereDate('start_date', $startDate);
        // Run this step if the ignore id is not null.
        if ($ignoreId !== null) {
            $candidates->where('project_id', '!=', $ignoreId);
        }

        $duplicate = $candidates->lockForUpdate()->get(['project_name', 'client_name'])->contains(
            fn ($project) => $this->normalizeKey((string) $project->project_name) === $normalizedName
                && $this->normalizeKey((string) $project->client_name) === $normalizedClient
        );

        // Stop if the duplicate is set.
        if ($duplicate) {
            throw ValidationException::withMessages([
                'project_name' => ['A project with the same name, client, and start date already exists.'],
            ]);
        }
    }

    // Clean up label.
    private function normalizeLabel(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    // Clean up key.
    private function normalizeKey(string $value): string
    {
        return mb_strtolower($this->normalizeLabel($value));
    }

    // Find presented.
    private function findPresented(int $id): object
    {
        $latestBudget = DB::table('budgets_tbl')->where('project_id', $id)->orderByDesc('budget_id')->first();
        $project = DB::table('project_tbl')->where('project_id', $id)->first();
        $project->budget = (float) ($latestBudget->budget_amount ?? 0);
        $project->budget_context = app(BudgetHistoryService::class)->context($id);

        return $project;
    }

    // Get the notify status change.
    private function notifyStatusChange(object $project, string $newStatus): void
    {
        $type = match ($newStatus) {
            'delayed' => ['project_delayed', 'Project Delayed', 'overdue'],
            'at risk' => ['project_at_risk', 'Project At Risk', 'warning'],
            default => null,
        };
        // Run this step if the type and project id checks match.
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

    // Get the flag projects near deadline.
    private function flagProjectsNearDeadline(): void
    {
        $leadDays = max(0, min(365, (int) SystemSetting::value('project_at_risk_days_before_end_date', 7)));

        DB::table('project_tbl')
            ->whereNotNull('estimated_end_date')
            ->where('status', '!=', 'Completed')
            ->whereDate('estimated_end_date', '>=', now()->toDateString())
            ->whereDate('estimated_end_date', '<=', now()->addDays($leadDays)->toDateString())
            ->update(['status' => 'At Risk']);
    }
}
