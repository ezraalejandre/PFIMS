<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Group the audit feature schema actions.
class AuditFeatureSchema
{
    // Get the ensure.
    public function ensure(): void
    {
        $this->createAuditLogsTable();
        $this->createDefaultFiltersTable();
    }

    // Create audit logs table.
    private function createAuditLogsTable(): void
    {
        // Run this step if the value check matches.
        if (Schema::hasTable('audit_logs')) return;

        // Try this action and handle any errors below.
        try {
            // Update this part of the database structure.
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name');
                $table->string('user_email');
                $table->string('user_role', 50);
                $table->string('action_type', 10);
                $table->string('module', 80);
                $table->string('subject_type');
                $table->string('subject_table');
                $table->string('subject_key', 80);
                $table->string('subject_id', 120)->nullable();
                $table->string('record_label')->nullable();
                $table->text('details');
                $table->json('changes')->nullable();
                $table->string('view_url')->nullable();
                $table->timestamps();
                $table->index(['created_at', 'action_type']);
                $table->index(['module', 'created_at']);
                $table->index(['subject_table', 'subject_id']);
            });
        } catch (\Throwable $exception) {
            // Concurrent first requests may both observe a missing table. If
            // another request completed creation, the desired state exists.
            if (! Schema::hasTable('audit_logs')) throw $exception;
        }
    }

    // Create default filters table.
    private function createDefaultFiltersTable(): void
    {
        // Run this step if the value check matches.
        if (Schema::hasTable('user_default_filters')) return;

        // Try this action and handle any errors below.
        try {
            // Update this part of the database structure.
            Schema::create('user_default_filters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('module', 80);
                $table->json('filters');
                $table->timestamps();
                $table->unique(['user_id', 'module']);
            });
        } catch (\Throwable $exception) {
            // Stop if the value check matches.
            if (! Schema::hasTable('user_default_filters')) throw $exception;
        }
    }
}
