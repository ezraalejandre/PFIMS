<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinEquipmentRentalIncome extends Model
{
    protected $table = 'fin_equipment_rental_income_tbl';
    protected $primaryKey = 'rental_income_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'project_id',
        'period_month',
        'amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_month' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CompanyAsset::class, 'asset_id', 'asset_id');
    }

    // NOTE: adjust the referenced class/namespace below if your existing
    // Project model uses a different name.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
