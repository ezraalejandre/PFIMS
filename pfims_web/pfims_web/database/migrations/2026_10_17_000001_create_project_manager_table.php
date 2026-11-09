<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Update this part of the database structure.
        Schema::create('project_manager_tbl', function (Blueprint $table) {
            $table->increments('manager_id');
            $table->string('manager_name', 150)->unique();
        });
        // Preserve existing assignments in the new dropdown.
        if (Schema::hasColumn('project_tbl', 'project_manager')) {
            $seen = [];
            // Process each name.
            foreach (DB::table('project_tbl')->whereNotNull('project_manager')->distinct()->pluck('project_manager') as $name) {
                $name = trim($name);
                $key = mb_strtolower($name);
                // Skip this item if the name and key checks match.
                if ($name === '' || isset($seen[$key])) continue;
                DB::table('project_manager_tbl')->insert(['manager_name' => $name]);
                $seen[$key] = true;
            }
        }
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::dropIfExists('project_manager_tbl');
    }
};
