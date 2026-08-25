<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinProjectContractController extends Controller
{
    public function index()
    {
        try {
            // Get contracts with budget data from budgets_tbl
            $contracts = DB::table('fin_project_contract_tbl as c')
                ->rightJoin('project_tbl as p', 'c.project_id', '=', 'p.project_id')
                ->leftJoin('budgets_tbl as b', 'p.project_id', '=', 'b.project_id')
                ->select(
                    'p.project_id',
                    'p.project_name',
                    'p.start_date',
                    'p.actual_end_date',
                    DB::raw('COALESCE(c.contract_id, 0) as contract_id'),
                    // Use budget as contract price if available, otherwise use contract table value
                    DB::raw('COALESCE(b.budget_amount, c.original_contract_price, 0) as original_contract_price'),
                    DB::raw('COALESCE(c.additional_works_contract, 0) as additional_works_contract'),
                    DB::raw('COALESCE(c.original_payment_received, 0) as original_payment_received'),
                    DB::raw('COALESCE(c.additional_works_payment, 0) as additional_works_payment'),
                    'c.remarks'
                )
                ->get();

            return response()->json($contracts);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'project_id' => 'required|exists:project_tbl,project_id',
                'original_contract_price' => 'nullable|numeric|min:0|max:99999999999999.99',
                'additional_works_contract' => 'nullable|numeric|min:0|max:99999999999999.99',
                'original_payment_received' => 'nullable|numeric|min:0|max:99999999999999.99',
                'additional_works_payment' => 'nullable|numeric|min:0|max:99999999999999.99',
                'remarks' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Check if contract already exists for this project
            $validated = $validator->validated();
            $existing = DB::table('fin_project_contract_tbl')
                ->where('project_id', $validated['project_id'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'A contract already exists for this project. Edit the existing record instead.',
                    'contract_id' => $existing->contract_id,
                ], 409);
            }

            $id = DB::table('fin_project_contract_tbl')->insertGetId([
                'project_id' => $validated['project_id'],
                'original_contract_price' => $validated['original_contract_price'] ?? 0,
                'additional_works_contract' => $validated['additional_works_contract'] ?? 0,
                'original_payment_received' => $validated['original_payment_received'] ?? 0,
                'additional_works_payment' => $validated['additional_works_payment'] ?? 0,
                'remarks' => blank($validated['remarks'] ?? null) ? null : trim($validated['remarks']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract created successfully',
                'contract_id' => $id,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $exists = DB::table('fin_project_contract_tbl')->where('contract_id', $id)->exists();
            if (! $exists) {
                return response()->json(['error' => 'Contract not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'original_contract_price' => 'nullable|numeric|min:0|max:99999999999999.99',
                'additional_works_contract' => 'nullable|numeric|min:0|max:99999999999999.99',
                'original_payment_received' => 'nullable|numeric|min:0|max:99999999999999.99',
                'additional_works_payment' => 'nullable|numeric|min:0|max:99999999999999.99',
                'remarks' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            DB::table('fin_project_contract_tbl')
                ->where('contract_id', $id)
                ->update([
                    'original_contract_price' => $validated['original_contract_price'] ?? 0,
                    'additional_works_contract' => $validated['additional_works_contract'] ?? 0,
                    'original_payment_received' => $validated['original_payment_received'] ?? 0,
                    'additional_works_payment' => $validated['additional_works_payment'] ?? 0,
                    'remarks' => blank($validated['remarks'] ?? null) ? null : trim($validated['remarks']),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $exists = DB::table('fin_project_contract_tbl')->where('contract_id', $id)->exists();
            if (! $exists) {
                return response()->json(['error' => 'Contract not found'], 404);
            }

            DB::table('fin_project_contract_tbl')->where('contract_id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contract deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
