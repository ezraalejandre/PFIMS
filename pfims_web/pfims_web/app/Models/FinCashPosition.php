<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinCashPosition extends Model
{
    protected $table = 'fin_cash_position_tbl';
    protected $primaryKey = 'cash_position_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'period_month',
        'balance_amount',
    ];

    protected $casts = [
        'period_month' => 'date',
        'balance_amount' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CompanyBankAccount::class, 'account_id', 'account_id');
    }
}
