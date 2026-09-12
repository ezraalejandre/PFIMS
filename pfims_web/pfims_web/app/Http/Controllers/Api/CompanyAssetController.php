<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyAssetController extends Controller
{
    /**
     * Get all assets
     */
    public function index(Request $request)
    {
        $query = CompanyAsset::query();

        // Filter by type if provided
        if ($request->has('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'active');
        }

        return response()->json($query->orderBy('asset_name')->get());
    }

    /**
     * Get assets by type
     */
    public function getByType($type)
    {
        $assets = CompanyAsset::where('status', 'active')
            ->where('asset_type', $type)
            ->orderBy('asset_name')
            ->get();

        return response()->json($assets);
    }

    /**
     * Get single asset
     */
    public function show($id)
    {
        $asset = CompanyAsset::find($id);
        if (! $asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        return response()->json($asset);
    }

    /**
     * Create a new asset
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_name' => 'required|string|max:100',
            'asset_type' => 'required|in:vehicle,heavy_equipment,tool',
            'asset_code' => 'nullable|string|max:50|unique:company_asset_tbl,asset_code',
            'acquisition_cost' => 'nullable|numeric|min:0|max:999999999999.99',
            'status' => 'sometimes|required|in:active,sold,disposed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['asset_name'] = trim($data['asset_name']);
        $data['asset_code'] = blank($data['asset_code'] ?? null) ? null : strtoupper(trim($data['asset_code']));
        $data['status'] = $data['status'] ?? 'active';
        if ($this->duplicateNameType($data['asset_name'], $data['asset_type'])) {
            return response()->json(['message' => 'An asset with this name and type already exists.'], 409);
        }
        $asset = CompanyAsset::create($data);

        return response()->json($asset, 201);
    }

    /**
     * Update an asset
     */
    public function update(Request $request, $id)
    {
        $asset = CompanyAsset::find($id);
        if (! $asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'asset_name' => 'sometimes|required|string|max:100',
            'asset_type' => 'sometimes|required|in:vehicle,heavy_equipment,tool',
            'asset_code' => 'nullable|string|max:50|unique:company_asset_tbl,asset_code,'.$id.',asset_id',
            'acquisition_cost' => 'nullable|numeric|min:0|max:999999999999.99',
            'status' => 'sometimes|required|in:active,sold,disposed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        if (isset($data['asset_name'])) {
            $data['asset_name'] = trim($data['asset_name']);
        }
        if (array_key_exists('asset_code', $data)) {
            $data['asset_code'] = blank($data['asset_code']) ? null : strtoupper(trim($data['asset_code']));
        }
        $candidate = array_merge($asset->only($asset->getFillable()), $data);
        if ($this->duplicateNameType($candidate['asset_name'], $candidate['asset_type'], (int) $id)) {
            return response()->json(['message' => 'An asset with this name and type already exists.'], 409);
        }
        $asset->update($data);

        return response()->json($asset);
    }

    /**
     * Delete an asset
     */
    public function destroy($id)
    {
        $asset = CompanyAsset::find($id);
        if (! $asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        // Check if asset has related records
        $hasExpenses = $asset->expenses()->exists();
        $hasRentalIncome = $asset->rentalIncome()->exists();

        if ($hasExpenses || $hasRentalIncome) {
            return response()->json([
                'message' => 'Cannot delete asset because it has related expense or rental income records.',
            ], 409);
        }

        $asset->delete();

        return response()->json(['message' => 'Asset deleted successfully']);
    }

    private function duplicateNameType(string $name, string $type, ?int $ignoreId = null): bool
    {
        $query = DB::table('company_asset_tbl')
            ->where('asset_type', $type)
            ->whereRaw('LOWER(TRIM(asset_name)) = ?', [strtolower(trim($name))]);
        if ($ignoreId !== null) {
            $query->where('asset_id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
