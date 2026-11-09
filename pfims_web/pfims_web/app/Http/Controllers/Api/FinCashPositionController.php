<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyBankAccount;
use App\Models\FinCashPosition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// Group the fin cash position actions.
class FinCashPositionController extends Controller
{
    // Get the accounts.
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

    // Get the store account.
    public function storeAccount(Request $request)
    {
        $data = $request->validate([
            'account_name' => ['required', 'string', 'max:100'],
        ]);
        $name = trim($data['account_name']);
        // Return early if the name is empty.
        if ($name === '') {
            return response()->json(['message' => 'Account title is required.'], 422);
        }
        // Return early if the exists check matches.
        if (CompanyBankAccount::query()->whereRaw('LOWER(account_name) = ?', [mb_strtolower($name)])->exists()) {
            return response()->json(['message' => 'An account with this title already exists.'], 409);
        }

        $account = CompanyBankAccount::create(['account_name' => $name, 'account_type' => 'treasury']);

        return response()->json($account->only(['account_id', 'account_name', 'account_type']), 201);
    }

    // List the fin cash position records.
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

    // Update account.
    public function updateAccount(Request $request, int $id)
    {
        $account = CompanyBankAccount::findOrFail($id);
        abort_if($this->isHiddenAccount($id), 404);
        $data = $request->validate(['account_name' => ['required', 'string', 'max:100']]);
        $name = trim($data['account_name']);
        abort_if($name === '', 422, 'Account title is required.');
        abort_if(CompanyBankAccount::whereKeyNot($id)->whereRaw('LOWER(TRIM(account_name)) = ?', [mb_strtolower($name)])->exists(), 409, 'An account with this title already exists.');
        $account->update(['account_name' => $name]);
        return response()->json($account);
    }

    // Get the destroy account.
    public function destroyAccount(int $id)
    {
        $account = CompanyBankAccount::findOrFail($id);
        abort_if($this->isHiddenAccount($id), 404);
        abort_if($account->cashPositions()->exists(), 409, 'This account is used by Cash Asset records. Remove those records before deleting the account.');
        $account->delete();
        return response()->json(['success' => true, 'message' => 'Account deleted.']);
    }

    // Save a new fin cash position record.
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:company_bank_account_tbl,account_id',
            'period_month' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'regex:/^\d{4}-\d{2}-01$/'],
            'balance_amount' => 'required|numeric|min:0|max:99999999999999.99',
        ]);

        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        // Return early if the data account id check matches.
        if ($this->isHiddenAccount((int) $data['account_id'])) {
            return response()->json(['message' => 'This account is not available in Cash Asset.'], 422);
        }
        $existing = FinCashPosition::where('account_id', $data['account_id'])
            ->where('period_month', $data['period_month'])
            ->first();

        // Return early if the existing is set.
        if ($existing) {
            return response()->json(['message' => 'A Cash Asset record already exists for this account and month. Edit the existing record instead.'], 409);
        }

        $cash = FinCashPosition::create($data);

        return response()->json($cash, 201);
    }

    // Save changes to the fin cash position record.
    public function update(Request $request, $id)
    {
        $cash = FinCashPosition::find($id);
        // Return early if the cash is missing or false.
        if (! $cash) {
            return response()->json(['message' => 'Cash Asset record not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_id' => 'sometimes|required|exists:company_bank_account_tbl,account_id',
            'period_month' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today', 'regex:/^\d{4}-\d{2}-01$/'],
            'balance_amount' => 'sometimes|required|numeric|min:0|max:99999999999999.99',
        ]);
        // Return early if the submitted fields fail validation.
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        // Return early if the account id account id check matches.
        if ($this->isHiddenAccount((int) ($data['account_id'] ?? $cash->account_id))) {
            return response()->json(['message' => 'This account is not available in Cash Asset.'], 422);
        }
        $candidate = array_merge($cash->only($cash->getFillable()), $data);
        $duplicate = FinCashPosition::where('account_id', $candidate['account_id'])
            ->where('period_month', $candidate['period_month'])
            ->where('cash_position_id', '!=', $id)
            ->exists();
        // Return early if the duplicate is set.
        if ($duplicate) {
            return response()->json(['message' => 'A Cash Asset record already exists for this account and month.'], 409);
        }
        $cash->update($data);

        return response()->json($cash);
    }

    // Delete the selected fin cash position record.
    public function destroy($id)
    {
        $cash = FinCashPosition::find($id);
        // Return early if the cash is missing or false.
        if (! $cash) {
            return response()->json(['message' => 'Cash Asset record not found'], 404);
        }

        $cash->delete();

        return response()->json(['message' => 'Cash Asset record deleted successfully']);
    }

    // Check the hidden account condition.
    private function isHiddenAccount(int $accountId): bool
    {
        return CompanyBankAccount::query()->whereKey($accountId)
            ->where('account_type', 'cash_on_hand_field')
            ->whereRaw('LOWER(account_name) = ?', ['site revolving fund'])->exists();
    }
}
