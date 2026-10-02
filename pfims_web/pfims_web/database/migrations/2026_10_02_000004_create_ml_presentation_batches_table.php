<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ml_presentation_batches', function (Blueprint $table) {
            $table->string('batch_key', 80)->primary();
            $table->string('plan_sha256', 64);
            $table->json('manifest');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ml_presentation_batches') && DB::table('ml_presentation_batches')->exists()) {
            throw new RuntimeException('Remove presentation records using their verified manifest before removing batch tracking.');
        }
        Schema::dropIfExists('ml_presentation_batches');
    }
};
