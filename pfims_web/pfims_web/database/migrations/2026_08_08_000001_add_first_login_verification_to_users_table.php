<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('first_login_verification_required')->default(false);
            $table->string('first_login_otp')->nullable();
            $table->timestamp('first_login_otp_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_login_verification_required',
                'first_login_otp',
                'first_login_otp_expires_at',
            ]);
        });
    }
};
