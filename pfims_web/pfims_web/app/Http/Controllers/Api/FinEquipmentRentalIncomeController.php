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
            'period_month' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'regex:/^\d{4}-\d{2}-01$/'],
            'amount' => 'required|numeric|gt:0|max:999999999999.99',
            'remarks' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['remarks'] = blank($data['remarks'] ?? null) ? null : trim($data['remarks']);
        $existing = FinEquipmentRentalIncome::where('asset_id', $data['asset_id'])
            ->where('project_id', $data['project_id'] ?? null)
            ->where('period_month', $data['period_month'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Rental income already exists for this asset, project, and month. Edit the existing record instead.'], 409);
        }

        $rental = FinEquipmentRentalIncome::create($data);

        return response()->json($rental, 201);
    }

    public function update(Request $request, $id)
    {
        $rental = FinEquipmentRentalIncome::find($id);
        if (! $rental) {
            return response()->json(['message' => 'Rental income not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'asset_id' => 'sometimes|required|exists:company_asset_tbl,asset_id',
            'project_id' => 'nullable|exists:project_tbl,project_id',
            'period_month' => ['sometimes', 'required', 'date_format:Y-m-d', 'before_or_equal:today', 'regex:/^\d{4}-\d{2}-01$/'],
            'amount' => 'sometimes|required|numeric|gt:0|max:999999999999.99',
            'remarks' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if (array_key_exists('remarks', $data)) {
            $data['remarks'] = blank($data['remarks']) ? null : trim($data['remarks']);
        }
        $candidate = array_merge($rental->only($rental->getFillable()), $data);
        $duplicate = FinEquipmentRentalIncome::where('asset_id', $candidate['asset_id'])
            ->where('project_id', $candidate['project_id'] ?? null)
            ->where('period_month', $candidate['period_month'])
            ->where('rental_income_id', '!=', $id)
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'Rental income already exists for this asset, project, and month.'], 409);
        }
        $rental->update($data);

        return response()->json($rental);
    }

    public function destroy($id)
    {
        $rental = FinEquipmentRentalIncome::find($id);
        if (! $rental) {
            return response()->json(['message' => 'Rental income not found'], 404);
        }

        $rental->delete();

        return response()->json(['message' => 'Rental income deleted successfully']);
    }
}
