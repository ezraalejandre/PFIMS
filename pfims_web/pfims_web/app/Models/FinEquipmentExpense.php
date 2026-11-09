<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Link records to the fin equipment expense table.
class FinEquipmentExpense extends Model
{
    protected $table = 'fin_equipment_expense_tbl';
    protected $primaryKey = 'equip_expense_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'project_id',
        'expense_type',
        'amount',
        'expense_date',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    // Link this record to its asset.
    public function asset(): BelongsTo
    {
        return $this->belongsTo(CompanyAsset::class, 'asset_id', 'asset_id');
    }

    // Link this record to its project.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
