<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyAsset extends Model
{
    protected $table = 'company_asset_tbl';
    protected $primaryKey = 'asset_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'asset_name',
        'asset_type',
        'asset_code',
        'acquisition_cost',
        'status',
    ];

    protected $casts = [
        'acquisition_cost' => 'decimal:2',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(FinEquipmentExpense::class, 'asset_id', 'asset_id');
    }

    public function rentalIncome(): HasMany
    {
        return $this->hasMany(FinEquipmentRentalIncome::class, 'asset_id', 'asset_id');
    }
}
