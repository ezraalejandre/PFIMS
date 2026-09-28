<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_expense_tbl', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->nullable()->change();
        });

        // Historical IN rows have no reliable purchase price or reason. Do
        // not manufacture Finance purchases from them during schema rollout.
    }

    public function down(): void
    {
        throw new RuntimeException('Cannot make expense amounts non-nullable without reviewing historical unpriced rows.');
    }
};
