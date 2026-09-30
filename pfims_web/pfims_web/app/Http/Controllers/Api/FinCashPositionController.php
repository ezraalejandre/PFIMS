<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyBankAccount;
use App\Models\FinCashPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinCashPositionController extends Controller
{
    public function accounts()
    {
        return response()->json(
            CompanyBankAccount::query()
                ->whereNot(function ($query) {
                    $query->where('account_type', 'cash_on_hand_field')
                        ->whereRaw('LOWER(account_name) = ?', ['site revolving fund']);
                })
                ->select(['account_id', 'account_name', 'account_type'])
                ->orderBy('account_name')
                ->get()
        );
    }

    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:100'],
        ]);
        $name = trim($data['account_name']);
        if ($name === '') {
            return response()->json(['message' => 'Account title is required.'], 422);
        }
        if (CompanyBankAccount::query()->whereRaw('LOWER(account_name) = ?', [mb_strtolower($name)])->exists()) {
            return response()->json(['message' => 'An account with this title already exists.'], 409);
        }

        $account = CompanyBankAccount::create(['account_name' => $name, 'account_type' => 'treasury']);

        return response()->json($account->only(['account_id', 'account_name', 'account_type']), 201);
    }

    public function index()
    {
        return response()->json(FinCashPosition::with('account:account_id,account_name')
            ->whereHas('account', function ($query) {
                $query->whereNot(function ($hidden) {
                    $hidden->where('account_type', 'cash_on_hand_field')
                        ->whereRaw('LOWER(account_name) = ?', ['site revolving fund']);
                });
            })->get());
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
        if ($this->isHiddenAccount((int) $data['account_id'])) {
            return response()->json(['message' => 'This account is not available in Cash Asset.'], 422);
        }
        $existing = FinCashPosition::where('account_id', $data['account_id'])
            ->where('period_month', $data['period_month'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A Cash Asset record already exists for this account and month. Edit the existing record instead.'], 409);
        }

        $cash = FinCashPosition::create($data);

        return response()->json($cash, 201);
    }

    public function update(Request $request, $id)
    {
        $cash = FinCashPosition::find($id);
        if (! $cash) {
            return response()->json(['message' => 'Cash Asset record not found'], 404);
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
        if ($this->isHiddenAccount((int) ($data['account_id'] ?? $cash->account_id))) {
            return response()->json(['message' => 'This account is not available in Cash Asset.'], 422);
        }
        $candidate = array_merge($cash->only($cash->getFillable()), $data);
        $duplicate = FinCashPosition::where('account_id', $candidate['account_id'])
            ->where('period_month', $candidate['period_month'])
            ->where('cash_position_id', '!=', $id)
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'A Cash Asset record already exists for this account and month.'], 409);
        }
        $cash->update($data);

        return response()->json($cash);
    }

    public function destroy($id)
    {
        $cash = FinCashPosition::find($id);
        if (! $cash) {
            return response()->json(['message' => 'Cash Asset record not found'], 404);
        }

        $cash->delete();

        return response()->json(['message' => 'Cash Asset record deleted successfully']);
    }

    private function isHiddenAccount(int $accountId): bool
    {
        return CompanyBankAccount::query()->whereKey($accountId)
            ->where('account_type', 'cash_on_hand_field')
            ->whereRaw('LOWER(account_name) = ?', ['site revolving fund'])->exists();
    }
}
