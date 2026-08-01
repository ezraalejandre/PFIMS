<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinExpense extends Model
{
    protected $table = 'fin_expense_tbl';
    protected $primaryKey = 'fin_expense_id';
    public $incrementing = true;
    protected $keyType = 'int';
    // Table has created_at / updated_at columns, so Eloquent timestamps apply.

    protected $fillable = [
        'project_id',
        'fin_category_id',
        'source_expense_id',
        'amount',
        'expense_date',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    // NOTE: adjust the referenced class/namespace below if your existing
    // Project and Expense models use different names.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinExpenseCategory::class, 'fin_category_id', 'fin_category_id');
    }

    public function sourceExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'source_expense_id', 'expense_id');
    }
}
