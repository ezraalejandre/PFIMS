<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Link records to the fin project contract table.
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

    // Link this record to its project.
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    // Get total contract price attribute.
    public function getTotalContractPriceAttribute(): float
    {
        return (float) $this->original_contract_price + (float) $this->additional_works_contract;
    }

    // Get total payment attribute.
    public function getTotalPaymentAttribute(): float
    {
        return (float) $this->original_payment_received + (float) $this->additional_works_payment;
    }
}
