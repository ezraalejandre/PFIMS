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
            'period_month' => 'required|date',
            'balance_amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = FinCashPosition::where('account_id', $request->account_id)
            ->where('period_month', $request->period_month)
            ->first();

        if ($existing) {
            $existing->update(['balance_amount' => $request->balance_amount]);
            return response()->json($existing);
        }

        $cash = FinCashPosition::create($request->all());
        return response()->json($cash, 201);
    }

    public function update(Request $request, $id)
    {
        $cash = FinCashPosition::find($id);
        if (!$cash) {
            return response()->json(['message' => 'Cash position not found'], 404);
        }

        $cash->update($request->all());
        return response()->json($cash);
    }

    public function destroy($id)
    {
        $cash = FinCashPosition::find($id);
        if (!$cash) {
            return response()->json(['message' => 'Cash position not found'], 404);
        }

        $cash->delete();
        return response()->json(['message' => 'Cash position deleted successfully']);
    }
}