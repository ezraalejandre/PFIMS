<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Run this step if the value check matches.
        if (Schema::hasTable('project_tbl')) {
            DB::table('project_tbl')->where('status', 'On Track')->update(['status' => 'Ongoing']);
        }
    }

    // Undo these database changes.
    public function down(): void
    {
        // Do not rename new Ongoing records back to the retired status.
    }
};
