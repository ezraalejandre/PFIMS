<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cash/treasury account dimension for the CASHASSET sheet
     * (Cash on Hand, Cash on Hand - Field, Treasury EVCA, Treasury OB,
     * Treasury OP, Treasury, Treasury EVCA Corp, Treasury (PhilHealth
     * Purposes)).
     */
    public function up(): void
    {
        Schema::create('company_bank_account_tbl', function (Blueprint $table) {
            $table->increments('account_id');
            $table->string('account_name', 100);
            $table->enum('account_type', ['cash_on_hand', 'cash_on_hand_field', 'treasury'])
                ->default('treasury');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_bank_account_tbl');
    }
};
