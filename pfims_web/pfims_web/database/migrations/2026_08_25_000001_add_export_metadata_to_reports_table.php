<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('generation_method', 30)->default('legacy_upload')->after('status');
            $table->string('dataset_key', 40)->nullable()->after('generation_method');
            $table->string('export_format', 10)->nullable()->after('dataset_key');
            $table->unsignedInteger('row_count')->default(0)->after('export_format');
            $table->json('selected_columns')->nullable()->after('row_count');
            $table->json('filters_applied')->nullable()->after('selected_columns');
            $table->json('export_options')->nullable()->after('filters_applied');
            $table->timestamp('generated_at')->nullable()->after('export_options');

            $table->index(['generation_method', 'dataset_key'], 'reports_generation_dataset_idx');
            $table->index('generated_at', 'reports_generated_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_generation_dataset_idx');
            $table->dropIndex('reports_generated_at_idx');
            $table->dropColumn([
                'generation_method', 'dataset_key', 'export_format', 'row_count',
                'selected_columns', 'filters_applied', 'export_options', 'generated_at',
            ]);
        });
    }
};
