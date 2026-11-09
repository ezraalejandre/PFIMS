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
        // Run this step if the value check matches.
        if (! Schema::hasTable('login_histories')) {
            // Update this part of the database structure.
            Schema::create('login_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->string('device', 100);
                $table->string('browser', 100);
                $table->text('user_agent')->nullable();
                $table->timestamp('logged_in_at');
                $table->index(['user_id', 'logged_in_at']);
            });

            return;
        }

        // Some production databases received this table before the migration
        // record was written. Add only missing pieces and retain every row.
        Schema::table('login_histories', function (Blueprint $table) {
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'id')) {
                $table->id()->first();
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'device')) {
                $table->string('device', 100)->nullable()->after('ip_address');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'browser')) {
                $table->string('browser', 100)->nullable()->after('device');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('browser');
            }
            // Run this step if the value check matches.
            if (! Schema::hasColumn('login_histories', 'logged_in_at')) {
                $table->timestamp('logged_in_at')->nullable()->after('user_agent');
            }
        });

        // Run this step if the value check matches.
        if (! Schema::hasIndex('login_histories', ['user_id', 'logged_in_at'])) {
            // Update this part of the database structure.
            Schema::table('login_histories', function (Blueprint $table) {
                $table->index(['user_id', 'logged_in_at']);
            });
        }

        $hasUserForeignKey = collect(Schema::getForeignKeys('login_histories'))
            ->contains(fn (array $key) => $key['columns'] === ['user_id'] && $key['foreign_table'] === 'users');
        $hasOrphanedUsers = Schema::hasTable('users') && DB::table('login_histories as history')
            ->leftJoin('users', 'users.id', '=', 'history.user_id')
            ->whereNotNull('history.user_id')
            ->whereNull('users.id')
            ->exists();

        // Run this step if the has user foreign key and has orphaned users checks match.
        if (! $hasUserForeignKey && Schema::hasTable('users') && ! $hasOrphanedUsers) {
            // Update this part of the database structure.
            Schema::table('login_histories', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::dropIfExists('login_histories');
    }
};
