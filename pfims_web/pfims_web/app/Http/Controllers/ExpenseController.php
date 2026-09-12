<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'expense_category_id' => ['nullable', 'integer', 'exists:fin_expense_category_tbl,fin_category_id'],
            'start_date' => ['nullable', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'before_or_equal:today'],
        ]);

        $query = $this->baseQuery();
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($inner) => $inner->where('fin_expense_tbl.expense_description', 'like', "%{$search}%")
                ->orWhere('fin_expense_tbl.remarks', 'like', "%{$search}%")
                ->orWhere('fin_expense_category_tbl.category_name', 'like', "%{$search}%"));
        }
        if (! empty($filters['project_id'])) {
            $query->where('fin_expense_tbl.project_id', $filters['project_id']);
        }
        if (! empty($filters['expense_category_id'])) {
            $query->where('fin_expense_tbl.fin_category_id', $filters['expense_category_id']);
        }
        if (! empty($filters['start_date'])) {
            $query->whereDate('fin_expense_tbl.expense_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('fin_expense_tbl.expense_date', '<=', $filters['end_date']);
        }

        return response()->json($query->orderByDesc('fin_expense_tbl.expense_date')->orderByDesc('fin_expense_tbl.fin_expense_id')->get()
            ->map(fn (object $expense) => $this->present($expense)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateExpense($request);
        $data['expense_description'] = $this->normalizeLabel($data['expense_description']);
        $data['remarks'] = isset($data['remarks']) ? $this->normalizeLabel($data['remarks']) : null;
        $category = $this->category($data['expense_category_id']);
        $data['project_cost_component'] = $this->normalizeCostComponent(
            $data['project_cost_component'] ?? $this->componentForCategory($category)
        );
        $this->assertProjectComponentRules($data, $category);
        $this->assertNotDuplicate($data);

        $path = null;
        try {
            if ($request->hasFile('proof_file')) {
                $path = $request->file('proof_file')->store('proofs', 'public');
            }
            $expense = DB::transaction(function () use ($data, $request, $path) {
                $this->assertNotDuplicate($data);
                $id = DB::table('fin_expense_tbl')->insertGetId([
                    'project_id' => $data['project_id'] ?? null,
                    'fin_category_id' => $data['expense_category_id'],
                    'inventory_transaction_id' => $data['inventory_transaction_id'] ?? null,
                    'project_cost_component' => $data['project_cost_component'],
                    'expense_description' => $data['expense_description'],
                    'amount' => round((float) $data['amount'], 2),
                    'expense_date' => $data['expense_date'],
                    'remarks' => $data['remarks'] ?? null,
                    'proof_file_path' => $path,
                    'proof_file_name' => $path ? mb_substr($request->file('proof_file')->getClientOriginalName(), 0, 255) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if (! empty($data['project_id'])) {
                    $this->recalcBudgetActual((int) $data['project_id']);
                }

                return $this->find($id);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }

        $this->notifications->notify(
            title: 'New Expense Recorded',
            message: $this->expenseNotificationMessage($expense, (float) $data['amount']),
            type: 'new_expense', kind: 'info', filter: 'alerts',
            referenceType: 'fin_expense', referenceId: (int) $expense->fin_expense_id,
        );

        return response()->json($this->present($expense), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $expense = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->first();
        if (! $expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $data = $this->validateExpense($request);
        $data['expense_description'] = $this->normalizeLabel($data['expense_description']);
        $data['remarks'] = isset($data['remarks']) ? $this->normalizeLabel($data['remarks']) : null;
        $category = $this->category($data['expense_category_id']);
        $data['project_cost_component'] = $this->normalizeCostComponent(
            $data['project_cost_component'] ?? $this->componentForCategory($category)
        );
        $this->assertProjectComponentRules($data, $category);
        $this->assertNotDuplicate($data, $id);

        $oldProjectId = $expense->project_id;
        $oldPath = $expense->proof_file_path;
        $newPath = null;
        try {
            if ($request->hasFile('proof_file')) {
                $newPath = $request->file('proof_file')->store('proofs', 'public');
            }
            DB::transaction(function () use ($expense, $data, $request, $newPath, $oldProjectId, $id) {
                $this->assertNotDuplicate($data, (int) $expense->fin_expense_id);
                $changes = [
                    'amount' => round((float) $data['amount'], 2),
                    'fin_category_id' => $data['expense_category_id'],
                    'project_cost_component' => $data['project_cost_component'],
                    'expense_description' => $data['expense_description'],
                    'expense_date' => $data['expense_date'],
                    'remarks' => $data['remarks'] ?? null,
                    'project_id' => $data['project_id'] ?? null,
                    'inventory_transaction_id' => $data['inventory_transaction_id'] ?? null,
                    'updated_at' => now(),
                ];
                if ($newPath) {
                    $changes['proof_file_path'] = $newPath;
                    $changes['proof_file_name'] = mb_substr($request->file('proof_file')->getClientOriginalName(), 0, 255);
                } elseif ($request->boolean('remove_proof_file')) {
                    $changes['proof_file_path'] = null;
                    $changes['proof_file_name'] = null;
                }
                DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->update($changes);
                foreach (array_filter(array_unique([$oldProjectId, $data['project_id'] ?? null])) as $projectId) {
                    $this->recalcBudgetActual((int) $projectId);
                }
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

        return response()->json($this->present($this->find($id)));
    }

    public function destroy(int $id): JsonResponse
    {
        $expense = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->first();
        if (! $expense) {
            return response()->json(['message' => 'Expense not found'], 404);
        }

        $projectId = $expense->project_id;
        $path = $expense->proof_file_path;
        DB::transaction(function () use ($expense, $projectId) {
            DB::table('fin_expense_tbl')->where('fin_expense_id', $expense->fin_expense_id)->delete();
            if ($projectId) {
                $this->recalcBudgetActual((int) $projectId);
            }
        });
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Expense deleted']);
    }

    private function validateExpense(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'expense_category_id' => ['required', 'integer', 'exists:fin_expense_category_tbl,fin_category_id'],
            'project_cost_component' => ['nullable', 'string', 'in:material,labor,equipment,other'],
            'expense_description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'expense_date' => ['required', 'date', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'inventory_transaction_id' => ['nullable', 'integer', 'exists:inventory_transaction_tbl,inventory_transaction_id'],
            'unit_id' => ['nullable', 'integer', 'exists:unit_tbl,unit_id'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'remove_proof_file' => ['nullable', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (! $request->filled('project_id') || ! $request->filled('expense_date')) {
                return;
            }
            $project = DB::table('project_tbl')->where('project_id', $request->integer('project_id'))->first();
            $date = strtotime((string) $request->input('expense_date'));
            if ($project?->start_date && $date < strtotime($project->start_date)) {
                $validator->errors()->add('expense_date', 'The expense date cannot be before the project start date.');
            }
            if ($project?->actual_end_date && $date > strtotime($project->actual_end_date)) {
                $validator->errors()->add('expense_date', 'The expense date cannot be after the project actual end date.');
            }
        });

        return $validator->validate();
    }

    private function assertNotDuplicate(array $data, ?int $ignoreId = null): void
    {
        $query = DB::table('fin_expense_tbl')
            ->where('fin_category_id', $data['expense_category_id'])
            ->whereDate('expense_date', $data['expense_date'])
            ->where('amount', round((float) $data['amount'], 2));
        isset($data['project_id']) ? $query->where('project_id', $data['project_id']) : $query->whereNull('project_id');
        blank($data['project_cost_component'] ?? null) ? $query->whereNull('project_cost_component') : $query->where('project_cost_component', $data['project_cost_component']);
        if (! empty($data['inventory_transaction_id'])) {
            $query->where('inventory_transaction_id', $data['inventory_transaction_id']);
        }
        if ($ignoreId !== null) {
            $query->where('fin_expense_id', '!=', $ignoreId);
        }

        $description = $this->normalizeKey($data['expense_description']);
        $duplicate = $query->lockForUpdate()->get(['expense_description'])->contains(
            fn ($expense) => $this->normalizeKey((string) $expense->expense_description) === $description
        );
        if ($duplicate) {
            throw ValidationException::withMessages([
                'expense_description' => ['An identical expense already exists for this project, category, amount, and date.'],
            ]);
        }
    }

    private function componentForCategory(object $category): ?string
    {
        $value = strtolower(trim(($category->category_code ?? '').' '.($category->category_name ?? '')));

        return match (true) {
            str_contains($value, 'labor'), str_contains($value, 'labour'), str_contains($value, 'wage'), str_contains($value, 'salary') => 'labor',
            str_contains($value, 'equipment'), str_contains($value, 'rental'), str_contains($value, 'backhoe') => 'equipment',
            str_contains($value, 'material'), str_contains($value, 'supply'), str_contains($value, 'construction') => 'material',
            strtolower((string) ($category->classification ?? '')) === 'direct' => 'other',
            default => null,
        };
    }

    private function recalcBudgetActual(int $projectId): void
    {
        $total = (float) (DB::table('fin_expense_tbl')->where('project_id', $projectId)->sum('amount') ?? 0);
        DB::table('budgets_tbl')->where('project_id', $projectId)->update(['actual_amount' => $total]);
    }

    private function present(object $expense): array
    {
        return [
            'expense_id' => $expense->fin_expense_id,
            'fin_expense_id' => $expense->fin_expense_id,
            'project_id' => $expense->project_id,
            'project_name' => $expense->project_name ?? null,
            'expense_description' => $expense->expense_description,
            'expense_category_id' => $expense->fin_category_id,
            'fin_category_id' => $expense->fin_category_id,
            'category_name' => $expense->category_name ?? null,
            'project_cost_component' => $expense->project_cost_component,
            'actual_amount' => (float) $expense->amount,
            'amount' => (float) $expense->amount,
            'expense_date' => $expense->expense_date,
            'remarks' => $expense->remarks,
            'proof_file_path' => $expense->proof_file_path,
            'proof_file_name' => $expense->proof_file_name,
        ];
    }

    private function expenseNotificationMessage(object $expense, float $amount): string
    {
        $category = $expense->category_name ?? 'Expense';
        $project = ! blank($expense->project_name ?? null) ? " for {$expense->project_name}" : '';

        return "{$category}{$project}: {$expense->expense_description} (PHP ".number_format($amount, 2).').';
    }

    private function baseQuery(): Builder
    {
        return DB::table('fin_expense_tbl')
            ->leftJoin('project_tbl', 'project_tbl.project_id', '=', 'fin_expense_tbl.project_id')
            ->leftJoin('fin_expense_category_tbl', 'fin_expense_category_tbl.fin_category_id', '=', 'fin_expense_tbl.fin_category_id')
            ->select(
                'fin_expense_tbl.fin_expense_id',
                'fin_expense_tbl.project_id',
                'project_tbl.project_name',
                'fin_expense_tbl.fin_category_id',
                'fin_expense_category_tbl.category_name',
                'fin_expense_tbl.inventory_transaction_id',
                'fin_expense_tbl.project_cost_component',
                'fin_expense_tbl.expense_description',
                'fin_expense_tbl.amount',
                'fin_expense_tbl.expense_date',
                'fin_expense_tbl.remarks',
                'fin_expense_tbl.proof_file_path',
                'fin_expense_tbl.proof_file_name'
            );
    }

    private function find(int $id): object
    {
        return $this->baseQuery()->where('fin_expense_tbl.fin_expense_id', $id)->first();
    }

    private function category(int $id): object
    {
        return DB::table('fin_expense_category_tbl')->where('fin_category_id', $id)->firstOrFail();
    }

    private function assertProjectComponentRules(array $data, object $category): void
    {
        $errors = [];
        $isDirect = strtolower((string) $category->classification) === 'direct';
        if ($isDirect && blank($data['project_id'] ?? null)) {
            $errors['project_id'][] = 'A direct project expense requires a valid project.';
        }
        if (($isDirect || ! blank($data['project_id'] ?? null)) && blank($data['project_cost_component'] ?? null)) {
            $errors['project_cost_component'][] = 'Select a project cost component for project expenses.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeCostComponent(?string $component): ?string
    {
        $component = strtolower(trim((string) $component));

        return in_array($component, ['material', 'labor', 'equipment', 'other'], true) ? $component : null;
    }

    private function normalizeLabel(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower($this->normalizeLabel($value));
    }
}
