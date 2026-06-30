<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $table = 'expense_category_tbl';
    protected $primaryKey = 'expense_category_id';
    public $timestamps = false;

    protected $fillable = [
        'category_name',
    ];
}
