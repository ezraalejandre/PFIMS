<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { if(!Schema::hasColumn('account_sessions','closing_at')) Schema::table('account_sessions',function(Blueprint $t){$t->timestamp('closing_at')->nullable();$t->char('page_nonce',64)->nullable();}); }
    public function down(): void { Schema::table('account_sessions',fn(Blueprint $t)=>$t->dropColumn(['closing_at','page_nonce'])); }
};
