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
        if (! Schema::hasTable('login_histories') || Schema::hasColumn('login_histories', 'location')) {
            return;
        }

        // Update this part of the database structure.
        Schema::table('login_histories', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('ip_address');
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Run this step if the input checks match.
        if (! Schema::hasTable('login_histories') || ! Schema::hasColumn('login_histories', 'location')) {
            return;
        }

        // Update this part of the database structure.
        Schema::table('login_histories', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
