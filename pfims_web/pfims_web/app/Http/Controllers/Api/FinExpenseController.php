<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FinExpenseController extends Controller
{
    private const PROJECT_COST_COMPONENTS = ['material', 'labor', 'equipment', 'other'];

    public function __construct(private NotificationService $notifications) {}

    private function mapExpense($item)
    {
        return [
            'fin_expense_id' => $item->fin_expense_id,
            'expense_id' => $item->fin_expense_id,
            'inventory_transaction_id' => $item->inventory_transaction_id ?? null,
            'is_pending_inventory' => false,
            'project_id' => $item->project_id,
            'project_name' => $item->project_name,
            'project_cost_component' => $item->project_cost_component ?? null,
            'project_cost_component_label' => $this->costComponentLabel($item->project_cost_component ?? null),
            'expense_description' => $item->expense_description ?? '',
            'fin_category_id' => $item->fin_category_id,
            'expense_category_id' => $item->fin_category_id,
            'category_name' => $item->category_name ?? '',
            'amount' => (float) $item->amount,
            'expense_date' => $item->expense_date,
            'remarks' => $item->remarks,
            'proof_file_path' => $item->proof_file_path,
            'proof_file_name' => $item->proof_file_name,
        ];
    }

    private function findWithJoins($id)
    {
        return DB::table('fin_expense_tbl')
            ->leftJoin('project_tbl', 'fin_expense_tbl.project_id', '=', 'project_tbl.project_id')
            ->leftJoin('fin_expense_category_tbl', 'fin_expense_tbl.fin_category_id', '=', 'fin_expense_category_tbl.fin_category_id')
            ->select(
                'fin_expense_tbl.fin_expense_id',
                'fin_expense_tbl.project_id',
                'fin_expense_tbl.inventory_transaction_id',
                'fin_expense_tbl.project_cost_component',
                'project_tbl.project_name',
                'fin_expense_tbl.fin_category_id',
                'fin_expense_category_tbl.category_name',
                'fin_expense_tbl.expense_description',
                'fin_expense_tbl.amount',
                'fin_expense_tbl.expense_date',
                'fin_expense_tbl.remarks',
                'fin_expense_tbl.proof_file_path',
                'fin_expense_tbl.proof_file_name'
            )
            ->where('fin_expense_tbl.fin_expense_id', $id)
            ->first();
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'category_id' => ['nullable', 'integer', 'exists:fin_expense_category_tbl,fin_category_id'],
            'project_cost_component' => ['nullable', 'string', 'in:material,labor,equipment,other'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'],
            'include_pending' => ['nullable', 'boolean'],
        ]);

        try {
            $expenseQuery = DB::table('fin_expense_tbl')
                ->leftJoin('project_tbl', 'fin_expense_tbl.project_id', '=', 'project_tbl.project_id')
                ->leftJoin('fin_expense_category_tbl', 'fin_expense_tbl.fin_category_id', '=', 'fin_expense_category_tbl.fin_category_id')
                ->select(
                    'fin_expense_tbl.fin_expense_id',
                    'fin_expense_tbl.project_id',
                    'fin_expense_tbl.inventory_transaction_id',
                    'fin_expense_tbl.project_cost_component',
                    'project_tbl.project_name',
                    'fin_expense_tbl.fin_category_id',
                    'fin_expense_category_tbl.category_name',
                    'fin_expense_tbl.expense_description',
                    'fin_expense_tbl.amount',
                    'fin_expense_tbl.expense_date',
                    'fin_expense_tbl.remarks',
                    'fin_expense_tbl.proof_file_path',
                    'fin_expense_tbl.proof_file_name'
                );

            if (! empty($filters['project_id'])) {
                $expenseQuery->where('fin_expense_tbl.project_id', $filters['project_id']);
            }
            if (! empty($filters['category_id'])) {
                $expenseQuery->where('fin_expense_tbl.fin_category_id', $filters['category_id']);
            }
            if (! empty($filters['project_cost_component'])) {
                $expenseQuery->where('fin_expense_tbl.project_cost_component', $filters['project_cost_component']);
            }
            if (! empty($filters['start_date'])) {
                $expenseQuery->whereDate('fin_expense_tbl.expense_date', '>=', $filters['start_date']);
            }
            if (! empty($filters['end_date'])) {
                $expenseQuery->whereDate('fin_expense_tbl.expense_date', '<=', $filters['end_date']);
            }
            if (! empty($filters['search'])) {
                $search = trim($filters['search']);
                $expenseQuery->where(function ($query) use ($search) {
                    $query->where('project_tbl.project_name', 'like', "%{$search}%")
                        ->orWhere('fin_expense_category_tbl.category_name', 'like', "%{$search}%")
                        ->orWhere('fin_expense_tbl.expense_description', 'like', "%{$search}%")
                        ->orWhere('fin_expense_tbl.remarks', 'like', "%{$search}%");
                });
            }

            $expenses = $expenseQuery
                ->orderByDesc('fin_expense_tbl.expense_date')
                ->orderByDesc('fin_expense_tbl.fin_expense_id')
                ->get();

            $mapped = $expenses->map(function ($item) {
                return $this->mapExpense($item);
            });

            $constructionSupply = DB::table('fin_expense_category_tbl')
                ->where('category_name', 'Construction Supply')
                ->first();

            $includePending = ! array_key_exists('include_pending', $filters) || (bool) $filters['include_pending'];
            $pendingMatchesCategory = empty($filters['category_id'])
                || (int) $filters['category_id'] === (int) ($constructionSupply->fin_category_id ?? 0);

            if ($constructionSupply && $includePending && $pendingMatchesCategory) {
                $pendingQuery = DB::table('inventory_transaction_tbl as transaction')
                    ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
                    ->leftJoin('project_tbl as project', 'project.project_id', '=', 'transaction.project_id')
                    ->leftJoin('fin_expense_tbl as expense', 'expense.inventory_transaction_id', '=', 'transaction.inventory_transaction_id')
                    ->where('transaction.transaction_type', 'IN')
                    ->whereNotNull('transaction.project_id')
                    ->whereNull('expense.fin_expense_id');

                if (! empty($filters['project_id'])) {
                    $pendingQuery->where('transaction.project_id', $filters['project_id']);
                }
                if (! empty($filters['start_date'])) {
                    $pendingQuery->whereDate('transaction.transaction_date', '>=', $filters['start_date']);
                }
                if (! empty($filters['end_date'])) {
                    $pendingQuery->whereDate('transaction.transaction_date', '<=', $filters['end_date']);
                }
                if (! empty($filters['search'])) {
                    $search = trim($filters['search']);
                    $pendingQuery->where(function ($query) use ($search) {
                        $query->where('project.project_name', 'like', "%{$search}%")
                            ->orWhere('item.item_name', 'like', "%{$search}%");
                    });
                }

                $pending = $pendingQuery->select(
                    'transaction.inventory_transaction_id',
                    'transaction.project_id',
                    'project.project_name',
                    'transaction.transaction_date',
                    'item.item_name'
                )
                    ->orderByDesc('transaction.transaction_date')
                    ->get()
                    ->map(function ($transaction) use ($constructionSupply) {
                        return [
                            'fin_expense_id' => null,
                            'expense_id' => null,
                            'inventory_transaction_id' => $transaction->inventory_transaction_id,
                            'is_pending_inventory' => true,
                            'project_id' => $transaction->project_id,
                            'project_name' => $transaction->project_name,
                            'project_cost_component' => 'material',
                            'project_cost_component_label' => $this->costComponentLabel('material'),
                            'expense_description' => 'Stock-in: '.$transaction->item_name,
                            'fin_category_id' => $constructionSupply->fin_category_id,
                            'expense_category_id' => $constructionSupply->fin_category_id,
                            'category_name' => $constructionSupply->category_name,
                            'amount' => null,
                            'expense_date' => $transaction->transaction_date,
                            'remarks' => 'Pending amount',
                            'proof_file_path' => null,
                            'proof_file_name' => null,
                        ];
                    });

                $mapped = $mapped->concat($pending)->sortByDesc('expense_date')->values();
            }

            return response()->json($mapped);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function storeFromInventory(Request $request, int $transactionId)
    {
        $validator = Validator::make($request->all(), ['amount' => 'required|numeric|min:0.01|max:999999999999.99']);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $amount = (float) $validator->validated()['amount'];
            $id = DB::transaction(function () use ($amount, $transactionId) {
                $transaction = DB::table('inventory_transaction_tbl as transaction')
                    ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
                    ->where('transaction.inventory_transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->select('transaction.*', 'item.item_name')
                    ->first();

                if (! $transaction || $transaction->transaction_type !== 'IN') {
                    abort(404, 'Stock-in transaction not found.');
                }
                if (blank($transaction->project_id)) {
                    abort(422, 'Stock-in finance expenses require a linked project.');
                }

                if (DB::table('fin_expense_tbl')->where('inventory_transaction_id', $transactionId)->exists()) {
                    abort(409, 'An expense already exists for this stock-in transaction.');
                }

                $category = DB::table('fin_expense_category_tbl')
                    ->where('category_name', 'Construction Supply')
                    ->where('is_active', true)
                    ->first();

                if (! $category) {
                    abort(422, 'The Construction Supply finance category is unavailable.');
                }

                return DB::table('fin_expense_tbl')->insertGetId([
                    'project_id' => $transaction->project_id,
                    'fin_category_id' => $category->fin_category_id,
                    'inventory_transaction_id' => $transactionId,
                    'project_cost_component' => 'material',
                    'expense_description' => 'Stock-in: '.$transaction->item_name,
                    'amount' => $amount,
                    'expense_date' => $transaction->transaction_date,
                    'remarks' => 'Created from inventory stock-in',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return response()->json($this->mapExpense($this->findWithJoins($id)), 201);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json(['error' => $e->getMessage() ?: 'Unable to create expense.'], $status ?: 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'project_cost_component' => 'nullable|in:material,labor,equipment,other',
                'expense_description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01|max:999999999999.99',
                'expense_date' => 'required|date|before_or_equal:today',
                'remarks' => 'nullable|string|max:255',
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            if ($errors = $this->projectComponentErrors($validated)) {
                return response()->json(['errors' => $errors], 422);
            }

            $data = [
                'project_id' => $validated['project_id'] ?? null,
                'fin_category_id' => $validated['fin_category_id'],
                'project_cost_component' => $this->normalizeCostComponent($validated['project_cost_component'] ?? null),
                'expense_description' => trim($validated['expense_description']),
                'amount' => round((float) $validated['amount'], 2),
                'expense_date' => $validated['expense_date'],
                'remarks' => blank($validated['remarks'] ?? null) ? null : trim($validated['remarks']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($this->duplicateExpense($data)) {
                return response()->json(['error' => 'This finance expense already exists.'], 409);
            }

            if ($request->hasFile('proof_file')) {
                $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'public');
                $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
            }

            $id = DB::table('fin_expense_tbl')->insertGetId($data);
            $expense = $this->findWithJoins($id);

            // Send notification
            $category = $expense->category_name ?? 'Expense';
            $project = $expense->project_name ? " for {$expense->project_name}" : '';
            $formatted = 'PHP '.number_format((float) $expense->amount, 2);

            $this->notifications->notify(
                title: 'New Expense Recorded',
                message: "{$category}{$project}: {$expense->expense_description} ({$formatted}).",
                type: 'new_expense',
                kind: 'info',
                filter: 'alerts',
                referenceType: 'fin_expense',
                referenceId: (int) $expense->fin_expense_id,
            );

            return response()->json($this->mapExpense($expense), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $existing = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->first();
            if (! $existing) {
                return response()->json(['error' => 'Expense not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'project_cost_component' => 'nullable|in:material,labor,equipment,other',
                'expense_description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01|max:999999999999.99',
                'expense_date' => 'required|date|before_or_equal:today',
                'remarks' => 'nullable|string|max:255',
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
                'remove_proof_file' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            if ($errors = $this->projectComponentErrors($validated)) {
                return response()->json(['errors' => $errors], 422);
            }

            $data = [
                'project_id' => $validated['project_id'] ?? null,
                'fin_category_id' => $validated['fin_category_id'],
                'project_cost_component' => $this->normalizeCostComponent($validated['project_cost_component'] ?? null),
                'expense_description' => trim($validated['expense_description']),
                'amount' => round((float) $validated['amount'], 2),
                'expense_date' => $validated['expense_date'],
                'remarks' => blank($validated['remarks'] ?? null) ? null : trim($validated['remarks']),
                'updated_at' => now(),
            ];
            if ($this->duplicateExpense($data, (int) $id)) {
                return response()->json(['error' => 'This finance expense already exists.'], 409);
            }

            if ($request->hasFile('proof_file')) {
                if ($existing->proof_file_path) {
                    Storage::disk('public')->delete($existing->proof_file_path);
                }
                $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'public');
                $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
            } elseif ($request->boolean('remove_proof_file')) {
                if ($existing->proof_file_path) {
                    Storage::disk('public')->delete($existing->proof_file_path);
                }
                $data['proof_file_path'] = null;
                $data['proof_file_name'] = null;
            }

            DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->update($data);
            $expense = $this->findWithJoins($id);

            return response()->json($this->mapExpense($expense));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $existing = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->first();
            if (! $existing) {
                return response()->json(['error' => 'Expense not found'], 404);
            }

            if ($existing->proof_file_path) {
                Storage::disk('public')->delete($existing->proof_file_path);
            }

            DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->delete();

            return response()->json(['success' => true, 'message' => 'Expense deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function duplicateExpense(array $data, ?int $ignoreId = null): bool
    {
        $query = DB::table('fin_expense_tbl')
            ->where('fin_category_id', $data['fin_category_id'])
            ->where('amount', $data['amount'])
            ->whereDate('expense_date', $data['expense_date'])
            ->whereRaw('LOWER(TRIM(expense_description)) = ?', [strtolower(trim($data['expense_description']))]);
        $query = empty($data['project_id']) ? $query->whereNull('project_id') : $query->where('project_id', $data['project_id']);
        $query = empty($data['project_cost_component']) ? $query->whereNull('project_cost_component') : $query->where('project_cost_component', $data['project_cost_component']);
        if ($ignoreId !== null) {
            $query->where('fin_expense_id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function projectComponentErrors(array $data): array
    {
        $classification = DB::table('fin_expense_category_tbl')
            ->where('fin_category_id', $data['fin_category_id'] ?? null)
            ->value('classification');
        $isDirect = strtolower((string) $classification) === 'direct';
        $hasProject = ! blank($data['project_id'] ?? null);
        $hasComponent = ! blank($data['project_cost_component'] ?? null);
        $errors = [];

        if ($isDirect && ! $hasProject) {
            $errors['project_id'][] = 'A direct project expense requires a valid project.';
        }
        if (($isDirect || $hasProject) && ! $hasComponent) {
            $errors['project_cost_component'][] = 'Select a project cost component for project expenses.';
        }

        return $errors;
    }

    private function normalizeCostComponent(?string $component): ?string
    {
        $component = strtolower(trim((string) $component));

        return in_array($component, self::PROJECT_COST_COMPONENTS, true) ? $component : null;
    }

    private function costComponentLabel(?string $component): ?string
    {
        return match ($this->normalizeCostComponent($component)) {
            'material' => 'Material',
            'labor' => 'Labor',
            'equipment' => 'Equipment',
            'other' => 'Other',
            default => null,
        };
    }
}
