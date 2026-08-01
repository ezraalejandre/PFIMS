<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinConstructionBond extends Model
{
    protected $table = 'fin_construction_bond_tbl';
    protected $primaryKey = 'bond_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'bond_date',
        'amount',
        'bond_provider',
        'status',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bond_date' => 'date',
    ];

    // NOTE: adjust the referenced class/namespace below if your existing
    // Project model uses a different name.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
