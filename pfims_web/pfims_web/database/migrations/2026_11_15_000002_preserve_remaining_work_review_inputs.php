<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_remaining_cost_reviews', function (Blueprint $table) {
            $table->longText('planning_inputs_json')->nullable();
            $table->string('phase', 100)->nullable();
            $table->decimal('completion_percentage', 6, 2)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('project_remaining_cost_reviews', fn (Blueprint $table) => $table->dropColumn(['planning_inputs_json', 'phase', 'completion_percentage']));
    }
};
