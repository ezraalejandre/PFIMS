<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinEquipmentRentalIncome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinEquipmentRentalIncomeController extends Controller
{
    public function index()
    {
        return response()->json(FinEquipmentRentalIncome::with(['asset:asset_id,asset_name', 'project:project_id,project_name'])->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:company_asset_tbl,asset_id',
            'project_id' => 'nullable|exists:project_tbl,project_id',
            'period_month' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = FinEquipmentRentalIncome::where('asset_id', $request->asset_id)
            ->where('project_id', $request->project_id)
            ->where('period_month', $request->period_month)
            ->first();

        if ($existing) {
            $existing->update(['amount' => $request->amount, 'remarks' => $request->remarks]);
            return response()->json($existing);
        }

        $rental = FinEquipmentRentalIncome::create($request->all());
        return response()->json($rental, 201);
    }

    public function update(Request $request, $id)
    {
        $rental = FinEquipmentRentalIncome::find($id);
        if (!$rental) {
            return response()->json(['message' => 'Rental income not found'], 404);
        }

        $rental->update($request->all());
        return response()->json($rental);
    }

    public function destroy($id)
    {
        $rental = FinEquipmentRentalIncome::find($id);
        if (!$rental) {
            return response()->json(['message' => 'Rental income not found'], 404);
        }

        $rental->delete();
        return response()->json(['message' => 'Rental income deleted successfully']);
    }
}