<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_item_tbl', function (Blueprint $table) {
            $table->integer('bar_code')->nullable()->after('item_name');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_item_tbl', function (Blueprint $table) {
            $table->dropColumn('bar_code');
        });
    }
};
