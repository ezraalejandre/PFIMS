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

            return response()->json($expenses->map(function ($item) {
                return $this->mapExpense($item);
            }));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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