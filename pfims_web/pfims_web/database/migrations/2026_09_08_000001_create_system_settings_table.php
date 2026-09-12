<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 80)->unique();
            $table->string('setting_value', 255);
            $table->timestamps();
        });

        $now = now();
        DB::table('system_settings')->insert([
            ['setting_key' => 'inventory_reorder_threshold', 'setting_value' => '5', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'project_at_risk_days_before_end_date', 'setting_value' => '7', 'created_at' => $now, 'updated_at' => $now],
            ['setting_key' => 'inventory_max_transaction_quantity', 'setting_value' => '999999999.99', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
