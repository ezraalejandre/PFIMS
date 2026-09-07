<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_tbl') && ! Schema::hasColumn('project_tbl', 'data_source')) {
            Schema::table('project_tbl', function (Blueprint $table) {
                $table->string('data_source', 64)->default('operational');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_tbl') && Schema::hasColumn('project_tbl', 'data_source')) {
            Schema::table('project_tbl', function (Blueprint $table) {
                $table->dropColumn('data_source');
            });
        }
    }
};
