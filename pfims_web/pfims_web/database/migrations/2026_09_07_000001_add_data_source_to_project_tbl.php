<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run this step if the input checks match.
        if (Schema::hasTable('project_tbl') && ! Schema::hasColumn('project_tbl', 'data_source')) {
            // Update this part of the database structure.
            Schema::table('project_tbl', function (Blueprint $table) {
                $table->string('data_source', 64)->default('operational');
            });
        }
    }

    // Undo these database changes.
    public function down(): void
    {
        // Run this step if the input checks match.
        if (Schema::hasTable('project_tbl') && Schema::hasColumn('project_tbl', 'data_source')) {
            // Update this part of the database structure.
            Schema::table('project_tbl', function (Blueprint $table) {
                $table->dropColumn('data_source');
            });
        }
    }
};
