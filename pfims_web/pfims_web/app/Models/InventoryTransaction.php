<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Link records to the inventory transaction table.
class InventoryTransaction extends Model
{
    protected $table = 'inventory_transaction_tbl';
    protected $primaryKey = 'inventory_transaction_id';
    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'project_id',
        'transaction_type',
        'movement_reason',
        'recorded_at',
        'quantity',
        'bar_code',
        'transaction_date',
        'proof_file_path',
        'proof_file_name',
    ];

    // Link this record to its item.
    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id', 'item_id');
    }

    // Link this record to its project.
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
