<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
// Link records to the project manager table.
class ProjectManager extends Model
{
    protected $table = 'project_manager_tbl';
    protected $primaryKey = 'manager_id';
    public $timestamps = false;
    protected $fillable = ['manager_name'];
}
