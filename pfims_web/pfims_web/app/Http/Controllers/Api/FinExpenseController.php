<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\NotificationService;

class FinExpenseController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    private function mapExpense($item)
    {
        return [
            'fin_expense_id' => $item->fin_expense_id,
            'expense_id' => $item->fin_expense_id,
            'inventory_transaction_id' => $item->inventory_transaction_id ?? null,
            'is_pending_inventory' => false,
            'project_id' => $item->project_id,
            'project_name' => $item->project_name,
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

    public function index()
    {
        try {
            $expenses = DB::table('fin_expense_tbl')
                ->leftJoin('project_tbl', 'fin_expense_tbl.project_id', '=', 'project_tbl.project_id')
                ->leftJoin('fin_expense_category_tbl', 'fin_expense_tbl.fin_category_id', '=', 'fin_expense_category_tbl.fin_category_id')
                ->select(
                    'fin_expense_tbl.fin_expense_id',
                    'fin_expense_tbl.project_id',
                    'fin_expense_tbl.inventory_transaction_id',
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
                ->orderByDesc('fin_expense_tbl.expense_date')
                ->get();

            $mapped = $expenses->map(function ($item) {
                return $this->mapExpense($item);
            });

            $constructionSupply = DB::table('fin_expense_category_tbl')
                ->where('category_name', 'Construction Supply')
                ->first();

            if ($constructionSupply) {
                $pending = DB::table('inventory_transaction_tbl as transaction')
                    ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
                    ->leftJoin('project_tbl as project', 'project.project_id', '=', 'transaction.project_id')
                    ->leftJoin('fin_expense_tbl as expense', 'expense.inventory_transaction_id', '=', 'transaction.inventory_transaction_id')
                    ->where('transaction.transaction_type', 'IN')
                    ->whereNull('expense.fin_expense_id')
                    ->select(
                        'transaction.inventory_transaction_id',
                        'transaction.project_id',
                        'project.project_name',
                        'transaction.transaction_date',
                        'item.item_name'
                    )
                    ->get()
                    ->map(function ($transaction) use ($constructionSupply) {
                        return [
                            'fin_expense_id' => null,
                            'expense_id' => null,
                            'inventory_transaction_id' => $transaction->inventory_transaction_id,
                            'is_pending_inventory' => true,
                            'project_id' => $transaction->project_id,
                            'project_name' => $transaction->project_name,
                            'expense_description' => 'Stock-in: ' . $transaction->item_name,
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
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $id = DB::transaction(function () use ($request, $transactionId) {
                $transaction = DB::table('inventory_transaction_tbl as transaction')
                    ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
                    ->where('transaction.inventory_transaction_id', $transactionId)
                    ->lockForUpdate()
                    ->select('transaction.*', 'item.item_name')
                    ->first();

                if (!$transaction || $transaction->transaction_type !== 'IN') {
                    abort(404, 'Stock-in transaction not found.');
                }

                if (DB::table('fin_expense_tbl')->where('inventory_transaction_id', $transactionId)->exists()) {
                    abort(409, 'An expense already exists for this stock-in transaction.');
                }

                $category = DB::table('fin_expense_category_tbl')
                    ->where('category_name', 'Construction Supply')
                    ->where('is_active', true)
                    ->first();

                if (!$category) {
                    abort(422, 'The Construction Supply finance category is unavailable.');
                }

                return DB::table('fin_expense_tbl')->insertGetId([
                    'project_id' => $transaction->project_id,
                    'fin_category_id' => $category->fin_category_id,
                    'inventory_transaction_id' => $transactionId,
                    'expense_description' => 'Stock-in: ' . $transaction->item_name,
                    'amount' => $request->amount,
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
                'expense_description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'expense_date' => 'required|date',
                'remarks' => 'nullable|string',
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = [
                'project_id' => $request->project_id,
                'fin_category_id' => $request->fin_category_id,
                'expense_description' => $request->expense_description,
                'amount' => $request->amount,
                'expense_date' => $request->expense_date,
                'remarks' => $request->remarks,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($request->hasFile('proof_file')) {
                $data['proof_file_path'] = $request->file('proof_file')->store('proofs', 'public');
                $data['proof_file_name'] = $request->file('proof_file')->getClientOriginalName();
            }

            $id = DB::table('fin_expense_tbl')->insertGetId($data);
            $expense = $this->findWithJoins($id);

            // Send notification
            $category = $expense->category_name ?? 'Expense';
            $project = $expense->project_name ? " for {$expense->project_name}" : '';
            $formatted = 'PHP ' . number_format((float) $expense->amount, 2);

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
            if (!$existing) {
                return response()->json(['error' => 'Expense not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'expense_description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'expense_date' => 'required|date',
                'remarks' => 'nullable|string',
                'proof_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = [
                'project_id' => $request->project_id,
                'fin_category_id' => $request->fin_category_id,
                'expense_description' => $request->expense_description,
                'amount' => $request->amount,
                'expense_date' => $request->expense_date,
                'remarks' => $request->remarks,
                'updated_at' => now(),
            ];

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
            if (!$existing) {
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
}
