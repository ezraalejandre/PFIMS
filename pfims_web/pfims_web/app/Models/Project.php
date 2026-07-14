<?php
// app/Models/Project.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $table = 'project_tbl';
    protected $primaryKey = 'project_id';
    public $timestamps = false;

    protected $fillable = [
        'project_name', 
        'client_name', 
        'project_manager',
        'start_date', 
        'estimated_end_date', 
        'actual_end_date',
        'worker_count', 
        'phase', 
        'completion_percentage', 
        'status'
    ];

    // Relationship with budget
    public function budget()
    {
        return $this->hasOne(Budget::class, 'project_id', 'project_id');
    }

    // Relationship with expenses
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'project_id', 'project_id');
    }
}