<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinProjectContract extends Model
{
    protected $table = 'fin_project_contract_tbl';
    protected $primaryKey = 'contract_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'original_contract_price',
        'additional_works_contract',
        'original_payment_received',
        'additional_works_payment',
        'remarks',
    ];

    protected $casts = [
        'original_contract_price' => 'decimal:2',
        'additional_works_contract' => 'decimal:2',
        'original_payment_received' => 'decimal:2',
        'additional_works_payment' => 'decimal:2',
    ];

    // NOTE: adjust the referenced class/namespace below if your existing
    // Project model uses a different name.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    public function getTotalContractPriceAttribute(): float
    {
        return (float) $this->original_contract_price + (float) $this->additional_works_contract;
    }

    public function getTotalPaymentAttribute(): float
    {
        return (float) $this->original_payment_received + (float) $this->additional_works_payment;
    }
}
