<?php

namespace App\Http\Controllers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    /**
     * Get all inventory items with relations
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_category_tbl,inventory_category_id'],
            'supplier_id' => ['nullable', 'integer', 'exists:supplier_tbl,supplier_id'],
            'stock_state' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        $query = InventoryItem::with(['category', 'supplier', 'unit']);
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($inner) use ($search) {
                $inner->where('item_name', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($relation) => $relation->where('inventory_category_name', 'like', "%{$search}%"))
                    ->orWhereHas('supplier', fn ($relation) => $relation->where('supplier_name', 'like', "%{$search}%"))
                    ->orWhereHas('unit', fn ($relation) => $relation->where('unit_name', 'like', "%{$search}%"));
            });
        }
        if (! empty($filters['category_id'])) {
            $query->where('inventory_category_id', $filters['category_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        if (($filters['stock_state'] ?? null) === 'in_stock') {
            $query->whereColumn('current_stock', '>', 'reorder_level');
        } elseif (($filters['stock_state'] ?? null) === 'low_stock') {
            $query->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'reorder_level');
        } elseif (($filters['stock_state'] ?? null) === 'out_of_stock') {
            $query->where('current_stock', '<=', 0);
        }

        $items = $query
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
            'current_stock' => 'required|numeric|min:0|max:999999999999.99',
            'reorder_level' => 'required|numeric|min:0|max:999999999999.99',
        ]);
        $validated['item_name'] = trim($validated['item_name']);
        if ($this->duplicateItem($validated)) {
            return response()->json(['success' => false, 'message' => 'This inventory item already exists.'], 409);
        }

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
            'project_id' => 'nullable|integer|exists:project_tbl,project_id',
            'transaction_type' => 'required|in:IN,OUT',
            'quantity' => 'required|numeric|min:0.01|max:999999999999.99',
            'bar_code' => 'nullable|integer|min:0|max:2147483647',
            'transaction_date' => 'required|date|before_or_equal:today',
            'proof_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $proofFile = $validated['proof_file'];
        unset($validated['proof_file']);
        if ($this->duplicateTransaction($validated)) {
            return response()->json(['success' => false, 'message' => 'This inventory transaction already exists.'], 409);
        }
        $validated['proof_file_path'] = $proofFile->store('inventory-transaction-proofs', 'public');
        $validated['proof_file_name'] = $proofFile->getClientOriginalName();

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
                    abort(422, 'Insufficient stock for this transaction.');
                }
                $item->current_stock -= $validated['quantity'];
            }
            $item->save();

            DB::commit();

            return response()->json(['success' => true, 'data' => $transaction, 'message' => 'Transaction added successfully!'], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            if (! empty($validated['proof_file_path'])) {
                Storage::disk('public')->delete($validated['proof_file_path']);
            }
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Failed to add transaction.'], $status ?: 500);
        }
    }

    public function updateItem(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:100',
            'inventory_category_id' => 'required|integer|exists:inventory_category_tbl,inventory_category_id',
            'supplier_id' => 'required|integer|exists:supplier_tbl,supplier_id',
            'unit_id' => 'required|integer|exists:unit_tbl,unit_id',
            'reorder_level' => 'required|numeric|min:0|max:999999999999.99',
        ]);

        $item = InventoryItem::findOrFail($id);
        $validated['item_name'] = trim($validated['item_name']);
        if ($this->duplicateItem($validated, (int) $id)) {
            return response()->json(['success' => false, 'message' => 'This inventory item already exists.'], 409);
        }
        $item->update($validated);

        return response()->json([
            'success' => true,
            'data' => $item->fresh(),
            'message' => 'Item updated successfully!',
        ]);
    }

    public function destroyItem($id): JsonResponse
    {
        if (InventoryTransaction::where('item_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this item because it has inventory transaction history.',
            ], 409);
        }

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
                'message' => 'Failed to delete item: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all transactions across inventory items
     */
    public function getAllTransactions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'transaction_type' => ['nullable', 'in:IN,OUT'],
            'project_id' => ['nullable', 'integer', 'exists:project_tbl,project_id'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_category_tbl,inventory_category_id'],
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:2100-12-31'],
        ]);

        $transactions = InventoryTransaction::with(['item.category', 'item.unit', 'item.supplier', 'project'])
            ->orderBy('transaction_date')
            ->orderBy('inventory_transaction_id')
            ->get();

        $runningStock = [];
        $rows = $transactions->map(function ($transaction) use (&$runningStock) {
            $itemId = $transaction->item_id;
            if (! isset($runningStock[$itemId])) {
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
                'project_id' => $transaction->project_id,
                'project' => $transaction->project?->project_name,
                'item_name' => $transaction->item?->item_name ?? 'N/A',
                'description' => null,
                'inventory_category_id' => $transaction->item?->inventory_category_id,
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
                'proof_file_path' => $transaction->proof_file_path,
                'proof_file_name' => $transaction->proof_file_name,
            ];
        });

        if (! empty($filters['search'])) {
            $search = Str::lower(trim($filters['search']));
            $rows = $rows->filter(function (array $row) use ($search) {
                $haystack = Str::lower(implode(' ', [
                    $row['project'] ?? '', $row['item_name'] ?? '', $row['category'] ?? '',
                    $row['supplier'] ?? '', $row['unit'] ?? '', $row['bar_code'] ?? '',
                ]));

                return str_contains($haystack, $search);
            });
        }
        if (! empty($filters['transaction_type'])) {
            $rows = $rows->where('transaction_type', $filters['transaction_type']);
        }
        if (! empty($filters['project_id'])) {
            $rows = $rows->where('project_id', (int) $filters['project_id']);
        }
        if (! empty($filters['category_id'])) {
            $rows = $rows->where('inventory_category_id', (int) $filters['category_id']);
        }
        if (! empty($filters['start_date'])) {
            $rows = $rows->filter(fn (array $row) => $row['transaction_date'] >= $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $rows = $rows->filter(fn (array $row) => $row['transaction_date'] <= $filters['end_date']);
        }

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
            'quantity' => 'required|numeric|min:0.01|max:999999999999.99',
            'bar_code' => 'nullable|integer|min:0|max:2147483647',
            'transaction_date' => 'required|date|before_or_equal:today',
        ]);

        DB::beginTransaction();
        try {
            $transaction = InventoryTransaction::lockForUpdate()->findOrFail($id);
            $candidate = [
                'item_id' => $transaction->item_id,
                'project_id' => $transaction->project_id,
                'transaction_type' => $transaction->transaction_type,
                'quantity' => $validated['quantity'],
                'bar_code' => $validated['bar_code'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ];
            if ($this->duplicateTransaction($candidate, (int) $id)) {
                abort(409, 'This inventory transaction already exists.');
            }
            $item = InventoryItem::whereKey($transaction->item_id)->lockForUpdate()->firstOrFail();
            $oldEffect = $transaction->transaction_type === 'IN' ? (float) $transaction->quantity : -(float) $transaction->quantity;
            $newEffect = $transaction->transaction_type === 'IN' ? (float) $validated['quantity'] : -(float) $validated['quantity'];
            if (((float) $item->current_stock - $oldEffect + $newEffect) < 0) {
                abort(422, 'The updated OUT quantity exceeds available stock.');
            }
            $transaction->update([
                'quantity' => $validated['quantity'],
                'bar_code' => $validated['bar_code'] ?? null,
                'transaction_date' => $validated['transaction_date'],
            ]);

            $this->recalculateItemStock($transaction->item_id);
            DB::commit();

            return response()->json(['success' => true, 'data' => $transaction, 'message' => 'Transaction updated successfully!']);
        } catch (\Throwable $e) {
            DB::rollBack();
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Failed to update transaction.'], $status ?: 500);
        }
    }

    public function destroyTransaction($id): JsonResponse
    {
        InventoryTransaction::findOrFail($id);

        return response()->json([
            'success' => false,
            'message' => 'Inventory transactions cannot be deleted because they are part of the Finance audit trail.',
        ], 409);
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

    private function duplicateItem(array $data, ?int $ignoreId = null): bool
    {
        $query = InventoryItem::query()
            ->whereRaw('LOWER(TRIM(item_name)) = ?', [Str::lower(trim((string) $data['item_name']))])
            ->where('inventory_category_id', $data['inventory_category_id'])
            ->where('supplier_id', $data['supplier_id'])
            ->where('unit_id', $data['unit_id']);
        if ($ignoreId !== null) {
            $query->where('item_id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function duplicateTransaction(array $data, ?int $ignoreId = null): bool
    {
        $query = InventoryTransaction::query()
            ->where('item_id', $data['item_id'])
            ->where('transaction_type', $data['transaction_type'])
            ->where('quantity', $data['quantity'])
            ->whereDate('transaction_date', $data['transaction_date']);
        $query = empty($data['project_id']) ? $query->whereNull('project_id') : $query->where('project_id', $data['project_id']);
        $query = ($data['bar_code'] ?? null) === null ? $query->whereNull('bar_code') : $query->where('bar_code', $data['bar_code']);
        if ($ignoreId !== null) {
            $query->where('inventory_transaction_id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Legacy mobile category list response.
     */
    public function categories(): JsonResponse
    {
        return response()->json(
            InventoryCategory::query()
                ->orderBy('inventory_category_name')
                ->get(['inventory_category_id', 'inventory_category_name'])
        );
    }

    /**
     * Lightweight mobile item list, with optional lookup filters.
     */
    public function items(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:inventory_category_tbl,inventory_category_id'],
            'supplier_id' => ['nullable', 'integer', 'exists:supplier_tbl,supplier_id'],
        ]);

        $query = InventoryItem::query()
            ->select('item_id', 'item_name', 'inventory_category_id', 'supplier_id', 'unit_id', 'current_stock');

        if (! empty($filters['category_id'])) {
            $query->where('inventory_category_id', $filters['category_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        return response()->json($query->orderBy('item_name')->get());
    }

    /**
     * Legacy mobile list including each item's latest transaction metadata.
     */
    public function itemList(): JsonResponse
    {
        $latestTransactions = DB::table('inventory_transaction_tbl as t1')
            ->select('t1.item_id', 't1.transaction_type', 't1.transaction_date')
            ->whereRaw('t1.transaction_date = (
                select max(t2.transaction_date) from inventory_transaction_tbl t2
                where t2.item_id = t1.item_id
            )');

        $items = DB::table('inventory_item_tbl as i')
            ->join('inventory_category_tbl as c', 'i.inventory_category_id', '=', 'c.inventory_category_id')
            ->join('unit_tbl as u', 'i.unit_id', '=', 'u.unit_id')
            ->leftJoinSub($latestTransactions, 'lt', 'i.item_id', '=', 'lt.item_id')
            ->select(
                'i.item_id',
                'i.item_name',
                'i.current_stock',
                'c.inventory_category_name',
                'u.unit_name',
                'lt.transaction_type',
                'lt.transaction_date'
            )
            ->orderBy('i.item_name')
            ->get();

        return response()->json($items);
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
