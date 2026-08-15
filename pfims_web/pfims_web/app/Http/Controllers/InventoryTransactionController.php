<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function store(Request $request)
    {

        $request->validate([

            'item_id'=>'required|integer|exists:inventory_item_tbl,item_id',
            'project_id'=>'nullable|integer',
            'transaction_type'=>'required|string|in:IN,OUT',
            'quantity'=>'required|numeric|min:0.01',
            'bar_code'=>'nullable|integer|min:0',
            'transaction_date'=>'required|date|before_or_equal:today',

        ]);

        try {
            $result = DB::transaction(function () use ($request) {

                // Lock the item row for the duration of this transaction so
                // two simultaneous requests can't both read the same
                // current_stock and overwrite each other's update.
                $item = DB::table('inventory_item_tbl')
                    ->where('item_id', $request->item_id)
                    ->lockForUpdate()
                    ->first();

                if (!$item) {
                    abort(404, 'Item not found');
                }

                $delta = $request->transaction_type === 'IN'
                    ? (float) $request->quantity
                    : -(float) $request->quantity;

                $newStock = (float) $item->current_stock + $delta;

                if ($newStock < 0) {
                    abort(422, 'Not enough stock for this OUT transaction.');
                }

                $id = DB::table('inventory_transaction_tbl')->insertGetId([
                    'item_id'          => $request->item_id,
                    'project_id'       => $request->project_id,
                    'transaction_type' => $request->transaction_type,
                    'quantity'         => $request->quantity,
                    'bar_code'         => $request->bar_code,
                    'transaction_date' => $request->transaction_date,
                ]);

                DB::table('inventory_item_tbl')
                    ->where('item_id', $request->item_id)
                    ->update(['current_stock' => $newStock]);

                return [
                    'inventory_transaction_id' => $id,
                    'current_stock'            => $newStock,
                    'item_id'                  => (int) $item->item_id,
                    'item_name'                => $item->item_name,
                    'old_stock'                => (float) $item->current_stock,
                    'reorder_level'            => (float) ($item->reorder_level ?? 0),
                ];
            });
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $e->getMessage() ?: 'Failed to save transaction.';
            return response()->json(['message' => $message], $status ?: 500);
        }

        $quantity = rtrim(rtrim(number_format((float) $request->quantity, 2), '0'), '.');

        if ($request->transaction_type === 'IN') {
            $this->notifications->notify(
                title: 'New Stock-In Expense',
                message: "{$quantity} unit(s) of \"{$result['item_name']}\" were added to inventory.",
                type: 'stock_in_expense',
                kind: 'info',
                filter: 'alerts',
                referenceType: 'inventory_transaction',
                referenceId: (int) $result['inventory_transaction_id'],
            );
        }

        if (
            $result['current_stock'] <= $result['reorder_level'] &&
            !$this->notifications->alreadyNotified('item_low_stock', 'item', $result['item_id'])
        ) {
            $stock = rtrim(rtrim(number_format((float) $result['current_stock'], 2), '0'), '.');
            $threshold = rtrim(rtrim(number_format((float) $result['reorder_level'], 2), '0'), '.');

            $this->notifications->notify(
                title: 'Low Stock Warning',
                message: "\"{$result['item_name']}\" is low on stock ({$stock} left, threshold {$threshold}).",
                type: 'item_low_stock',
                kind: 'warning',
                filter: 'alerts',
                referenceType: 'item',
                referenceId: $result['item_id'],
            );
        }

        return response()->json([
            'message'                  => 'Transaction saved',
            'inventory_transaction_id' => $result['inventory_transaction_id'],
            'current_stock'            => $result['current_stock'],
        ], 201);
    }
    //iloveyou
        public function index()
    {
        $rows = DB::table('inventory_transaction_tbl as t')
            ->join('inventory_item_tbl as i', 'i.item_id', '=', 't.item_id')
            ->leftJoin('inventory_category_tbl as c', 'c.inventory_category_id', '=', 'i.inventory_category_id')
            ->leftJoin('unit_tbl as u', 'u.unit_id', '=', 'i.unit_id')
            ->leftJoin('project_tbl as p', 'p.project_id', '=', 't.project_id')
            ->select(
                't.inventory_transaction_id',
                't.item_id',
                'i.item_name',
                'c.inventory_category_name',
                'u.unit_name',
                'i.current_stock',
                't.project_id',
                'p.project_name',
                't.transaction_type',
                't.quantity',
                't.bar_code',
                't.transaction_date'
            )
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('t.inventory_transaction_id')
            ->get();

        return response()->json($rows);
    }
}
