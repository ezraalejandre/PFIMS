<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Unifies the AR-AP sheet's four aging-bucket sub-blocks (Accounts
     * Receivable, Accounts Payable, Cash Advance (Site Expense),
     * Advances to Employees) into one table, distinguished by
     * `entry_type`, instead of four near-identical tables. R Total per
     * row = amount_30d + amount_31_60d + amount_61_90d + amount_91_120d.
     */
    public function up(): void
    {
        Schema::create('fin_receivable_payable_tbl', function (Blueprint $table) {
            $table->increments('rp_id');
            $table->enum('entry_type', [
                'accounts_receivable',
                'accounts_payable',
                'cash_advance_site',
                'advance_employee',
            ]);
            $table->integer('project_id')->nullable()
                ->comment('Used for cash_advance_site; NULL for AR/AP/employee advances not tied to a project');
            $table->string('counterparty_name', 150)->comment('Client/vendor/employee name');
            $table->date('entry_date');
            $table->decimal('amount_30d', 12, 2)->default(0.00);
            $table->decimal('amount_31_60d', 12, 2)->default(0.00);
            $table->decimal('amount_61_90d', 12, 2)->default(0.00);
            $table->decimal('amount_91_120d', 12, 2)->default(0.00);
            $table->enum('status', ['outstanding', 'settled'])->default('outstanding');
            $table->string('remarks', 255)->nullable();

            $table->index('entry_type', 'idx_rp_entry_type');
            $table->foreign('project_id', 'fk_rp_project_id')
                ->references('project_id')->on('project_tbl');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fin_receivable_payable_tbl');
    }
};
