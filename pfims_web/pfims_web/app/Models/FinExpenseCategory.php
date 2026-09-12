<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinExpenseCategory extends Model
{
    protected $table = 'fin_expense_category_tbl';
    protected $primaryKey = 'fin_category_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'category_code',
        'category_name',
        'classification',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(FinExpense::class, 'fin_category_id', 'fin_category_id');
    }
}
