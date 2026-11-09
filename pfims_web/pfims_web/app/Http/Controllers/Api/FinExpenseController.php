<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AutomaticModelRetraining;
use App\Services\NotificationService;
use App\Services\AuditLogService;
use App\Services\InventoryPurchaseService;
use App\Services\ProjectCostLedger;
use App\Services\ExpenseCategoryRules;
use App\Models\FinExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

// Group the fin expense actions.
class FinExpenseController extends Controller
{
    private const PROJECT_COST_COMPONENTS = ['material', 'labor', 'equipment', 'other'];

    // Set up the values this class needs.
    public function __construct(
        private NotificationService $notifications,
        private AutomaticModelRetraining $modelRetraining,
        private AuditLogService $audit,
        private InventoryPurchaseService $purchaseService,
        private ProjectCostLedger $projectCosts,
    ) {}

    // Map expense.
    private function mapExpense($item)
    {
        $amount = $item->amount === null ? null : (float) $item->amount;
        $expenseType = strtolower((string) ($item->classification ?? ''));
        $isOfficeExpense = $expenseType === 'office';

        return [
            'fin_expense_id' => $item->fin_expense_id,
            'expense_id' => $item->fin_expense_id,
            'inventory_transaction_id' => $item->inventory_transaction_id ?? null,
            'entry_kind' => $item->entry_kind ?? null,
            'is_inventory_expense' => ! empty($item->inventory_transaction_id),
            'is_pending_inventory' => ! empty($item->inventory_transaction_id) && $amount === null,
            'project_id' => $item->project_id,
            'project_name' => match ($expenseType) {
                'office' => 'Office Expenses',
                'admin' => 'Office',
                default => ($item->entry_kind ?? null) === 'office_expense' ? 'Office' : $item->project_name,
            },
            'is_office_expense' => $isOfficeExpense,
            'expense_type' => $expenseType,
            'category_classification' => $item->classification ?? null,
            'project_cost_component' => $item->project_cost_component ?? null,
            'project_cost_component_label' => $this->costComponentLabel($item->project_cost_component ?? null),
            'expense_description' => $item->expense_description ?? '',
            'fin_category_id' => $item->fin_category_id,
            'expense_category_id' => $item->fin_category_id,
            'category_name' => $item->category_name ?? '',
            'amount' => $amount,
            'expense_date' => $item->expense_date,
            'remarks' => $item->remarks,
            'proof_file_path' => $item->proof_file_path,
            'proof_file_name' => $item->proof_file_name,
        ];
    }

