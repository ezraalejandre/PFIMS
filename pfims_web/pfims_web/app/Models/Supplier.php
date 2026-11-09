<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Link records to the supplier table.
class Supplier extends Model
{
    protected $table = 'supplier_tbl';
    protected $primaryKey = 'supplier_id';
    public $timestamps = false;

    protected $fillable = [
        'supplier_name',
        'address',
        'contact_number',
    ];
}
