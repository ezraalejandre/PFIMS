<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';

    protected $fillable = [
        'report_id',
        'title',
        'type',
        'role',
        'description',
        'file_name',
        'file_path',
        'file_size',
        'date_uploaded',
        'uploaded_by',
        'status',
        'user_id'
    ];

    protected $casts = [
        'date_uploaded' => 'date',
        'file_size' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope for filtering by role
    public function scopeForRole($query, $role)
    {
        if ($role === 'admin') {
            return $query; // Admin sees all
        }
        return $query->where('role', $role);
    }

    // Scope for filtering by type
    public function scopeOfType($query, $type)
    {
        if ($type === 'all') {
            return $query;
        }
        return $query->where('type', $type);
    }

    /**
     * Generate a unique report ID
     */
    public static function generateReportId()
    {
        $lastReport = self::orderBy('id', 'desc')->first();
        
        if ($lastReport) {
            // Extract the number from the last report_id
            $lastId = intval(str_replace('RPT-', '', $lastReport->report_id));
            $newId = $lastId + 1;
        } else {
            $newId = 1;
        }
        
        // Check if this ID already exists (just in case)
        while (self::where('report_id', 'RPT-' . str_pad($newId, 3, '0', STR_PAD_LEFT))->exists()) {
            $newId++;
        }
        
        return 'RPT-' . str_pad($newId, 3, '0', STR_PAD_LEFT);
    }
}