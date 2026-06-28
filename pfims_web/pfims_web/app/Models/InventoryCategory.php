<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    protected $table = 'inventory_category_tbl';
    protected $primaryKey = 'inventory_category_id';
    public $timestamps = false;

    protected $fillable = [
        'inventory_category_name',
    ];
}
