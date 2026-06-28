<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

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
}
