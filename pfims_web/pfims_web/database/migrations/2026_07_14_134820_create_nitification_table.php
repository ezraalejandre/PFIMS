<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_tbl', function (Blueprint $table) {
            $table->id('notification_id');
            $table->string('title');
            $table->text('message');

            // project_delayed | project_at_risk | item_low_stock | item_out_of_stock | general
            $table->string('type');

            // warning | overdue | success | info | maintenance | system_update
            // (matches the "kind" values your NotificationsScreen already expects)
            $table->string('kind')->default('info');

            // 'alerts' or 'system' -> matches your NotificationFilter enum
            $table->string('filter')->default('alerts');

            // polymorphic-ish reference so we can dedupe / link back
            $table->string('reference_type')->nullable(); // 'project' | 'item'
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_tbl');
    }
};