<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_tbl', function (Blueprint $table) {
            $table->decimal('equipment_amount', 12, 2)->nullable()->after('material_amount');
            $table->decimal('other_amount', 12, 2)->nullable()->after('equipment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expense_tbl', function (Blueprint $table) {
            $table->dropColumn(['equipment_amount', 'other_amount']);
        });
    }
};