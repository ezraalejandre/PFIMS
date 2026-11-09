<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Link records to the company bank account table.
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

    // Link this record to its cash positions.
    public function cashPositions(): HasMany
    {
        return $this->hasMany(FinCashPosition::class, 'account_id', 'account_id');
    }
}
