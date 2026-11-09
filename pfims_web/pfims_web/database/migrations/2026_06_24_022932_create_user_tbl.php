<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
// Update this part of the database structure.
Schema::create('user_tbl', function (Blueprint $table) {

    $table->id('user_id');

    $table->string('username')
          ->unique();

    $table->string('password');

    $table->enum('role',[
        'admin',
        'operations',
        'accounting'
    ]);

    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::dropIfExists('user_tbl');
    }
};
