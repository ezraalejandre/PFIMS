<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Construction bonds per project (CONST BOND sheet). G Total =
     * SUM(amount) WHERE status = 'active', computed via a view rather
     * than stored.
     */
    public function up(): void
    {
        Schema::create('fin_construction_bond_tbl', function (Blueprint $table) {
            $table->increments('bond_id');
            $table->integer('project_id');
            $table->date('bond_date');
            $table->decimal('amount', 12, 2);
            $table->string('bond_provider', 100)->nullable();
            $table->enum('status', ['active', 'released', 'forfeited'])->default('active');
            $table->string('remarks', 255)->nullable();

            $table->foreign('project_id', 'fk_bond_project_id')
                ->references('project_id')->on('project_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_construction_bond_tbl');
    }
};
