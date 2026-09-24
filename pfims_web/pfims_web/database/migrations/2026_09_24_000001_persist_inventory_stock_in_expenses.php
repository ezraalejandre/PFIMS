<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        $category = DB::table('fin_expense_category_tbl')
            ->where('category_code', 'CONST_SUPPLY')
            ->orWhereRaw('LOWER(category_name) LIKE ?', ['%construction suppl%'])
            ->orderByDesc('is_active')
            ->first();

        if (! $category) {
            return;
        }

        DB::table('inventory_transaction_tbl as transaction')
            ->join('inventory_item_tbl as item', 'item.item_id', '=', 'transaction.item_id')
            ->join('unit_tbl as unit', 'unit.unit_id', '=', 'item.unit_id')
            ->leftJoin('fin_expense_tbl as expense', 'expense.inventory_transaction_id', '=', 'transaction.inventory_transaction_id')
            ->where('transaction.transaction_type', 'IN')
            ->whereNull('expense.fin_expense_id')
            ->select(
                'transaction.inventory_transaction_id',
                'transaction.quantity',
                'transaction.bar_code',
                'transaction.transaction_date',
                'transaction.proof_file_path',
                'transaction.proof_file_name',
                'item.item_name',
                'unit.unit_name'
            )
            ->orderBy('transaction.inventory_transaction_id')
            ->each(function ($transaction) use ($category) {
                $quantity = rtrim(rtrim(number_format((float) $transaction->quantity, 2, '.', ''), '0'), '.');
                $remarks = 'Inventory stock-in transaction.';
                if ($transaction->bar_code !== null && $transaction->bar_code !== '') {
                    $remarks .= ' Receiving reference: '.$transaction->bar_code;
                }

                DB::table('fin_expense_tbl')->insert([
                    'project_id' => null,
                    'fin_category_id' => $category->fin_category_id,
                    'inventory_transaction_id' => $transaction->inventory_transaction_id,
                    'project_cost_component' => 'material',
                    'expense_description' => "Purchased {$quantity} ".strtolower($transaction->unit_name)." of {$transaction->item_name}",
                    'amount' => null,
                    'expense_date' => $transaction->transaction_date,
                    'remarks' => $remarks,
                    'proof_file_path' => $transaction->proof_file_path,
                    'proof_file_name' => $transaction->proof_file_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('fin_expense_tbl')->whereNotNull('inventory_transaction_id')->whereNull('amount')->delete();

        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable(false)->change();
        });
    }
};
