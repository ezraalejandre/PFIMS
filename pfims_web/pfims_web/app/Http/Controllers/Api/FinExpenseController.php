<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinExpenseController extends Controller
{
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
                    'fin_expense_tbl.amount',
                    'fin_expense_tbl.expense_date',
                    'fin_expense_tbl.remarks'
                )
                ->orderByDesc('fin_expense_tbl.expense_date')
                ->get();
            
            // Map to match frontend expectations
            return response()->json($expenses->map(function($item) {
                return [
                    'fin_expense_id' => $item->fin_expense_id,
                    'expense_id' => $item->fin_expense_id,
                    'project_id' => $item->project_id,
                    'project_name' => $item->project_name,
                    'expense_description' => $item->remarks ?? $item->category_name ?? 'Expense',
                    'fin_category_id' => $item->fin_category_id,
                    'expense_category_id' => $item->fin_category_id,
                    'category_name' => $item->category_name,
                    'amount' => $item->amount,
                    'expense_date' => $item->expense_date,
                    'remarks' => $item->remarks,
                ];
            }));
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
    
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'amount' => 'required|numeric|min:0.01',
                'expense_date' => 'required|date',
                'remarks' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            $id = DB::table('fin_expense_tbl')->insertGetId([
                'project_id' => $request->project_id,
                'fin_category_id' => $request->fin_category_id,
                'amount' => $request->amount,
                'expense_date' => $request->expense_date,
                'remarks' => $request->remarks,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Expense created successfully',
                'fin_expense_id' => $id,
                'expense_id' => $id
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        try {
            $exists = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->exists();
            if (!$exists) {
                return response()->json(['error' => 'Expense not found'], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'project_id' => 'nullable|exists:project_tbl,project_id',
                'fin_category_id' => 'required|exists:fin_expense_category_tbl,fin_category_id',
                'amount' => 'required|numeric|min:0.01',
                'expense_date' => 'required|date',
                'remarks' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            DB::table('fin_expense_tbl')
                ->where('fin_expense_id', $id)
                ->update([
                    'project_id' => $request->project_id,
                    'fin_category_id' => $request->fin_category_id,
                    'amount' => $request->amount,
                    'expense_date' => $request->expense_date,
                    'remarks' => $request->remarks,
                    'updated_at' => now(),
                ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $exists = DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->exists();
            if (!$exists) {
                return response()->json(['error' => 'Expense not found'], 404);
            }
            
            DB::table('fin_expense_tbl')->where('fin_expense_id', $id)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}