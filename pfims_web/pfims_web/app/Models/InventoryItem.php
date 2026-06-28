<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $table = 'inventory_item_tbl';
    protected $primaryKey = 'item_id';
    public $timestamps = false;

    protected $fillable = [
        'inventory_category_id',
        'supplier_id',
        'unit_id',
        'item_name',
        'current_stock',
        'reorder_level',
    ];

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id', 'inventory_category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id', 'item_id');
    }
}
