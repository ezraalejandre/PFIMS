<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InventoryPurchaseService
{
    /** Create the one Finance purchase linked to a storage receipt. Call inside a DB transaction. */
    public function createLinkedExpense(int $transactionId, $totalAmount, ?int $categoryId = null): int
    {
        if (! is_numeric($totalAmount) || (float) $totalAmount < 0.01 || (float) $totalAmount > 9999999999.99) {
            abort(422, 'A purchase receipt requires a valid positive total amount.');
        }
        $receipt = DB::table('inventory_transaction_tbl as transaction')
            ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
            ->leftJoin('unit_tbl as unit', 'unit.unit_id', '=', 'item.unit_id')
            ->where('transaction.inventory_transaction_id', $transactionId)
            ->select('transaction.*', 'item.item_name', 'unit.unit_name')
            ->first();
        if (! $receipt || $receipt->transaction_type !== 'IN' || $receipt->movement_reason !== 'purchase'
            || $receipt->project_id !== null) {
            abort(422, 'A purchase receipt must be a stock-in for storage.');
        }
        if (DB::table('fin_expense_tbl')->where('inventory_transaction_id', $transactionId)->exists()) {
            abort(409, 'This receipt already has a linked Finance purchase.');
        }

        $category = DB::table('fin_expense_category_tbl')
            ->where(function ($query) {
                $query->where('category_code', 'CONST_SUPPLY')
                    ->orWhereRaw('LOWER(category_name) LIKE ?', ['%construction suppl%']);
            })
            ->where('is_active', true);
        if ($categoryId !== null) {
            $category->where('fin_category_id', $categoryId);
        }
        $category = $category->first();
        if (! $category) {
            abort(422, 'The Construction Supply finance category is unavailable.');
        }

        $quantity = rtrim(rtrim(number_format((float) $receipt->quantity, 2, '.', ''), '0'), '.');
        $remarks = 'Inventory stock-in transaction.';
        if ($receipt->bar_code !== null && $receipt->bar_code !== '') {
            $remarks .= ' Receiving reference: '.$receipt->bar_code;
        }

        return DB::table('fin_expense_tbl')->insertGetId([
            'project_id' => null,
            'fin_category_id' => $category->fin_category_id,
            'inventory_transaction_id' => $transactionId,
            'entry_kind' => 'inventory_purchase',
            'project_cost_component' => 'material',
            'expense_description' => "Purchased {$quantity} ".strtolower((string) ($receipt->unit_name ?: 'unit'))." of {$receipt->item_name}",
            'amount' => round((float) $totalAmount, 2),
            'expense_date' => $receipt->transaction_date,
            'remarks' => $remarks,
            'proof_file_path' => $receipt->proof_file_path,
            'proof_file_name' => $receipt->proof_file_name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
