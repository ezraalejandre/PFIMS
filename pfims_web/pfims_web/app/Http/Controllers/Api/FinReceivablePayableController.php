<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinReceivablePayable;
use Illuminate\Http\Request;
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
            'amount_30d' => 'nullable|numeric|min:0',
            'amount_31_60d' => 'nullable|numeric|min:0',
            'amount_61_90d' => 'nullable|numeric|min:0',
            'amount_91_120d' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:outstanding,settled',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $rp = FinReceivablePayable::create($request->all());
        return response()->json($rp, 201);
    }

    public function update(Request $request, $id)
    {
        $rp = FinReceivablePayable::find($id);
        if (!$rp) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $rp->update($request->all());
        return response()->json($rp);
    }

    public function destroy($id)
    {
        $rp = FinReceivablePayable::find($id);
        if (!$rp) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $rp->delete();
        return response()->json(['message' => 'Record deleted successfully']);
    }
}