<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// Group the inventory cost allocator actions.
class InventoryCostAllocator
{
    // Set up the values this class needs.
    public function __construct(private ProjectCostLedger $projectCosts) {}

    /**
     * Allocate one new withdrawal by receipt order. Call inside the transaction
     * that locks the item and creates the OUT movement.
     */
    public function allocate(int $outTransactionId): void
    {
        $out = DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $outTransactionId)->first();
        // Run this step if the out and transaction type checks match.
        if (! $out || $out->transaction_type !== 'OUT' || $out->project_id === null) {
            abort(422, 'A project stock-out is required for cost allocation.');
        }
        // Run this step if the exists check matches.
        if (DB::table('inventory_cost_allocation_tbl')->where('out_transaction_id', $outTransactionId)->exists()) {
            abort(409, 'This stock-out has already been allocated.');
        }

        // IDs reflect posting order. Recorded dates may be backdated; replaying
        // posting order prevents a later entry from repricing an earlier OUT.
        $movements = DB::table('inventory_transaction_tbl as movement')
            ->leftJoin('fin_expense_tbl as expense', 'expense.inventory_transaction_id', '=', 'movement.inventory_transaction_id')
            ->where('movement.item_id', $out->item_id)
            ->where('movement.inventory_transaction_id', '<=', $outTransactionId)
            ->orderBy('movement.inventory_transaction_id')
            ->get([
                'movement.inventory_transaction_id', 'movement.transaction_type', 'movement.quantity',
                'expense.entry_kind', 'expense.amount',
            ]);

        $lots = [];
        // Process each movement.
        foreach ($movements as $movement) {
            $quantity = (int) round((float) $movement->quantity * 100);
            // Run this step if the quantity is at most 0.
            if ($quantity <= 0) {
                abort(422, 'Inventory movement has an invalid quantity.');
            }
            // Run this step if the transaction type equals in.
            if ($movement->transaction_type === 'IN') {
                $valued = $movement->entry_kind === 'inventory_purchase' && $movement->amount !== null;
                $lots[] = [
                    'id' => (int) $movement->inventory_transaction_id,
                    'total_quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'total_cents' => $valued ? (int) round((float) $movement->amount * 100) : null,
                ];
                continue;
            }
            // Run this step if the transaction type does not equal out.
            if ($movement->transaction_type !== 'OUT') {
                abort(422, 'Inventory movement has an invalid transaction type.');
            }

            $isCurrent = (int) $movement->inventory_transaction_id === $outTransactionId;
            // Process each lot.
            foreach ($lots as &$lot) {
                // Run this step if the quantity equals 0.
                if ($quantity === 0) {
                    break;
                }
                $taken = min($quantity, $lot['remaining_quantity']);
                // Skip this item if the taken equals 0.
                if ($taken === 0) {
                    continue;
                }
                $quantity -= $taken;
                $lot['remaining_quantity'] -= $taken;

                // Run this step if the is current is missing or false.
                if (! $isCurrent) {
                    // Run this step if the lot total cents is not null.
                    if ($lot['total_cents'] !== null) {
                        $earlierAllocation = DB::table('inventory_cost_allocation_tbl')
                            ->where('in_transaction_id', $lot['id'])
                            ->where('out_transaction_id', $movement->inventory_transaction_id)
                            ->first();
                        // Run this step if the earlier allocation and valuation status checks match.
                        if (! $earlierAllocation || $earlierAllocation->valuation_status !== 'valued'
                            || $earlierAllocation->allocated_amount === null
                            || (int) round((float) $earlierAllocation->quantity * 100) !== $taken) {
                            abort(422, 'An earlier stock-out used a valued receipt without a matching cost allocation. Reconcile it before another stock-out.');
                        }
                    }
                    continue;
                }
                $valued = $lot['total_cents'] !== null;
                $amount = null;
                $unitCost = null;
                // Run this step if the valued is set.
                if ($valued) {
                    $previousCents = (int) round((float) DB::table('inventory_cost_allocation_tbl')
                        ->where('in_transaction_id', $lot['id'])->sum('allocated_amount') * 100);
                    $amount = $lot['remaining_quantity'] === 0
                        ? $lot['total_cents'] - $previousCents
                        : (int) round($lot['total_cents'] * $taken / $lot['total_quantity']);
                    // Run this step if the amount and lot total cents checks match.
                    if ($amount < 0 || $amount > $lot['total_cents'] - $previousCents) {
                        abort(422, 'Purchase cost allocation exceeds the receipt amount.');
                    }
                    $unitCost = number_format($lot['total_cents'] / $lot['total_quantity'], 6, '.', '');
                }

                DB::table('inventory_cost_allocation_tbl')->insert([
                    'in_transaction_id' => $lot['id'],
                    'out_transaction_id' => $outTransactionId,
                    'project_id' => $out->project_id,
                    'quantity' => number_format($taken / 100, 2, '.', ''),
                    'valuation_status' => $valued ? 'valued' : 'unvalued',
                    'unit_cost' => $unitCost,
                    'allocated_amount' => $valued ? number_format($amount / 100, 2, '.', '') : null,
                    'allocated_at' => now(),
                ]);
            }
            unset($lot);
            // Run this step if the quantity does not equal 0.
            if ($quantity !== 0) {
                abort(422, 'Stock-out exceeds available receipt lots.');
            }
        }
        $this->projectCosts->syncBudget((int) $out->project_id);
    }
}
