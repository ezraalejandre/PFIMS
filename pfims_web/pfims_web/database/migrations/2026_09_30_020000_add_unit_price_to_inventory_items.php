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
            $table->decimal('unit_price', 12, 2)->nullable()->after('unit_id');
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::table('inventory_item_tbl', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
