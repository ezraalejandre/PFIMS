<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginHistoryMigrationIdempotencyTest extends TestCase
{
    public function test_login_history_migrations_preserve_an_existing_schema_and_can_run_repeatedly(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
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

        $createMigration = require base_path('database/migrations/2026_08_21_000001_create_login_histories_table.php');
        $locationMigration = require base_path('database/migrations/2026_08_21_000002_add_location_to_login_histories_table.php');

        $createMigration->up();
        $createMigration->up();
        $locationMigration->up();
        $locationMigration->up();

        $this->assertTrue(Schema::hasColumns('login_histories', [
            'id', 'user_id', 'ip_address', 'location', 'device', 'browser', 'user_agent', 'logged_in_at',
        ]));
        $this->assertTrue(Schema::hasIndex('login_histories', ['user_id', 'logged_in_at']));
    }
}
