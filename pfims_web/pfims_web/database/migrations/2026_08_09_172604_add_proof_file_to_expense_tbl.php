<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        Schema::table('expense_tbl', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_tbl', 'proof_file_path')) {
                $table->string('proof_file_path')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('expense_tbl', 'proof_file_name')) {
                $table->string('proof_file_name')->nullable()->after('proof_file_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        Schema::table('expense_tbl', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('expense_tbl', 'proof_file_path') ? 'proof_file_path' : null,
                Schema::hasColumn('expense_tbl', 'proof_file_name') ? 'proof_file_name' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
