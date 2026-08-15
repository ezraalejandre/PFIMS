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
                    'inventory_category_id' => $item->inventory_category_id,
                    'supplier_id' => $item->supplier_id,
                    'unit_id' => $item->unit_id,
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
            'bar_code' => 'nullable|integer|min:0',
            'transaction_date' => 'required|date|before_or_equal:today',
        ]);

        DB::beginTransaction();
        try {
            $item = InventoryItem::findOrFail($validated['item_id']);

            // Create transaction record
            $transaction = InventoryTransaction::create($validated);

            // Update inventory stock
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

    public function updateItem(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:100',
            'inventory_category_id' => 'required|integer|exists:inventory_category_tbl,inventory_category_id',
            'supplier_id' => 'required|integer|exists:supplier_tbl,supplier_id',
            'unit_id' => 'required|integer|exists:unit_tbl,unit_id',
            'reorder_level' => 'required|numeric|min:0',
        ]);

        $item = InventoryItem::findOrFail($id);
        $item->update($validated);

        return response()->json([
            'success' => true,
            'data' => $item->fresh(),
            'message' => 'Item updated successfully!',
        ]);
    }

    public function destroyItem($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $item = InventoryItem::lockForUpdate()->findOrFail($id);
            InventoryTransaction::where('item_id', $item->item_id)->delete();
            $item->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Item deleted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete item: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all transactions across inventory items
     */
    public function getAllTransactions(): JsonResponse
    {
        $transactions = InventoryTransaction::with(['item.category', 'item.unit', 'item.supplier', 'project'])
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
                'project' => $transaction->project?->project_name,
                'item_name' => $transaction->item?->item_name ?? 'N/A',
                'description' => null,
                'category' => $transaction->item?->category?->inventory_category_name ?? 'N/A',
                'unit' => $transaction->item?->unit?->unit_name ?? 'N/A',
                'supplier' => $transaction->item?->supplier?->supplier_name ?? 'N/A',
                'supplier_id' => $transaction->item?->supplier?->supplier_id ?? null,
                'quantity' => $transaction->quantity,
                'bar_code' => $transaction->bar_code,
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

    public function updateTransaction(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'bar_code' => 'nullable|integer|min:0',
            'transaction_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $transaction = InventoryTransaction::lockForUpdate()->findOrFail($id);
            $transaction->update([
                'quantity' => $validated['quantity'],
                'bar_code' => $validated['bar_code'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            $this->recalculateItemStock($transaction->item_id);
            DB::commit();

            return response()->json(['success' => true, 'data' => $transaction, 'message' => 'Transaction updated successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to update transaction: ' . $e->getMessage()], 500);
        }
    }

    public function destroyTransaction($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $transaction = InventoryTransaction::lockForUpdate()->findOrFail($id);
            $itemId = $transaction->item_id;
            $transaction->delete();

            $this->recalculateItemStock($itemId);
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Transaction deleted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function recalculateItemStock(int $itemId): void
    {
        $runningStock = InventoryTransaction::where('item_id', $itemId)
            ->get()
            ->reduce(function ($carry, $transaction) {
                return $carry + ($transaction->transaction_type === 'IN' ? $transaction->quantity : -$transaction->quantity);
            }, 0);

        $item = InventoryItem::findOrFail($itemId);
        $item->current_stock = max(0, $runningStock);
        $item->save();
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
