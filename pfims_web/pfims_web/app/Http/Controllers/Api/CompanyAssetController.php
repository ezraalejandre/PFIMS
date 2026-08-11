<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyAsset;
use Illuminate\Http\Request;
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
        if (!$asset) {
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
            'acquisition_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,sold,disposed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset = CompanyAsset::create([
            'asset_name' => $request->asset_name,
            'asset_type' => $request->asset_type,
            'asset_code' => $request->asset_code,
            'acquisition_cost' => $request->acquisition_cost,
            'status' => $request->status ?? 'active',
        ]);

        return response()->json($asset, 201);
    }

    /**
     * Update an asset
     */
    public function update(Request $request, $id)
    {
        $asset = CompanyAsset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'asset_name' => 'sometimes|required|string|max:100',
            'asset_type' => 'sometimes|required|in:vehicle,heavy_equipment,tool',
            'asset_code' => 'nullable|string|max:50|unique:company_asset_tbl,asset_code,' . $id . ',asset_id',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,sold,disposed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $asset->update($request->all());
        return response()->json($asset);
    }

    /**
     * Delete an asset
     */
    public function destroy($id)
    {
        $asset = CompanyAsset::find($id);
        if (!$asset) {
            return response()->json(['message' => 'Asset not found'], 404);
        }

        // Check if asset has related records
        $hasExpenses = $asset->expenses()->exists();
        $hasRentalIncome = $asset->rentalIncome()->exists();

        if ($hasExpenses || $hasRentalIncome) {
            return response()->json([
                'message' => 'Cannot delete asset because it has related expense or rental income records.'
            ], 409);
        }

        $asset->delete();
        return response()->json(['message' => 'Asset deleted successfully']);
    }
}