<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Get all suppliers
     */
    public function index()
    {
        $suppliers = Supplier::all();
        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    /**
     * Store a new supplier
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier added successfully!',
            'data' => $supplier,
        ], 201);
    }

    /**
     * Update a supplier
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'supplier_name' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully!',
            'data' => $supplier,
        ]);
    }

    /**
     * Get a single supplier
     */
    public function show($id)
    {
        $supplier = Supplier::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $supplier,
        ]);
    }

    /**
     * Delete a supplier
     */
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);

        $hasItems = DB::table('inventory_item_tbl')->where('supplier_id', $id)->exists();
        if ($hasItems) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete supplier: it is still linked to inventory items.'
            ], 409);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully!',
        ]);
    }
}
