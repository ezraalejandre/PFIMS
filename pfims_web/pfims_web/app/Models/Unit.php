<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'unit_tbl';
    protected $primaryKey = 'unit_id';
    public $timestamps = false;

    protected $fillable = [
        'unit_name',
    ];
}
