<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Price legacy purchase receipts from the item's current price, with explicit provenance. */
class LegacyInventoryPriceBackfill
{
    public function candidates(): array
    {
        $category = DB::table('fin_expense_category_tbl')
            ->where('category_code', 'CONSTRUCTION_SUPPLY')->where('is_active', true)->first();
        if (! $category) {
            throw new RuntimeException('The active Construction Supply expense category is missing.');
        }

        $receipts = DB::table('inventory_transaction_tbl as movement')
            ->join('inventory_item_tbl as item', 'item.item_id', '=', 'movement.item_id')
            ->leftJoin('unit_tbl as unit', 'unit.unit_id', '=', 'item.unit_id')
            ->leftJoin('fin_expense_tbl as expense', 'expense.inventory_transaction_id', '=', 'movement.inventory_transaction_id')
            ->where('movement.transaction_type', 'IN')
            ->whereNull('expense.fin_expense_id')
            ->where(function ($query) {
                $query->where('movement.movement_reason', 'legacy_unpriced')
                    ->orWhere(function ($projectReceipt) {
                        $projectReceipt->whereNull('movement.movement_reason')
                            ->whereNotNull('movement.project_id');
                    });
            })
            ->orderBy('movement.inventory_transaction_id')
            ->get(['movement.inventory_transaction_id', 'movement.project_id', 'movement.quantity',
                'movement.transaction_date', 'movement.movement_reason', 'movement.bar_code',
                'movement.proof_file_path', 'movement.proof_file_name', 'item.item_name',
                'item.unit_price', 'unit.unit_name']);

        $candidates = [];
        foreach ($receipts as $receipt) {
            $amount = round((float) $receipt->quantity * (float) $receipt->unit_price, 2);
            if ((float) $receipt->quantity <= 0 || (float) $receipt->unit_price <= 0
                || $amount < 0.01 || $amount > 9999999999.99) {
                throw new RuntimeException("Receipt {$receipt->inventory_transaction_id} has no valid quantity × unit price.");
            }
            $candidates[] = ['receipt' => $receipt, 'amount' => $amount,
                'category_id' => $category->fin_category_id];
        }

        return $candidates;
    }

    public function apply(): array
    {
        return DB::transaction(function (): array {
            $candidates = $this->candidates();
            $storage = 0;
            $project = 0;
            foreach ($candidates as $candidate) {
                $receipt = $candidate['receipt'];
                $id = (int) $receipt->inventory_transaction_id;
                if (DB::table('fin_expense_tbl')->where('inventory_transaction_id', $id)->exists()) {
                    throw new RuntimeException("Receipt {$id} gained a linked expense during backfill.");
                }
                $quantity = rtrim(rtrim(number_format((float) $receipt->quantity, 2, '.', ''), '0'), '.');
                DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $id)
                    ->update(['movement_reason' => 'purchase']);
                DB::table('fin_expense_tbl')->insert([
                    'project_id' => $receipt->project_id,
                    'fin_category_id' => $candidate['category_id'],
                    'inventory_transaction_id' => $id,
                    'entry_kind' => 'inventory_purchase',
                    'project_cost_component' => 'material',
                    'expense_description' => "Purchased {$quantity} ".strtolower((string) ($receipt->unit_name ?: 'unit'))." of {$receipt->item_name}",
                    'amount' => $candidate['amount'],
                    'expense_date' => $receipt->transaction_date,
                    'remarks' => 'Historical item-price estimate: quantity × current Unit Price on '.today()->toDateString().'. Original invoice amount not recorded.',
                    'proof_file_path' => $receipt->proof_file_path,
                    'proof_file_name' => $receipt->proof_file_name,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $receipt->project_id === null ? $storage++ : $project++;
            }

            return ['storage_purchases' => $storage, 'project_purchases' => $project];
        }, 3);
    }
}
