<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('account_sessions',function(Blueprint $t){$t->unsignedBigInteger('user_id')->primary();$t->char('token_hash',64);$t->char('credential_hash',64);$t->timestamp('expires_at')->index();$t->timestamp('closing_at')->nullable();$t->char('page_nonce',64)->nullable();}); }
    public function down(): void { Schema::dropIfExists('account_sessions'); }
};
