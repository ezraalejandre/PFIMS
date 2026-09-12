<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the profile fields expected by the current User model/controllers.
     * Each column is checked independently so this is safe on partially
     * migrated installations as well as on databases with the full schema.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $columns = [
            'role' => static fn (Blueprint $table) => $table->string('role')->default('admin'),
            'phone' => static fn (Blueprint $table) => $table->string('phone', 50)->nullable(),
            'location' => static fn (Blueprint $table) => $table->string('location', 255)->nullable(),
            'status' => static fn (Blueprint $table) => $table->string('status')->default('Active'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', $definition);
            }
        }
    }

    /**
     * This compatibility migration is intentionally forward-only. Removing
     * these columns could destroy data added by a later application version.
     */
    public function down(): void
    {
    }
};
