<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Apply these database changes.
    public function up(): void
    {
        // Update this part of the database structure.
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('otp'); // hashed OTP (or hashed verified token after step 2)
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    // Undo these database changes.
    public function down(): void
    {
        // Update this part of the database structure.
        Schema::dropIfExists('password_reset_otps');
    }
};