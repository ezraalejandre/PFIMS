<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinEquipmentExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinEquipmentExpenseController extends Controller
{
    public function index()
    {
        return response()->json(FinEquipmentExpense::with(['asset:asset_id,asset_name', 'project:project_id,project_name'])->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:company_asset_tbl,asset_id',
            'project_id' => 'nullable|exists:project_tbl,project_id',
            'expense_type' => 'required|in:gas_diesel,payroll_operator,repair,delivery,transportation,other',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $expense = FinEquipmentExpense::create($request->all());
        return response()->json($expense, 201);
    }

    public function update(Request $request, $id)
    {
        $expense = FinEquipmentExpense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Equipment expense not found'], 404);
        }

        $expense->update($request->all());
        return response()->json($expense);
    }

    public function destroy($id)
    {
        $expense = FinEquipmentExpense::find($id);
        if (!$expense) {
            return response()->json(['message' => 'Equipment expense not found'], 404);
        }

        $expense->delete();
        return response()->json(['message' => 'Equipment expense deleted successfully']);
    }
}