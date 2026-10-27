<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('trash_entries', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('deleted_by')->index();
        $table->string('label'); $table->text('payload');
        $table->timestamp('deleted_at'); $table->timestamp('expires_at')->index();
    }); }
    public function down(): void { Schema::dropIfExists('trash_entries'); }
};
