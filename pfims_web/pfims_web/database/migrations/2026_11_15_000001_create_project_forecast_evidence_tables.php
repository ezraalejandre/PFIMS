<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_remaining_cost_reviews', function (Blueprint $table) {
            $table->bigIncrements('review_id');
            $table->integer('project_id');
            $table->dateTime('recorded_at');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->decimal('recorded_spend', 16, 2);
            $table->decimal('budget_amount', 16, 2);
            $table->decimal('committed_remaining', 16, 2);
            $table->decimal('uncommitted_remaining', 16, 2);
            $table->longText('items_json');
            $table->string('notes', 1000)->nullable();
            $table->index(['project_id', 'recorded_at']);
            $table->foreign('project_id')->references('project_id')->on('project_tbl')->cascadeOnDelete();
        });
        Schema::create('ml_validation_reservations', function (Blueprint $table) {
            $table->integer('project_id')->primary();
            $table->dateTime('reserved_at');
            $table->string('purpose');
            $table->foreign('project_id')->references('project_id')->on('project_tbl')->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('ml_validation_reservations');
        Schema::dropIfExists('project_remaining_cost_reviews');
    }
};
