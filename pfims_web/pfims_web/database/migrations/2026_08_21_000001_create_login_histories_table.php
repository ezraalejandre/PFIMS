<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('login_histories')) {
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
            if (! Schema::hasColumn('login_histories', 'id')) {
                $table->id()->first();
            }
            if (! Schema::hasColumn('login_histories', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('login_histories', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('login_histories', 'device')) {
                $table->string('device', 100)->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('login_histories', 'browser')) {
                $table->string('browser', 100)->nullable()->after('device');
            }
            if (! Schema::hasColumn('login_histories', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('browser');
            }
            if (! Schema::hasColumn('login_histories', 'logged_in_at')) {
                $table->timestamp('logged_in_at')->nullable()->after('user_agent');
            }
        });

        if (! Schema::hasIndex('login_histories', ['user_id', 'logged_in_at'])) {
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

        if (! $hasUserForeignKey && Schema::hasTable('users') && ! $hasOrphanedUsers) {
            Schema::table('login_histories', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};
