<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $table = 'inventory_transaction_tbl';
    protected $primaryKey = 'inventory_transaction_id';
    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'project_id',
        'transaction_type',
        'quantity',
        'transaction_date',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id', 'item_id');
    }
}
