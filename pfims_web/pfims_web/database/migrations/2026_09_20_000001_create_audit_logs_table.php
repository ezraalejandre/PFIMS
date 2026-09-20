<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
