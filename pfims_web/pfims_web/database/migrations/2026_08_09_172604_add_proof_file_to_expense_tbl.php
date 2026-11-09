<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run this step if the value check matches.
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        // Update this part of the database structure.
        Schema::table('expense_tbl', function (Blueprint $table) {
            // Run this step if the value check matches.
            if (! Schema::hasColumn('expense_tbl', 'proof_file_path')) {
                $table->string('proof_file_path')->nullable()->after('remarks');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('expense_tbl', 'proof_file_name')) {
                $table->string('proof_file_name')->nullable()->after('proof_file_path');
            }
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Run this step if the value check matches.
        if (! Schema::hasTable('expense_tbl')) {
            return;
        }

        // Update this part of the database structure.
        Schema::table('expense_tbl', function (Blueprint $table) {
            $columns = array_values(array_filter([
                // Update this part of the database structure.
                Schema::hasColumn('expense_tbl', 'proof_file_path') ? 'proof_file_path' : null,
                // Update this part of the database structure.
                Schema::hasColumn('expense_tbl', 'proof_file_name') ? 'proof_file_name' : null,
            ]));
            // Run this step if the columns is not empty.
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
