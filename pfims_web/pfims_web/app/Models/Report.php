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
        'generation_method',
        'dataset_key',
        'export_format',
        'row_count',
        'selected_columns',
        'filters_applied',
        'export_options',
        'generated_at',
        'user_id',
    ];

    protected $casts = [
        'date_uploaded' => 'date',
        'file_size' => 'integer',
        'row_count' => 'integer',
        'selected_columns' => 'array',
        'filters_applied' => 'array',
        'export_options' => 'array',
        'generated_at' => 'datetime',
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

    public function scopeGenerated($query)
    {
        return $query->where('generation_method', 'system_export')
            ->whereNotNull('generated_at');
    }

    /**
     * Generate a unique report ID
     */
    public static function generateReportId()
    {
        do {
            $reportId = 'RPT-'.now()->format('Ymd').'-'.strtoupper(str()->random(6));
        } while (self::where('report_id', $reportId)->exists());

        return $reportId;
    }
}