    // Find with joins.
    private function findWithJoins($id)
    {
        return DB::table('fin_expense_tbl')
            ->leftJoin('project_tbl', 'fin_expense_tbl.project_id', '=', 'project_tbl.project_id')
            ->leftJoin('fin_expense_category_tbl', 'fin_expense_tbl.fin_category_id', '=', 'fin_expense_category_tbl.fin_category_id')
            ->select(
                'fin_expense_tbl.fin_expense_id',
                'fin_expense_tbl.project_id',
                'fin_expense_tbl.inventory_transaction_id',
                'fin_expense_tbl.entry_kind',
                'fin_expense_tbl.project_cost_component',
                'project_tbl.project_name',
                'fin_expense_tbl.fin_category_id',
                'fin_expense_category_tbl.category_name',
                'fin_expense_category_tbl.classification',
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

    // List the fin expense records.
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'category_id' => ['nullable', 'integer', 'exists:fin_expense_category_tbl,fin_category_id'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'],
            'include_pending' => ['nullable', 'boolean'],
        ]);

        // Try this action and handle any errors below.
        try {
            $expenseQuery = DB::table('fin_expense_tbl')
                ->leftJoin('project_tbl', 'fin_expense_tbl.project_id', '=', 'project_tbl.project_id')
                ->leftJoin('fin_expense_category_tbl', 'fin_expense_tbl.fin_category_id', '=', 'fin_expense_category_tbl.fin_category_id')
                ->select(
                    'fin_expense_tbl.fin_expense_id',
                    'fin_expense_tbl.project_id',
                    'fin_expense_tbl.inventory_transaction_id',
                    'fin_expense_tbl.entry_kind',
                    'fin_expense_tbl.project_cost_component',
                    'project_tbl.project_name',
                    'fin_expense_tbl.fin_category_id',
                    'fin_expense_category_tbl.category_name',
                    'fin_expense_category_tbl.classification',
                    'fin_expense_tbl.expense_description',
                    'fin_expense_tbl.amount',
                    'fin_expense_tbl.expense_date',
                    'fin_expense_tbl.remarks',
                    'fin_expense_tbl.proof_file_path',
                    'fin_expense_tbl.proof_file_name'
                );

            // Run this step if the filters project id has a value.
            if (! empty($filters['project_id'])) {
                $expenseQuery->where('fin_expense_tbl.project_id', $filters['project_id']);
            }
            // Run this step if the filters category id has a value.
            if (! empty($filters['category_id'])) {
                $expenseQuery->where('fin_expense_tbl.fin_category_id', $filters['category_id']);
            }
            // Run this step if the filters start date has a value.
            if (! empty($filters['start_date'])) {
                $expenseQuery->whereDate('fin_expense_tbl.expense_date', '>=', $filters['start_date']);
            }
            // Run this step if the filters end date has a value.
            if (! empty($filters['end_date'])) {
                $expenseQuery->whereDate('fin_expense_tbl.expense_date', '<=', $filters['end_date']);
            }
            // Run this step if the filters search has a value.
            if (! empty($filters['search'])) {
                $search = trim($filters['search']);
                // Group these checks when finding matching records.
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

            // Build a result for each record.
            $mapped = $expenses->map(function ($item) {
                return $this->mapExpense($item);
            });

            $constructionSupply = DB::table('fin_expense_category_tbl')
                // Group these checks when finding matching records.
                ->where(function ($query) {
                    $query->where('category_code', 'CONST_SUPPLY')
                        ->orWhereRaw('LOWER(category_name) LIKE ?', ['%construction suppl%']);
                })
                ->first();

            $includePending = ! array_key_exists('include_pending', $filters) || (bool) $filters['include_pending'];
            $pendingMatchesCategory = empty($filters['category_id'])
                || (int) $filters['category_id'] === (int) ($constructionSupply->fin_category_id ?? 0);

            // Run this step if the construction supply and include pending checks match.
            if ($constructionSupply && $includePending && $pendingMatchesCategory) {
                $pendingQuery = DB::table('inventory_transaction_tbl as transaction')
                    ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
                    ->join('unit_tbl as unit', 'unit.unit_id', '=', 'item.unit_id')
                    ->leftJoin('project_tbl as project', 'project.project_id', '=', 'transaction.project_id')
                    ->leftJoin('fin_expense_tbl as expense', 'expense.inventory_transaction_id', '=', 'transaction.inventory_transaction_id')
                    ->where('transaction.transaction_type', 'IN')
                    // Group these checks when finding matching records.
                    ->where(function ($query) {
                        $query->whereNull('transaction.movement_reason')
                            ->orWhere('transaction.movement_reason', 'purchase');
                    })
                    ->whereNull('expense.fin_expense_id');

                // Run this step if the filters project id has a value.
                if (! empty($filters['project_id'])) {
                    $pendingQuery->where('transaction.project_id', $filters['project_id']);
                }
                // Run this step if the filters start date has a value.
                if (! empty($filters['start_date'])) {
                    $pendingQuery->whereDate('transaction.transaction_date', '>=', $filters['start_date']);
                }
                // Run this step if the filters end date has a value.
                if (! empty($filters['end_date'])) {
                    $pendingQuery->whereDate('transaction.transaction_date', '<=', $filters['end_date']);
                }
                // Run this step if the filters search has a value.
                if (! empty($filters['search'])) {
                    $search = trim($filters['search']);
                    // Group these checks when finding matching records.
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
                    'transaction.quantity',
                    'transaction.bar_code',
                    'item.item_name',
                    'unit.unit_name'
                )
                    ->orderByDesc('transaction.transaction_date')
                    ->get()
                    // Build a result for each record.
                    ->map(function ($transaction) use ($constructionSupply) {
                        return [
                            'fin_expense_id' => null,
                            'expense_id' => null,
                            'inventory_transaction_id' => $transaction->inventory_transaction_id,
                            'is_inventory_expense' => true,
                            'is_pending_inventory' => true,
                            'entry_kind' => null,
                            'project_id' => $transaction->project_id,
                            'project_name' => $transaction->project_name,
                            'project_cost_component' => 'material',
                            'project_cost_component_label' => $this->costComponentLabel('material'),
                            'expense_description' => $this->inventoryExpenseDescription($transaction),
                            'fin_category_id' => $constructionSupply->fin_category_id,
                            'expense_category_id' => $constructionSupply->fin_category_id,
                            'category_name' => $constructionSupply->category_name,
                            'expense_type' => 'direct',
                            'category_classification' => 'direct',
                            'amount' => null,
                            'expense_date' => $transaction->transaction_date,
                            'remarks' => $this->inventoryExpenseRemarks($transaction->bar_code),
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

    // Get the store from inventory.
    public function storeFromInventory(Request $request, int $transactionId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01|max:9999999999.99',
        ]);

        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Try this action and handle any errors below.
        try {
            $details = $validator->validated();
            $amount = (float) $details['amount'];
            // Return early if the filled check matches.
            if ($request->filled('project_id')) {
                return response()->json(['error' => 'A storage purchase cannot be assigned to a project. Use stock-out for project usage.'], 422);
            }
            // Run these database steps together. Undo changes if a step fails.
            $result = DB::transaction(function () use ($amount, $transactionId) {
                $transaction = DB::table('inventory_transaction_tbl as transaction')
                    ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
                    ->where('transaction.inventory_transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->join('unit_tbl as unit', 'unit.unit_id', '=', 'item.unit_id')
                    ->select('transaction.*', 'item.item_name', 'unit.unit_name')
                    ->first();

                // Run this step if the transaction and transaction type checks match.
                if (! $transaction || $transaction->transaction_type !== 'IN') {
                    abort(404, 'Stock-in transaction not found.');
                }
                $existing = DB::table('fin_expense_tbl')
                    ->where('inventory_transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->first();
                // Run this step if the entry kind equals inventory purchase.
                if (($existing->entry_kind ?? null) === 'inventory_purchase') {
                    // Run this step if the exists check matches.
                    if (DB::table('inventory_cost_allocation_tbl')->where('in_transaction_id', $transactionId)->exists()) {
                        abort(409, 'This purchase amount cannot be changed after its stock has been allocated to a project.');
                    }
                    DB::table('fin_expense_tbl')->where('fin_expense_id', $existing->fin_expense_id)
                        ->update(['amount' => $amount, 'updated_at' => now()]);

                    return ['id' => (int) $existing->fin_expense_id, 'before' => (array) $existing];
                }
                abort(422, 'Historical stock-in requires inventory cost reconciliation before editing.');
            });

            $createdExpense = $this->findWithJoins($result['id']);
            // Run this step if the result id check matches.
            if ($audited = FinExpense::find($result['id'])) {
                $this->audit->record(
                    $audited,
                    $result['before'] ? 'UPDATE' : 'CREATE',
                    $result['before'] ?? [],
                    $audited->getAttributes()
                );
            }
            $this->modelRetraining->afterDataChange($createdExpense?->project_id === null ? [] : [(int) $createdExpense->project_id]);

            return response()->json($this->mapExpense($createdExpense), $result['before'] ? 200 : 201);
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json(['error' => $e->getMessage() ?: 'Unable to create expense.'], $status ?: 500);
        }
    }

    // Save a new fin expense record.
    public function store(Request $request)
    {
        // Try this action and handle any errors below.
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'office_expense' => 'sometimes|boolean',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'expense_description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01|max:999999999999.99',
                'expense_date' => 'required|date|before_or_equal:today',
                'remarks' => 'nullable|string|max:255',
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'inventory_item_id' => 'nullable|integer|exists:inventory_item_tbl,item_id',
                'inventory_quantity' => 'nullable|numeric|min:0.01|max:999999999999.99',
                'inventory_bar_code' => ['nullable', 'regex:/\A[0-9]{1,64}\z/'],
            ]);

            // Return early if the submitted fields fail validation.
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            $categoryRecord = DB::table('fin_expense_category_tbl')->where('fin_category_id', $validated['fin_category_id'])->first();
            $validated['project_cost_component'] = ExpenseCategoryRules::component($categoryRecord);
            $isConstructionSupply = in_array(strtoupper((string) ($categoryRecord->category_code ?? '')), ['CONST_SUPPLY', 'CONSTRUCTION_SUPPLY'], true);
            $hasInventoryDetails = ! empty($validated['inventory_item_id']) || ! empty($validated['inventory_quantity']);
            $isInventoryPurchase = $isConstructionSupply && $hasInventoryDetails;
            // Return early if the is inventory purchase and validated inventory item id checks match.
            if ($isInventoryPurchase && (empty($validated['inventory_item_id']) || empty($validated['inventory_quantity']))) {
                return response()->json(['errors' => [
                    'inventory_item_id' => ['Select the inventory item being purchased.'],
                    'inventory_quantity' => ['Enter the purchased quantity.'],
                ]], 422);
            }
            // Return early if the is inventory purchase and errors checks match.
            if (! $isInventoryPurchase && ($errors = $this->projectComponentErrors($validated))) {
                return response()->json(['errors' => $errors], 422);
            }
            // Return early if the validated project id checks match.
            if (! empty($validated['project_id']) && ! DB::table('budgets_tbl')
                ->where('project_id', $validated['project_id'])
                ->where('budget_amount', '>', 0)
                ->exists()) {
                return response()->json(['errors' => [
                    'project_id' => ['Add a budget for this project before recording an expense.'],
                ]], 422);
            }

            $inventoryItem = null;
            // Run this step if the is inventory purchase is set.
            if ($isInventoryPurchase) {
                $inventoryItem = DB::table('inventory_item_tbl as item')
                    ->join('unit_tbl as unit', 'unit.unit_id', '=', 'item.unit_id')
                    ->where('item.item_id', $validated['inventory_item_id'])
                    ->select('item.*', 'unit.unit_name')
                    ->firstOrFail();
                $validated['project_id'] = null;
                $validated['project_cost_component'] = 'material';
                $descriptionSource = (object) [
                    'quantity' => $validated['inventory_quantity'],
                    'unit_name' => $inventoryItem->unit_name,
                    'item_name' => $inventoryItem->item_name,
                ];
                $validated['expense_description'] = $this->inventoryExpenseDescription($descriptionSource);
                $validated['remarks'] = $this->inventoryExpenseRemarks($validated['inventory_bar_code'] ?? null);
            }

            $data = [
                'project_id' => $validated['project_id'] ?? null,
                'entry_kind' => ! empty($validated['office_expense']) && ! $isInventoryPurchase ? 'office_expense' : null,
                'fin_category_id' => $validated['fin_category_id'],
                'project_cost_component' => $this->normalizeCostComponent($validated['project_cost_component'] ?? null),
                'expense_description' => trim($validated['expense_description']),
                'amount' => round((float) $validated['amount'], 2),
                'expense_date' => $validated['expense_date'],
                'remarks' => blank($validated['remarks'] ?? null) ? null : trim($validated['remarks']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            // Return early if the data check matches.
            if ($this->duplicateExpense($data)) {
                return response()->json(['error' => 'This finance expense already exists.'], 409);
            }

            // Run this step if the has file check matches.
            if ($request->hasFile('proof_file')) {
                $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'protected');
                $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
            }

            // Run these database steps together. Undo changes if a step fails.
            $id = DB::transaction(function () use ($data, $isInventoryPurchase, $inventoryItem, $validated) {
                // Run this step if the is inventory purchase is set.
                if ($isInventoryPurchase) {
                    $lockedItem = DB::table('inventory_item_tbl')->where('item_id', $inventoryItem->item_id)->lockForUpdate()->firstOrFail();
                    $transactionId = DB::table('inventory_transaction_tbl')->insertGetId([
                        'item_id' => $lockedItem->item_id,
                        'project_id' => null,
                        'transaction_type' => 'IN',
                        'movement_reason' => 'purchase',
                        'recorded_at' => now(),
                        'quantity' => $validated['inventory_quantity'],
                        'bar_code' => $validated['inventory_bar_code'] ?? null,
                        'transaction_date' => $validated['expense_date'],
                        'proof_file_path' => $data['proof_file_path'] ?? null,
                        'proof_file_name' => $data['proof_file_name'] ?? null,
                    ]);
                    DB::table('inventory_item_tbl')->where('item_id', $lockedItem->item_id)->update([
                        'current_stock' => (float) $lockedItem->current_stock + (float) $validated['inventory_quantity'],
                    ]);
                    return $this->purchaseService->createLinkedExpense(
                        (int) $transactionId,
                        $validated['amount'],
                        (int) $validated['fin_category_id'],
                    );
                }

                return DB::table('fin_expense_tbl')->insertGetId($data);
            });
            $expense = $this->findWithJoins($id);
            // Run this step if the project id is not null.
            if ($expense->project_id !== null) {
                $this->projectCosts->syncBudget((int) $expense->project_id);
            }
            // Run this step if the id check matches.
            if ($audited = FinExpense::find($id)) $this->audit->record($audited, 'CREATE', [], $audited->getAttributes());

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

            $this->modelRetraining->afterDataChange($expense->project_id === null ? [] : [(int) $expense->project_id]);

            return response()->json($this->mapExpense($expense), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Save changes to the fin expense record.
    public function update(Request $request, $id)
    {
        // Try this action and handle any errors below.
        try {
            $existing = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->first();
            // Return early if the existing is missing or false.
            if (! $existing) {
                return response()->json(['error' => 'Expense not found'], 404);
            }
            // Return early if the inventory transaction id is not null.
            if (($existing->inventory_transaction_id ?? null) !== null) {
                return response()->json(['error' => 'Linked stock-in expenses cannot be reassigned or edited here.'], 422);
            }

            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'office_expense' => 'sometimes|boolean',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'expense_description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01|max:999999999999.99',
                'expense_date' => 'required|date|before_or_equal:today',
                'remarks' => 'nullable|string|max:255',
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'remove_proof_file' => 'sometimes|boolean',
            ]);

            // Return early if the submitted fields fail validation.
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            $categoryRecord = DB::table('fin_expense_category_tbl')->where('fin_category_id', $validated['fin_category_id'])->first();
            $validated['project_cost_component'] = ExpenseCategoryRules::component($categoryRecord);
            $oldProjectId = $existing->project_id === null ? null : (int) $existing->project_id;
            // Return early if the validated check matches.
            if ($errors = $this->projectComponentErrors($validated)) {
                return response()->json(['errors' => $errors], 422);
            }

            $data = [
                'project_id' => $validated['project_id'] ?? null,
                'entry_kind' => ! empty($validated['office_expense']) ? 'office_expense' : null,
                'fin_category_id' => $validated['fin_category_id'],
                'project_cost_component' => $this->normalizeCostComponent($validated['project_cost_component'] ?? null),
                'expense_description' => trim($validated['expense_description']),
                'amount' => round((float) $validated['amount'], 2),
                'expense_date' => $validated['expense_date'],
                'remarks' => blank($validated['remarks'] ?? null) ? null : trim($validated['remarks']),
                'updated_at' => now(),
            ];
            // Return early if the id check matches.
            if ($this->duplicateExpense($data, (int) $id)) {
                return response()->json(['error' => 'This finance expense already exists.'], 409);
            }

            // Run this step if the has file check matches.
            if ($request->hasFile('proof_file')) {
                // Run this step if the proof file path is set.
                if ($existing->proof_file_path) {
                    app(\App\Services\TrashService::class)->retainFile($existing->proof_file_path);
                }
                $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'protected');
                $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
            } elseif ($request->boolean('remove_proof_file')) {
                // Run this step if the proof file path is set.
                if ($existing->proof_file_path) {
                    app(\App\Services\TrashService::class)->retainFile($existing->proof_file_path);
                }
                $data['proof_file_path'] = null;
                $data['proof_file_name'] = null;
            }

            DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->update($data);
            // Process each project id.
            foreach (array_unique(array_filter([$oldProjectId, $data['project_id']])) as $projectId) {
                $this->projectCosts->syncBudget((int) $projectId);
            }
            $expense = $this->findWithJoins($id);
            // Run this step if the id check matches.
            if ($audited = FinExpense::find($id)) $this->audit->record($audited, 'UPDATE', (array) $existing, $audited->getAttributes());
            $projectIds = array_values(array_filter([$oldProjectId, $expense->project_id === null ? null : (int) $expense->project_id]));
            $this->modelRetraining->afterDataChange($projectIds);

            return response()->json($this->mapExpense($expense));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Delete the selected fin expense record.
    public function destroy($id)
    {
        // Try this action and handle any errors below.
        try {
            $existing = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->first();
            // Return early if the existing is missing or false.
            if (! $existing) {
                return response()->json(['error' => 'Expense not found'], 404);
            }
            // Return early if the inventory transaction id is not null.
            if (($existing->inventory_transaction_id ?? null) !== null) {
                return response()->json(['error' => 'A linked inventory purchase cannot be deleted from Finance.'], 409);
            }

            // Run this step if the proof file path is set.
            if ($existing->proof_file_path) {
                app(\App\Services\TrashService::class)->retainFile($existing->proof_file_path);
            }

            DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->delete();
            // Run this step if the project id is not null.
            if ($existing->project_id !== null) {
                $this->projectCosts->syncBudget((int) $existing->project_id);
            }
            $deleted = new FinExpense();
            $deleted->setRawAttributes((array) $existing, true);
            $this->audit->record($deleted, 'DELETE', (array) $existing, []);
            $this->modelRetraining->afterDataChange($existing->project_id === null ? [] : [(int) $existing->project_id]);

            return response()->json(['success' => true, 'message' => 'Expense deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Get the duplicate expense.
    private function duplicateExpense(array $data, ?int $ignoreId = null): bool
    {
        $query = DB::table('fin_expense_tbl')
            ->where('fin_category_id', $data['fin_category_id'])
            ->where('amount', $data['amount'])
            ->whereDate('expense_date', $data['expense_date'])
            ->whereRaw('LOWER(TRIM(expense_description)) = ?', [strtolower(trim($data['expense_description']))]);
        $query = empty($data['project_id']) ? $query->whereNull('project_id') : $query->where('project_id', $data['project_id']);
        $query = empty($data['project_cost_component']) ? $query->whereNull('project_cost_component') : $query->where('project_cost_component', $data['project_cost_component']);
        $query = empty($data['entry_kind']) ? $query->whereNull('entry_kind') : $query->where('entry_kind', $data['entry_kind']);
        // Run this step if the ignore id is not null.
        if ($ignoreId !== null) {
            $query->where('fin_expense_id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    // Get the project component errors.
    private function projectComponentErrors(array $data): array
    {
        $classification = DB::table('fin_expense_category_tbl')
            ->where('fin_category_id', $data['fin_category_id'] ?? null)
            ->value('classification');
        $isDirect = strtolower((string) $classification) === 'direct';
        $hasProject = ! blank($data['project_id'] ?? null);
        $errors = [];

        $isOffice = ! empty($data['office_expense']);
        // Run this step if the is office and has project checks match.
        if ($isOffice && $hasProject) {
            $errors['project_id'][] = 'Office expenses cannot be linked to a project.';
        }
        // Run this step if the is direct and has project checks match.
        if ($isDirect && ! $hasProject && ! $isOffice) {
            $errors['project_id'][] = 'A direct project expense requires a valid project.';
        }
        // Run this step if the classification and has project checks match.
        if (in_array(strtolower((string) $classification), ['admin', 'office'], true) && $hasProject) {
            $errors['project_id'][] = 'Admin and office expenses cannot be linked to a project.';
        }

        return $errors;
    }

    // Clean up cost component.
    private function normalizeCostComponent(?string $component): ?string
    {
        $component = strtolower(trim((string) $component));

        return in_array($component, self::PROJECT_COST_COMPONENTS, true) ? $component : null;
    }

    // Get the cost component label.
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

    // Get the inventory expense description.
    private function inventoryExpenseDescription(object $transaction): string
    {
        $quantity = rtrim(rtrim(number_format((float) $transaction->quantity, 2, '.', ''), '0'), '.');

        return "Purchased {$quantity} ".strtolower((string) $transaction->unit_name)." of {$transaction->item_name}";
    }

    // Get the inventory expense remarks.
    private function inventoryExpenseRemarks($barCode): string
    {
        $remarks = 'Inventory stock-in transaction.';

        return $barCode === null || $barCode === '' ? $remarks : $remarks.' Receiving reference: '.$barCode;
    }
}
