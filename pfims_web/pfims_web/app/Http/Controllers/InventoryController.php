<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Unit;
use App\Models\InventoryCategory;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Get all inventory items with relations
     */
    public function index(): JsonResponse
    {
        $items = InventoryItem::with(['category', 'supplier', 'unit'])
            ->orderBy('item_name')
            ->get()
            ->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'category' => $item->category?->inventory_category_name ?? 'N/A',
                    'unit' => $item->unit?->unit_name ?? 'N/A',
                    'quantity' => $item->current_stock,
                    'supplier' => $item->supplier?->supplier_name ?? 'N/A',
                    'current_stock' => $item->current_stock,
                    'reorder_level' => $item->reorder_level,
                ];
            });

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * Store a new inventory item
     */
    public function storeItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:100',
            'inventory_category_id' => 'required|integer|exists:inventory_category_tbl,inventory_category_id',
            'supplier_id' => 'required|integer|exists:supplier_tbl,supplier_id',
            'unit_id' => 'required|integer|exists:unit_tbl,unit_id',
            'current_stock' => 'required|numeric|min:0',
            'reorder_level' => 'required|numeric|min:0',
        ]);

        $item = InventoryItem::create($validated);

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Item added successfully!'], 201);
    }

    /**
     * Add a transaction (IN/OUT)
     */
    public function addTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:inventory_item_tbl,item_id',
            'project_id' => 'nullable|integer',
            'transaction_type' => 'required|in:IN,OUT',
            'quantity' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            // Create transaction record
            $transaction = InventoryTransaction::create($validated);

            // Update inventory stock
            $item = InventoryItem::findOrFail($validated['item_id']);
            if ($validated['transaction_type'] === 'IN') {
                $item->current_stock += $validated['quantity'];
            } else {
                if ($item->current_stock < $validated['quantity']) {
                    return response()->json(['success' => false, 'message' => 'Insufficient stock for this transaction.'], 400);
                }
                $item->current_stock -= $validated['quantity'];
            }
            $item->save();

            DB::commit();

            return response()->json(['success' => true, 'data' => $transaction, 'message' => 'Transaction added successfully!'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to add transaction: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all transactions across inventory items
     */
    public function getAllTransactions(): JsonResponse
    {
        $transactions = InventoryTransaction::with(['item.category', 'item.unit', 'item.supplier'])
            ->orderBy('transaction_date')
            ->orderBy('inventory_transaction_id')
            ->get();

        $runningStock = [];
        $rows = $transactions->map(function ($transaction) use (&$runningStock) {
            $itemId = $transaction->item_id;
            if (!isset($runningStock[$itemId])) {
                $runningStock[$itemId] = 0;
            }

            if ($transaction->transaction_type === 'IN') {
                $runningStock[$itemId] += $transaction->quantity;
            } else {
                $runningStock[$itemId] -= $transaction->quantity;
            }

            return [
                'inventory_transaction_id' => $transaction->inventory_transaction_id,
                'item_id' => $transaction->item_id,
                'item_name' => $transaction->item?->item_name ?? 'N/A',
                'category' => $transaction->item?->category?->inventory_category_name ?? 'N/A',
                'unit' => $transaction->item?->unit?->unit_name ?? 'N/A',
                'supplier' => $transaction->item?->supplier?->supplier_name ?? 'N/A',
                'quantity' => $transaction->quantity,
                'transaction_type' => $transaction->transaction_type,
                'transaction_date' => $transaction->transaction_date,
                'current_stock' => $runningStock[$itemId],
                'reorder_level' => $transaction->item?->reorder_level ?? 0,
            ];
        });

        return response()->json(['success' => true, 'data' => $rows->reverse()->values()]);
    }

    /**
     * Get transactions for an item
     */
    public function getTransactions($itemId): JsonResponse
    {
        $transactions = InventoryTransaction::where('item_id', $itemId)
            ->orderByDesc('transaction_date')
            ->get();

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    /**
     * Get lookup data for inventory form
     */
    public function getLookupData(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => InventoryCategory::all(['inventory_category_id', 'inventory_category_name']),
                'suppliers' => Supplier::all(['supplier_id', 'supplier_name']),
                'units' => Unit::all(['unit_id', 'unit_name']),
            ],
        ]);
    }
}
