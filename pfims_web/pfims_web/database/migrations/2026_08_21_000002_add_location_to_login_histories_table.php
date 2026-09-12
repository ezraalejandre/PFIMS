<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('login_histories') || Schema::hasColumn('login_histories', 'location')) {
            return;
        }

        Schema::table('login_histories', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('login_histories') || ! Schema::hasColumn('login_histories', 'location')) {
            return;
        }

        Schema::table('login_histories', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
