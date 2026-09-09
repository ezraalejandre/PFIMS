<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FinanceComponent extends Model { protected $table = 'fin_component_tbl'; protected $primaryKey = 'component_id'; public $timestamps = false; protected $fillable = ['component_name']; }
