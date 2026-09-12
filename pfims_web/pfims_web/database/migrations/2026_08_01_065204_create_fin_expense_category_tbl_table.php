<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Finance-statement expense category dimension. Replaces the coarse
     * 4-value `expense_category_tbl` for Finance-module reporting: each
     * row is one line item from the client's EXPOVRALL / ADMINEXP
     * sheets, tagged `direct` (project site cost) or `admin` (office
     * overhead) so the EXPOVRALL "A - B" split is encoded declaratively
     * instead of a manual formula.
     */
    public function up(): void
    {
        Schema::create('fin_expense_category_tbl', function (Blueprint $table) {
            $table->increments('fin_category_id');
            $table->string('category_code', 40);
            $table->string('category_name', 100);
            $table->enum('classification', ['direct', 'admin']);
            $table->boolean('is_active')->default(true);

            $table->unique('category_code', 'fin_expense_category_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_expense_category_tbl');
    }
};
