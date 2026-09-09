<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProjectPhase extends Model { protected $table = 'project_phase_tbl'; protected $primaryKey = 'phase_id'; public $timestamps = false; protected $fillable = ['phase_name']; }
