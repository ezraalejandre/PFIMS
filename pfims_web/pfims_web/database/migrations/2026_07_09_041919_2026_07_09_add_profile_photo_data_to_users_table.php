<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds columns to store the profile photo directly in the database
     * (as raw bytes + its MIME type) instead of on local disk storage.
     * The old `profile_photo` path column is left untouched for now —
     * drop it in a follow-up migration once the new columns are
     * confirmed working end-to-end.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->longText('profile_photo_data')->nullable()->after('profile_photo');
            $table->string('profile_photo_mime', 100)->nullable()->after('profile_photo_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['profile_photo_data', 'profile_photo_mime']);
        });
    }
};