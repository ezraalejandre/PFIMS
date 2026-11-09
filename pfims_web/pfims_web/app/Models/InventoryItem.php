<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Link records to the inventory item table.
class InventoryItem extends Model
{
    protected $table = 'inventory_item_tbl';
    protected $primaryKey = 'item_id';
    public $timestamps = false;

    protected $fillable = [
        'inventory_category_id',
        'supplier_id',
        'unit_id',
        'unit_price',
        'item_name',
        'current_stock',
        'reorder_level',
    ];

    // Link this record to its category.
    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id', 'inventory_category_id');
    }

    // Link this record to its supplier.
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    // Link this record to its unit.
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'unit_id');
    }

    // Link this record to its transactions.
    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class, 'item_id', 'item_id');
    }
}
