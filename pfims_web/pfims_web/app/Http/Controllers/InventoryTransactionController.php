<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function store(Request $request)
    {

        $request->validate([

            'item_id'=>'required|integer|exists:inventory_item_tbl,item_id',
            'project_id'=>'nullable|integer',
            'transaction_type'=>'required|string|in:IN,OUT',
            'quantity'=>'required|numeric|min:0.01',
            'transaction_date'=>'required|date',

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
                    'transaction_date' => $request->transaction_date,
                ]);

                DB::table('inventory_item_tbl')
                    ->where('item_id', $request->item_id)
                    ->update(['current_stock' => $newStock]);

                return [
                    'inventory_transaction_id' => $id,
                    'current_stock'            => $newStock,
                ];
            });
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $e->getMessage() ?: 'Failed to save transaction.';
            return response()->json(['message' => $message], $status ?: 500);
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
                't.transaction_date'
            )
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('t.inventory_transaction_id')
            ->get();

        return response()->json($rows);
    }
}