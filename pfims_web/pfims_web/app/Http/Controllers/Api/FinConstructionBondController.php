<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinConstructionBond;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinConstructionBondController extends Controller
{
    public function index(Request $request)
    {
        $query = FinConstructionBond::with('project:project_id,project_name');
        
        // Filter by project if provided
        if ($request->has('project_id') && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }
        
        // Filter by status if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:project_tbl,project_id',
            'bond_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'bond_provider' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,released,forfeited',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bond = FinConstructionBond::create($request->all());
        return response()->json($bond, 201);
    }

    public function update(Request $request, $id)
    {
        $bond = FinConstructionBond::find($id);
        if (!$bond) {
            return response()->json(['message' => 'Bond not found'], 404);
        }

        $bond->update($request->all());
        return response()->json($bond);
    }

    public function destroy($id)
    {
        $bond = FinConstructionBond::find($id);
        if (!$bond) {
            return response()->json(['message' => 'Bond not found'], 404);
        }

        $bond->delete();
        return response()->json(['message' => 'Bond deleted successfully']);
    }
}