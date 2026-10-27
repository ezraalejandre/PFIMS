<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_manager_tbl', function (Blueprint $table) {
            $table->increments('manager_id');
            $table->string('manager_name', 150)->unique();
        });
        // Preserve existing assignments in the new dropdown.
        if (Schema::hasColumn('project_tbl', 'project_manager')) {
            $seen = [];
            foreach (DB::table('project_tbl')->whereNotNull('project_manager')->distinct()->pluck('project_manager') as $name) {
                $name = trim($name);
                $key = mb_strtolower($name);
                if ($name === '' || isset($seen[$key])) continue;
                DB::table('project_manager_tbl')->insert(['manager_name' => $name]);
                $seen[$key] = true;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_manager_tbl');
    }
};
