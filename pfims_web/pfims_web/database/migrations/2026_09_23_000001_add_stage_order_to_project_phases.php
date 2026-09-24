<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('project_phase_tbl', 'stage_order')) {
            Schema::table('project_phase_tbl', function (Blueprint $table) {
                $table->unsignedInteger('stage_order')->nullable()->index();
            });
        }

        $canonical = ['Planning', 'Foundation', 'Structure', 'Finishing', 'Complete'];
        foreach ($canonical as $name) {
            DB::table('project_phase_tbl')->insertOrIgnore(['phase_name' => $name]);
        }

        $phases = DB::table('project_phase_tbl')->get(['phase_id', 'phase_name']);
        $rank = array_flip(array_map('strtolower', $canonical));
        $ordered = $phases->sortBy(function ($phase) use ($rank) {
            $key = strtolower(trim((string) $phase->phase_name));
            return isset($rank[$key]) ? sprintf('0-%03d', $rank[$key]) : '1-'.str_pad((string) $phase->phase_id, 10, '0', STR_PAD_LEFT);
        })->values();

        foreach ($ordered as $index => $phase) {
            DB::table('project_phase_tbl')->where('phase_id', $phase->phase_id)
                ->update(['stage_order' => $index + 1]);
        }

        if (Schema::hasTable('project_tbl')) {
            $total = $ordered->count();
            foreach ($ordered as $index => $phase) {
                DB::table('project_tbl')
                    ->whereRaw('LOWER(TRIM(phase)) = ?', [strtolower(trim((string) $phase->phase_name))])
                    ->update(['completion_percentage' => round((($index + 1) / $total) * 100, 2)]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_phase_tbl', 'stage_order')) {
            Schema::table('project_phase_tbl', function (Blueprint $table) {
                $table->dropColumn('stage_order');
            });
        }
    }
};
