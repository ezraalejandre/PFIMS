<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BudgetController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'search' => ['nullable', 'string', 'max:150'],
            'min_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'max_amount' => ['nullable', 'numeric', 'gte:min_amount', 'max:999999999999.99'],
        ]);

        $query = Budget::with('project:project_id,project_name');
        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->whereHas('project', fn ($project) => $project->where('project_name', 'like', "%{$search}%"));
        }
        if (isset($filters['min_amount'])) {
            $query->where('budget_amount', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount'])) {
            $query->where('budget_amount', '<=', $filters['max_amount']);
        }

        return response()->json($query->orderByDesc('budget_id')->get()->map(fn (Budget $budget) => $this->present($budget)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:project_tbl,project_id'],
            'budget_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        if (Budget::where('project_id', $data['project_id'])->exists()) {
            return response()->json([
                'message' => 'This project already has a budget. Update the existing budget instead.',
                'errors' => ['project_id' => ['This project already has a budget.']],
            ], 409);
        }

        $path = null;
        try {
            if ($request->hasFile('proof_file')) {
                $path = $request->file('proof_file')->store('proofs', 'public');
            }
            $budget = DB::transaction(function () use ($data, $path, $request) {
                DB::table('project_tbl')->where('project_id', $data['project_id'])->lockForUpdate()->first();
                if (Budget::where('project_id', $data['project_id'])->exists()) {
                    throw new \DomainException('This project already has a budget.');
                }

                return Budget::create([
                    'project_id' => $data['project_id'],
                    'budget_amount' => $data['budget_amount'],
                    'actual_amount' => 0,
                    'proof_file_path' => $path,
                    'proof_file_name' => $path ? mb_substr($request->file('proof_file')->getClientOriginalName(), 0, 255) : null,
                ]);
            });
        } catch (\DomainException $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['project_id' => [$exception->getMessage()]],
            ], 409);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
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

    public function update(Request $request, int $id): JsonResponse
    {
        $budget = Budget::find($id);
        if (! $budget) {
            return response()->json(['message' => 'Budget not found'], 404);
        }

        $data = $request->validate([
            'budget_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'remove_proof_file' => ['nullable', 'boolean'],
        ]);

        $oldPath = $budget->proof_file_path;
        $newPath = null;
        try {
            if ($request->hasFile('proof_file')) {
                $newPath = $request->file('proof_file')->store('proofs', 'public');
            }
            DB::transaction(function () use ($budget, $data, $request, $newPath) {
                $changes = ['budget_amount' => $data['budget_amount']];
                if ($newPath) {
                    $changes['proof_file_path'] = $newPath;
                    $changes['proof_file_name'] = mb_substr($request->file('proof_file')->getClientOriginalName(), 0, 255);
                } elseif ($request->boolean('remove_proof_file')) {
                    $changes['proof_file_path'] = null;
                    $changes['proof_file_name'] = null;
                }
                $budget->update($changes);
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }

        if ($oldPath && ($newPath || $request->boolean('remove_proof_file'))) {
            Storage::disk('public')->delete($oldPath);
        }
        $budget->load('project:project_id,project_name');

        return response()->json($this->present($budget));
    }

    public function destroy(int $id): JsonResponse
    {
        $budget = Budget::find($id);
        if (! $budget) {
            return response()->json(['message' => 'Budget not found'], 404);
        }
        if ((float) $budget->actual_amount > 0) {
            return response()->json(['message' => 'A budget with recorded expenses cannot be deleted.'], 409);
        }

        $path = $budget->proof_file_path;
        $budget->delete();
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Budget deleted']);
    }

    private function present(Budget $budget): array
    {
        return [
            'budget_id' => $budget->budget_id,
            'project_id' => $budget->project_id,
            'project_name' => $budget->project?->project_name,
            'budget_amount' => $budget->budget_amount,
            'actual_amount' => $budget->actual_amount,
            'proof_file_path' => $budget->proof_file_path,
            'proof_file_name' => $budget->proof_file_name,
        ];
    }
}
