<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Phase deletion also backs up related project progress changes.
        Schema::table('trash_entries', function (Blueprint $table) {
            $table->longText('payload')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('trash_entries')->whereRaw('OCTET_LENGTH(payload) > 65535')->exists()) {
            throw new RuntimeException('Cannot reduce Trash Bin storage while large recovery records exist.');
        }

        Schema::table('trash_entries', function (Blueprint $table) {
            $table->text('payload')->change();
        });
    }
};
