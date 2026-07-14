<?php
// app/Models/Budget.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $table = 'budgets_tbl';
    protected $primaryKey = 'budget_id';
    public $timestamps = false;

    protected $fillable = [
        'project_id', 
        'budget_amount', 
        'actual_amount'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}