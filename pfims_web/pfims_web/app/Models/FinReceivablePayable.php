<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinReceivablePayable extends Model
{
    protected $table = 'fin_receivable_payable_tbl';
    protected $primaryKey = 'rp_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'entry_type',
        'project_id',
        'counterparty_name',
        'entry_date',
        'amount_30d',
        'amount_31_60d',
        'amount_61_90d',
        'amount_91_120d',
        'status',
        'remarks',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount_30d' => 'decimal:2',
        'amount_31_60d' => 'decimal:2',
        'amount_61_90d' => 'decimal:2',
        'amount_91_120d' => 'decimal:2',
    ];

    // NOTE: adjust the referenced class/namespace below if your existing
    // Project model uses a different name.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    /**
     * R Total for this row (aging buckets summed).
     */
    public function getRowTotalAttribute(): float
    {
        return (float) $this->amount_30d
            + (float) $this->amount_31_60d
            + (float) $this->amount_61_90d
            + (float) $this->amount_91_120d;
    }
}
