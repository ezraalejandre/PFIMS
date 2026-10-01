<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/** Audits legacy receipts without inventing purchase prices or project links. */
class InventoryHistoryReconciler
{
    public function __construct(private InventoryCostAllocator $allocator) {}

    public function report(): array
    {
        foreach (['inventory_transaction_tbl', 'fin_expense_tbl', 'inventory_cost_allocation_tbl', 'fin_expense_category_tbl'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required reconciliation table {$table} is unavailable; apply schema only after an approved migration plan.");
            }
        }
        if (! Schema::hasColumns('inventory_transaction_tbl', ['movement_reason', 'project_id', 'transaction_date'])
            || ! Schema::hasColumns('fin_expense_tbl', ['entry_kind', 'inventory_transaction_id', 'project_id', 'amount', 'expense_date'])) {
            throw new RuntimeException('The receipt/Finance reconciliation columns are unavailable.');
        }

        $report = ['safe_purchase_receipts' => [], 'unpriced_receipts' => [], 'ambiguous_receipts' => [],
            'unallocated_withdrawals' => [], 'orphan_purchase_expenses' => []];
        $receipts = DB::table('inventory_transaction_tbl')->where('transaction_type', 'IN')
            ->orderBy('inventory_transaction_id')->get();
        foreach ($receipts as $receipt) {
            $id = (int) $receipt->inventory_transaction_id;
            $expenses = DB::table('fin_expense_tbl as expense')
                ->leftJoin('fin_expense_category_tbl as category', 'category.fin_category_id', '=', 'expense.fin_category_id')
                ->where('expense.inventory_transaction_id', $id)
                ->select('expense.*', 'category.category_code', 'category.category_name')->get();
            if (count($expenses) > 1) {
                $report['ambiguous_receipts'][] = ['id' => $id, 'reason' => 'project_link_or_multiple_finance_rows'];

                continue;
            }
            if ($expenses->isEmpty()) {
                if (in_array($receipt->movement_reason, [null, 'legacy_unpriced'], true)) {
                    $report['unpriced_receipts'][] = $id;
                } else {
                    $report['ambiguous_receipts'][] = ['id' => $id, 'reason' => 'classified_receipt_without_finance_link'];
                }

                continue;
            }
            $expense = $expenses->first();
            $category = strtolower((string) $expense->category_code.' '.(string) $expense->category_name);
            $constructionSupply = str_contains($category, 'const_supply') || str_contains($category, 'construction suppl');
            $sameDate = substr((string) $receipt->transaction_date, 0, 10) === substr((string) $expense->expense_date, 0, 10);
            $projectMatches = $receipt->project_id === null
                ? $expense->project_id === null
                : (int) $expense->project_id === (int) $receipt->project_id
                    && $expense->entry_kind === 'inventory_purchase';
            if ($projectMatches && is_numeric($expense->amount) && (float) $expense->amount > 0
                && $constructionSupply && $sameDate
                && in_array($receipt->movement_reason, [null, 'purchase'], true)
                && in_array($expense->entry_kind, [null, 'inventory_purchase'], true)) {
                $report['safe_purchase_receipts'][] = ['id' => $id, 'expense_id' => (int) $expense->fin_expense_id];
            } else {
                $report['ambiguous_receipts'][] = ['id' => $id, 'reason' => 'price_category_date_or_existing_classification_conflict'];
            }
        }

        $report['unallocated_withdrawals'] = DB::table('inventory_transaction_tbl as movement')
            ->where('movement.transaction_type', 'OUT')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('inventory_cost_allocation_tbl as allocation')
                ->whereColumn('allocation.out_transaction_id', 'movement.inventory_transaction_id'))
            ->orderBy('movement.inventory_transaction_id')->pluck('movement.inventory_transaction_id')->map(fn ($id) => (int) $id)->all();
        $report['orphan_purchase_expenses'] = DB::table('fin_expense_tbl as expense')
            ->leftJoin('inventory_transaction_tbl as movement', 'movement.inventory_transaction_id', '=', 'expense.inventory_transaction_id')
            ->where('expense.entry_kind', 'inventory_purchase')
            ->where(fn ($query) => $query->whereNull('movement.inventory_transaction_id')->orWhere('movement.transaction_type', '<>', 'IN'))
            ->orderBy('expense.fin_expense_id')->pluck('expense.fin_expense_id')->map(fn ($id) => (int) $id)->all();

