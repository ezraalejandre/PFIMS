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
                'original_contract_price' => 'nullable|numeric|min:0',
                'additional_works_contract' => 'nullable|numeric|min:0',
                'original_payment_received' => 'nullable|numeric|min:0',
                'additional_works_payment' => 'nullable|numeric|min:0',
                'remarks' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            // Check if contract already exists for this project
            $existing = DB::table('fin_project_contract_tbl')
                ->where('project_id', $request->project_id)
                ->first();
            
            if ($existing) {
                // Update existing
                DB::table('fin_project_contract_tbl')
                    ->where('project_id', $request->project_id)
                    ->update([
                        'additional_works_contract' => $request->additional_works_contract ?? 0,
                        'original_payment_received' => $request->original_payment_received ?? 0,
                        'additional_works_payment' => $request->additional_works_payment ?? 0,
                        'remarks' => $request->remarks,
                    ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contract updated successfully',
                    'contract_id' => $existing->contract_id
                ]);
            }
            
            $id = DB::table('fin_project_contract_tbl')->insertGetId([
                'project_id' => $request->project_id,
                'original_contract_price' => $request->original_contract_price ?? 0,
                'additional_works_contract' => $request->additional_works_contract ?? 0,
                'original_payment_received' => $request->original_payment_received ?? 0,
                'additional_works_payment' => $request->additional_works_payment ?? 0,
                'remarks' => $request->remarks,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Contract created successfully',
                'contract_id' => $id
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function update(Request $request, $id)
    {
        try {
            $exists = DB::table('fin_project_contract_tbl')->where('contract_id', $id)->exists();
            if (!$exists) {
                return response()->json(['error' => 'Contract not found'], 404);
            }
            
            $validator = Validator::make($request->all(), [
                'original_contract_price' => 'nullable|numeric|min:0',
                'additional_works_contract' => 'nullable|numeric|min:0',
                'original_payment_received' => 'nullable|numeric|min:0',
                'additional_works_payment' => 'nullable|numeric|min:0',
                'remarks' => 'nullable|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            DB::table('fin_project_contract_tbl')
                ->where('contract_id', $id)
                ->update([
                    'additional_works_contract' => $request->additional_works_contract ?? 0,
                    'original_payment_received' => $request->original_payment_received ?? 0,
                    'additional_works_payment' => $request->additional_works_payment ?? 0,
                    'remarks' => $request->remarks,
                ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Contract updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $exists = DB::table('fin_project_contract_tbl')->where('contract_id', $id)->exists();
            if (!$exists) {
                return response()->json(['error' => 'Contract not found'], 404);
            }
            
            DB::table('fin_project_contract_tbl')->where('contract_id', $id)->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Contract deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}