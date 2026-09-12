<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications_tbl', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('notification_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('requires_acknowledgement')->default(false)->after('is_read');
            $table->timestamp('acknowledged_at')->nullable()->after('requires_acknowledgement');
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications_tbl', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_read']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'requires_acknowledgement', 'acknowledged_at']);
        });
    }
};