        return $report;
    }

    /** Apply only classifications proven by an existing one-to-one Finance link. */
    public function classifySafeReceipts(): array
    {
        return DB::transaction(function (): array {
            $report = $this->report();
            foreach ($report['safe_purchase_receipts'] as $match) {
                DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $match['id'])
                    ->whereNull('movement_reason')->update(['movement_reason' => 'purchase']);
                DB::table('fin_expense_tbl')->where('fin_expense_id', $match['expense_id'])
                    ->whereNull('entry_kind')->update(['entry_kind' => 'inventory_purchase']);
            }
            foreach ($report['unpriced_receipts'] as $id) {
                DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $id)
                    ->whereNull('movement_reason')->update(['movement_reason' => 'legacy_unpriced']);
            }

            return $report;
        }, 3);
    }

    /** Replay only project withdrawals with unambiguous earlier receipts in posting order. */
    public function allocateSafeWithdrawals(): array
    {
        if (! Schema::hasColumns('inventory_transaction_tbl', ['item_id', 'quantity', 'project_id', 'movement_reason'])) {
            throw new RuntimeException('Inventory movement columns needed for FIFO replay are unavailable.');
        }
        $audit = $this->report();
        $ambiguousReceiptIds = array_column($audit['ambiguous_receipts'], 'id');
        $provenProjectReceiptIds = array_column($audit['safe_purchase_receipts'], 'id');
        $result = ['allocated_withdrawals' => [], 'unreconciled_withdrawals' => []];
        foreach ($audit['unallocated_withdrawals'] as $id) {
            $out = DB::table('inventory_transaction_tbl')->where('inventory_transaction_id', $id)->first();
            if (! $out || $out->project_id === null) {
                $result['unreconciled_withdrawals'][] = ['id' => $id, 'reason' => 'missing_project'];

                continue;
            }
            $priorReceipts = DB::table('inventory_transaction_tbl')
                ->where('item_id', $out->item_id)->where('transaction_type', 'IN')
                ->where('inventory_transaction_id', '<=', $id)
                ->get(['inventory_transaction_id', 'project_id', 'movement_reason']);
            if ($priorReceipts->contains(fn ($receipt) => ($receipt->project_id !== null
                    && ! in_array((int) $receipt->inventory_transaction_id, $provenProjectReceiptIds, true))
                || $receipt->movement_reason === null
                || in_array((int) $receipt->inventory_transaction_id, $ambiguousReceiptIds, true))) {
                $result['unreconciled_withdrawals'][] = ['id' => $id, 'reason' => 'ambiguous_prior_receipt'];

                continue;
            }
            try {
                DB::transaction(function () use ($id) {
                    $this->allocator->allocate($id);
                    if (DB::table('inventory_cost_allocation_tbl')->where('out_transaction_id', $id)
                        ->where('valuation_status', 'unvalued')->exists()) {
                        throw new RuntimeException('unpriced_source');
                    }
                }, 3);
                $result['allocated_withdrawals'][] = $id;
            } catch (\Throwable $error) {
                // Each attempted OUT is atomic; one bad historical movement
                // cannot leave partial allocations or stop the audit.
                $result['unreconciled_withdrawals'][] = ['id' => $id,
                    'reason' => $error->getMessage() === 'unpriced_source' ? 'unpriced_source' : 'fifo_replay_failed'];
            }
        }

        return $result;
    }
}
