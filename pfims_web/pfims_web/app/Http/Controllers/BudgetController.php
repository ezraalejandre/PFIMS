<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\BudgetHistoryService;
use App\Services\NotificationService;
use App\Services\ProjectCostLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Group the budget actions.
class BudgetController extends Controller
{
    // Set up the values this class needs.
    public function __construct(
        private NotificationService $notifications,
    ) {}

    // List the budget records.
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'search' => ['nullable', 'string', 'max:150'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount', 'max:999999999999.99'],
        ]);

        $query = Budget::with('project:project_id,project_name');
        // Run this step if the filters project id has a value.
        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        // Run this step if the filters search has a value.
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('project', fn ($project) => $project->where('project_name', 'like', "%{$search}%"));
        }
        // Run this step if the filters min amount is set.
        if (isset($filters['min_amount'])) {
            $query->where('budget_amount', '>=', $filters['min_amount']);
        }
        // Run this step if the filters max amount is set.
        if (isset($filters['max_amount'])) {
            $query->where('budget_amount', '<=', $filters['max_amount']);
        }

        return response()->json($query->orderByDesc('budget_id')->get()->map(fn (Budget $budget) => $this->present($budget)));
    }

    // Save a new budget record.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:project_tbl,project_id'],
            'budget_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'revision_reason' => ['nullable', 'string', 'max:500'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        // Return early if the exists project id check matches.
        if (Budget::where('project_id', $data['project_id'])->exists()) {
            return response()->json([
                'message' => 'This project already has a budget. Update the existing budget instead.',
                'errors' => ['project_id' => ['This project already has a budget.']],
            ], 409);
        }

        $path = null;
        // Try this action and handle any errors below.
        try {
            // Run this step if the has file check matches.
            if ($request->hasFile('proof_file')) {
                $path = $request->file('proof_file')->store('proofs', 'protected');
            }
            // Run these database steps together. Undo changes if a step fails.
            $budget = DB::transaction(function () use ($data, $path, $request) {
                DB::table('project_tbl')->where('project_id', $data['project_id'])->lockForUpdate()->first();
                // Stop if the exists project id check matches.
                if (Budget::where('project_id', $data['project_id'])->exists()) {
                    throw new \DomainException('This project already has a budget.');
                }

                return app(BudgetHistoryService::class)->create([
                    'project_id' => $data['project_id'],
                    'budget_amount' => $data['budget_amount'],
                    'actual_amount' => app(ProjectCostLedger::class)->forProject((int) $data['project_id'])['total'],
                    'proof_file_path' => $path,
                    'proof_file_name' => $path ? mb_substr($request->file('proof_file')->getClientOriginalName(), 0, 255) : null,
                ], $data['revision_reason'] ?? 'Initial budget entered through Finance.', true);
            });
        } catch (\DomainException $exception) {
            // Run this step if the path is set.
            if ($path) {
                app(\App\Services\TrashService::class)->retainFile($path);
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['project_id' => [$exception->getMessage()]],
            ], 409);
        } catch (\Throwable $exception) {
            // Run this step if the path is set.
            if ($path) {
                app(\App\Services\TrashService::class)->retainFile($path);
            }
            throw $exception;
        }

        $budget->load('project:project_id,project_name');
        $this->notifications->notify(
            title: 'New Budget Allocated',
            message: 'Budget of PHP '.number_format((float) $budget->budget_amount, 2)." allocated for \"{$budget->project?->project_name}\".",
            type: 'new_budget', kind: 'info', filter: 'alerts',
            referenceType: 'budget', referenceId: (int) $budget->budget_id,
        );

        return response()->json($this->present($budget), 201);
    }

    // Save changes to the budget record.
    public function update(Request $request, int $id): JsonResponse
    {
        $budget = Budget::find($id);
        // Return early if the budget is missing or false.
        if (! $budget) {
            return response()->json(['message' => 'Budget not found'], 404);
        }

        $data = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:project_tbl,project_id'],
            'budget_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'revision_reason' => ['nullable', 'string', 'max:500'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'remove_proof_file' => ['nullable', 'boolean'],
        ]);

        $oldPath = $budget->proof_file_path;
        $newPath = null;
        // Try this action and handle any errors below.
        try {
            // Run this step if the has file check matches.
            if ($request->hasFile('proof_file')) {
                $newPath = $request->file('proof_file')->store('proofs', 'protected');
            }
            // Run these database steps together. Undo changes if a step fails.
            DB::transaction(function () use ($budget, $data, $request, $newPath) {
                $changes = ['budget_amount' => $data['budget_amount']];
                $projectId = (int) ($data['project_id'] ?? $budget->project_id);
                // Run this step if the project id does not equal project id.
                if ($projectId !== (int) $budget->project_id) {
                    DB::table('project_tbl')->whereIn('project_id', [$budget->project_id, $projectId])
                        ->orderBy('project_id')->lockForUpdate()->get();
                    // Stop if the where does not equal budget budget id exists.
                    if (Budget::where('project_id', $projectId)->where('budget_id', '!=', $budget->budget_id)->exists()) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'project_id' => 'The selected project already has a budget.',
                        ]);
                    }
                    $cost = app(ProjectCostLedger::class)->forProject((int) $budget->project_id);
                    // Stop if the cost total and cost unvalued count checks match.
                    if ($cost['total'] > 0 || $cost['unvalued_count'] > 0) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'project_id' => 'A budget with recorded expenses cannot be moved to another project.',
                        ]);
                    }
                    $changes['project_id'] = $projectId;
                    $changes['actual_amount'] = app(ProjectCostLedger::class)->forProject($projectId)['total'];
                }
                // Run this step if the new path is set.
                if ($newPath) {
                    $changes['proof_file_path'] = $newPath;
                    $changes['proof_file_name'] = mb_substr($request->file('proof_file')->getClientOriginalName(), 0, 255);
                } elseif ($request->boolean('remove_proof_file')) {
                    $changes['proof_file_path'] = null;
                    $changes['proof_file_name'] = null;
                }
                app(BudgetHistoryService::class)->revise($budget, $changes, $data['revision_reason'] ?? 'Budget updated through Finance.');
            });
        } catch (\Throwable $exception) {
            // Run this step if the new path is set.
            if ($newPath) {
                app(\App\Services\TrashService::class)->retainFile($newPath);
            }
            throw $exception;
        }

        // Run this step if the old path and new path checks match.
        if ($oldPath && ($newPath || $request->boolean('remove_proof_file'))) {
            app(\App\Services\TrashService::class)->retainFile($oldPath);
        }
        $budget->load('project:project_id,project_name');

        return response()->json($this->present($budget));
    }

    // Delete the selected budget record.
    public function destroy(int $id): JsonResponse
    {
        $budget = Budget::find($id);
        // Return early if the budget is missing or false.
        if (! $budget) {
            return response()->json(['message' => 'Budget not found'], 404);
        }
        $cost = app(ProjectCostLedger::class)->forProject((int) $budget->project_id);
        // Return early if the cost total and cost unvalued count checks match.
        if ($cost['total'] > 0 || $cost['unvalued_count'] > 0) {
            return response()->json(['message' => 'A budget with recorded expenses cannot be deleted.'], 409);
        }

        $path = $budget->proof_file_path;
        // Try this action and handle any errors below.
        try {
            app(BudgetHistoryService::class)->remove($budget, 'Budget deleted through Finance.');
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }
        // Run this step if the path is set.
        if ($path) {
            app(\App\Services\TrashService::class)->retainFile($path);
        }

        return response()->json(['message' => 'Budget deleted']);
    }

    // Get the history.
    public function history(int $id): JsonResponse
    {
        $budget = Budget::find($id);
        // Return early if the budget is missing or false.
        if (! $budget) {
            return response()->json(['message' => 'Budget not found'], 404);
        }

        return response()->json([
            'budget_context' => app(BudgetHistoryService::class)->context((int) $budget->project_id, (float) $budget->budget_amount, (int) $budget->budget_id),
            'history' => \Illuminate\Support\Facades\Schema::hasTable('project_budget_history')
                ? DB::table('project_budget_history')->where('project_id', $budget->project_id)->orderBy('history_id')->get()
                : [],
        ]);
    }

    // Get the present.
    private function present(Budget $budget): array
    {
        $cost = app(ProjectCostLedger::class)->forProject((int) $budget->project_id);
        return [
            'budget_id' => $budget->budget_id,
            'project_id' => $budget->project_id,
            'project_name' => $budget->project?->project_name,
            'budget_amount' => $budget->budget_amount,
            'budget_context' => app(BudgetHistoryService::class)->context((int) $budget->project_id, (float) $budget->budget_amount, (int) $budget->budget_id),
            'actual_amount' => $cost['total'],
            'direct_cost' => $cost['direct'],
            'inventory_usage_cost' => $cost['allocated'],
            'unvalued_withdrawals' => $cost['unvalued_count'],
            'proof_file_path' => $budget->proof_file_path,
            'proof_file_name' => $budget->proof_file_name,
        ];
    }
}
