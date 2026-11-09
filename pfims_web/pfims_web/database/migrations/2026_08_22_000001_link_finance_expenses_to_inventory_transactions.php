<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Update this part of the database structure.
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->integer('inventory_transaction_id')->nullable()->unique()->after('source_expense_id');
            $table->foreign('inventory_transaction_id', 'fk_fin_expense_inventory_transaction')
                ->references('inventory_transaction_id')
                ->on('inventory_transaction_tbl')
                ->restrictOnDelete();
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->dropForeign('fk_fin_expense_inventory_transaction');
            $table->dropUnique(['inventory_transaction_id']);
            $table->dropColumn('inventory_transaction_id');
        });
    }
};
