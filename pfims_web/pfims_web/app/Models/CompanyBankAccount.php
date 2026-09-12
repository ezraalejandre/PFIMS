<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyBankAccount extends Model
{
    protected $table = 'company_bank_account_tbl';
    protected $primaryKey = 'account_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'account_name',
        'account_type',
    ];

    public function cashPositions(): HasMany
    {
        return $this->hasMany(FinCashPosition::class, 'account_id', 'account_id');
    }
}
