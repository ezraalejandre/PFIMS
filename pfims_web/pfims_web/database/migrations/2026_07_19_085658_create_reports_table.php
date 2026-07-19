<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_id')->unique();
            $table->string('title');
            $table->enum('type', [
                'finance', 'budget', 'expense', 
                'project', 'inventory', 'workforce', 
                'supplier', 'other'
            ])->default('other');
            $table->enum('role', ['admin', 'operations', 'accounting'])->default('accounting');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->date('date_uploaded');
            $table->string('uploaded_by');
            $table->enum('status', ['Completed', 'In Progress', 'Pending'])->default('Completed');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reports');
    }
};