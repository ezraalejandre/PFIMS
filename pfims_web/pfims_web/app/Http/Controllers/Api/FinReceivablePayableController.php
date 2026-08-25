<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinReceivablePayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinReceivablePayableController extends Controller
{
    public function index()
    {
        return response()->json(FinReceivablePayable::with('project:project_id,project_name')->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entry_type' => 'required|in:accounts_receivable,accounts_payable,cash_advance_site,advance_employee',
            'project_id' => 'nullable|exists:project_tbl,project_id',
            'counterparty_name' => 'required|string|max:150',
            'entry_date' => 'required|date',
            'amount_30d' => 'nullable|numeric|min:0|max:999999999999.99',
            'amount_31_60d' => 'nullable|numeric|min:0|max:999999999999.99',
            'amount_61_90d' => 'nullable|numeric|min:0|max:999999999999.99',
            'amount_91_120d' => 'nullable|numeric|min:0|max:999999999999.99',
            'status' => 'sometimes|required|in:outstanding,settled',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ensure all amount fields are set with default 0
        $data = $validator->validated();
        $data['counterparty_name'] = trim($data['counterparty_name']);
        $data['amount_30d'] = $data['amount_30d'] ?? 0;
        $data['amount_31_60d'] = $data['amount_31_60d'] ?? 0;
        $data['amount_61_90d'] = $data['amount_61_90d'] ?? 0;
        $data['amount_91_120d'] = $data['amount_91_120d'] ?? 0;
        $data['status'] = $data['status'] ?? 'outstanding';
        $data['remarks'] = blank($data['remarks'] ?? null) ? null : trim($data['remarks']);
        if ($this->isDuplicate($data)) {
            return response()->json(['message' => 'This receivable/payable entry already exists.'], 409);
        }

        $rp = FinReceivablePayable::create($data);

        return response()->json($rp, 201);
    }

    public function update(Request $request, $id)
    {
        $rp = FinReceivablePayable::find($id);
        if (! $rp) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'entry_type' => 'sometimes|required|in:accounts_receivable,accounts_payable,cash_advance_site,advance_employee',
            'project_id' => 'nullable|exists:project_tbl,project_id',
            'counterparty_name' => 'sometimes|required|string|max:150',
            'entry_date' => 'sometimes|required|date',
            'amount_30d' => 'nullable|numeric|min:0|max:999999999999.99',
            'amount_31_60d' => 'nullable|numeric|min:0|max:999999999999.99',
            'amount_61_90d' => 'nullable|numeric|min:0|max:999999999999.99',
            'amount_91_120d' => 'nullable|numeric|min:0|max:999999999999.99',
            'status' => 'sometimes|required|in:outstanding,settled',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        if (isset($data['counterparty_name'])) {
            $data['counterparty_name'] = trim($data['counterparty_name']);
        }
        if (array_key_exists('remarks', $data)) {
            $data['remarks'] = blank($data['remarks']) ? null : trim($data['remarks']);
        }
        $candidate = array_merge($rp->only($rp->getFillable()), $data);
        if ($this->isDuplicate($candidate, (int) $id)) {
            return response()->json(['message' => 'This receivable/payable entry already exists.'], 409);
        }

        $rp->update($data);

        return response()->json($rp);
    }

    public function destroy($id)
    {
        $rp = FinReceivablePayable::find($id);
        if (! $rp) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $rp->delete();

        return response()->json(['message' => 'Record deleted successfully']);
    }

    private function isDuplicate(array $data, ?int $ignoreId = null): bool
    {
        $query = DB::table('fin_receivable_payable_tbl')
            ->where('entry_type', $data['entry_type'])
            ->whereDate('entry_date', $data['entry_date'])
            ->whereRaw('LOWER(TRIM(counterparty_name)) = ?', [strtolower(trim($data['counterparty_name']))]);
        foreach (['amount_30d', 'amount_31_60d', 'amount_61_90d', 'amount_91_120d'] as $field) {
            $query->where($field, $data[$field] ?? 0);
        }
        $query = empty($data['project_id']) ? $query->whereNull('project_id') : $query->where('project_id', $data['project_id']);
        if ($ignoreId !== null) {
            $query->where('rp_id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
