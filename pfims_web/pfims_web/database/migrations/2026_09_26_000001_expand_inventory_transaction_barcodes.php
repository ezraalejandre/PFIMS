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
        Schema::table('inventory_transaction_tbl', function (Blueprint $table) {
            $table->string('bar_code', 64)->nullable()->change();
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Converting existing long or zero-prefixed barcodes back to INT would lose data.
        throw new RuntimeException('Inventory barcodes cannot be safely converted back to a 32-bit integer.');
    }
};
