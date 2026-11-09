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
        Schema::table('inventory_item_tbl', function (Blueprint $table) {
            $table->dropColumn('bar_code');
        });

        // Update this part of the database structure.
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->integer('bar_code')->nullable()->after('quantity');
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->dropColumn('bar_code');
        });

        // Update this part of the database structure.
        Schema::table('inventory_item_tbl', function (Blueprint $table) {
            $table->integer('bar_code')->nullable()->after('item_name');
        });
    }
};
