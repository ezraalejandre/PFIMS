<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinEquipmentExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Group the fin equipment expense actions.
class FinEquipmentExpenseController extends Controller
{
    // List the fin equipment expense records.
    public function index()
    {
        return response()->json(FinEquipmentExpense::with(['asset:asset_id,asset_name', 'project:project_id,project_name'])->get());
    }

    // Save a new fin equipment expense record.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:company_asset_tbl,asset_id',
            'project_id' => 'nullable|exists:project_tbl,project_id',
            'expense_type' => 'required|in:gas_diesel,payroll_operator,repair,delivery,transportation,other',
            'amount' => 'required|numeric|gt:0|max:999999999999.99',
            'expense_date' => 'required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:255',
        ]);

        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $this->normalize($validator->validated());
        // Return early if a matching record already exists.
        if ($this->isDuplicate($data)) {
            return response()->json(['message' => 'This equipment expense already exists.'], 409);
        }
        $expense = FinEquipmentExpense::create($data);

        return response()->json($expense, 201);
    }

    // Save changes to the fin equipment expense record.
    public function update(Request $request, $id)
    {
        $expense = FinEquipmentExpense::find($id);
        // Return early if the expense is missing or false.
        if (! $expense) {
            return response()->json(['message' => 'Equipment expense not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'asset_id' => 'sometimes|required|exists:company_asset_tbl,asset_id',
            'project_id' => 'required|exists:project_tbl,project_id',
            'expense_type' => 'sometimes|required|in:gas_diesel,payroll_operator,repair,delivery,transportation,other',
            'amount' => 'sometimes|required|numeric|gt:0|max:999999999999.99',
            'expense_date' => 'sometimes|required|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:255',
        ]);
        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // Return early if the filled and expense date checks match.
        if ($request->filled('expense_date') && \Carbon\Carbon::parse($request->expense_date)->format('Y-m') !== $expense->expense_date->format('Y-m')) {
            return response()->json(['message' => 'The expense date does not match the rental period.'], 422);
        }
        $data = $this->normalize($validator->validated());
        $candidate = array_merge($expense->only($expense->getFillable()), $data);
        // Return early if a matching record already exists.
        if ($this->isDuplicate($candidate, (int) $id)) {
            return response()->json(['message' => 'This equipment expense already exists.'], 409);
        }
        $expense->update($data);

        return response()->json($expense);
    }

    // Delete the selected fin equipment expense record.
    public function destroy($id)
    {
        $expense = FinEquipmentExpense::find($id);
        // Return early if the expense is missing or false.
        if (! $expense) {
            return response()->json(['message' => 'Equipment expense not found'], 404);
        }

        $expense->delete();

        return response()->json(['message' => 'Equipment expense deleted successfully']);
    }

    // Clean up the supplied values.
    private function normalize(array $data): array
    {
        // Run this step if the data check matches.
        if (array_key_exists('remarks', $data)) {
            $data['remarks'] = blank($data['remarks']) ? null : trim($data['remarks']);
        }

        return $data;
    }

    // Check the duplicate condition.
    private function isDuplicate(array $data, ?int $ignoreId = null): bool
    {
        $query = DB::table('fin_equipment_expense_tbl')
            ->where('asset_id', $data['asset_id'])
            ->where('expense_type', $data['expense_type'])
            ->where('amount', $data['amount'])
            ->whereDate('expense_date', $data['expense_date']);
        $query = empty($data['project_id']) ? $query->whereNull('project_id') : $query->where('project_id', $data['project_id']);
        // Run this step if the ignore id is not null.
        if ($ignoreId !== null) {
            $query->where('equip_expense_id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
