<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'expense_tbl';
    protected $primaryKey = 'expense_id';
    public $timestamps = false;

    protected $fillable = [
        'project_id', 'expense_category_id', 'inventory_transaction_id', 'unit_id',
        'expense_description', 'labor_amount', 'material_amount',
        'equipment_amount', 'other_amount', 'expense_date', 'remarks',
        'proof_file_path', 'proof_file_name',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id', 'expense_category_id');
    }
}