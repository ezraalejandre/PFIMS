<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinCashPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinCashPositionController extends Controller
{
    public function index()
    {
        return response()->json(FinCashPosition::with('account:account_id,account_name')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:company_bank_account_tbl,account_id',
            'period_month' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'regex:/^\d{4}-\d{2}-01$/'],
            'balance_amount' => 'required|numeric|min:0|max:99999999999999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $existing = FinCashPosition::where('account_id', $data['account_id'])
            ->where('period_month', $data['period_month'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A cash position already exists for this account and month. Edit the existing record instead.'], 409);
        }

        $cash = FinCashPosition::create($data);

        return response()->json($cash, 201);
    }

    public function update(Request $request, $id)
    {
        $cash = FinCashPosition::find($id);
        if (! $cash) {
            return response()->json(['message' => 'Cash position not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_id' => 'sometimes|required|exists:company_bank_account_tbl,account_id',
            'period_month' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today', 'regex:/^\d{4}-\d{2}-01$/'],
            'balance_amount' => 'sometimes|required|numeric|min:0|max:99999999999999.99',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        $candidate = array_merge($cash->only($cash->getFillable()), $data);
        $duplicate = FinCashPosition::where('account_id', $candidate['account_id'])
            ->where('period_month', $candidate['period_month'])
            ->where('cash_position_id', '!=', $id)
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'A cash position already exists for this account and month.'], 409);
        }
        $cash->update($data);

        return response()->json($cash);
    }

    public function destroy($id)
    {
        $cash = FinCashPosition::find($id);
        if (! $cash) {
            return response()->json(['message' => 'Cash position not found'], 404);
        }

        $cash->delete();

        return response()->json(['message' => 'Cash position deleted successfully']);
    }
}
