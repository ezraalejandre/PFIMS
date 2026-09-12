<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Shared asset dimension for the REPAIR sheet's 16 per-vehicle
     * columns and the two BACKHOE sheets (KOMATSU, SUMITOMO) — turns
     * those wide, repeating-group columns into rows of one asset
     * master table.
     */
    public function up(): void
    {
        Schema::create('company_asset_tbl', function (Blueprint $table) {
            $table->increments('asset_id');
            $table->string('asset_name', 100);
            $table->enum('asset_type', ['vehicle', 'heavy_equipment', 'tool']);
            $table->string('asset_code', 50)->nullable();
            $table->decimal('acquisition_cost', 12, 2)->nullable();
            $table->enum('status', ['active', 'sold', 'disposed'])->default('active');

            $table->unique('asset_code', 'company_asset_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_asset_tbl');
    }
};
