<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NgReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_number',
        'part_id',
        'ng_types',
        'ng_images',
        'notes',
        'reported_by',
        'reported_at',
        'pica_document',
        'pica_uploaded_at',
        'pica_uploaded_by',
        'status',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'pica_uploaded_at' => 'datetime',
        'ng_images' => 'array',
        'ng_types' => 'array', // Cast ke array untuk multiple selection
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_PICA_SUBMITTED = 'pica_submitted';
    const STATUS_CLOSED = 'closed';

    // NG Type constants
    const TYPE_FUNGSI = 'fungsi';
    const TYPE_DIMENSI = 'dimensi';
    const TYPE_TAMPILAN = 'tampilan';

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            if (!$report->report_number) {
                $report->report_number = 'NG-' . date('Ymd') . '-' . str_pad(static::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
            }

            if (!$report->reported_at) {
                $report->reported_at = now();
            }

            if (!$report->status) {
                $report->status = self::STATUS_OPEN;
            }
        });
    }

    public function getNgImageAttribute()
    {
        return $this->ng_images[0] ?? null;
    }

    public function getStatusBadgeAttribute()
    {
        return [
            'open' => ['label' => 'Open', 'color' => 'red'],
            'pica_submitted' => ['label' => 'PICA Submitted', 'color' => 'yellow'],
            'closed' => ['label' => 'Closed', 'color' => 'green'],
        ][$this->status] ?? ['label' => 'Unknown', 'color' => 'gray'];
    }

    public function getNgTypeConfigsAttribute()
    {
        $configs = [
            'fungsi' => ['label' => 'Fungsi', 'color' => 'purple', 'icon' => 'wrench'],
            'dimensi' => ['label' => 'Dimensi', 'color' => 'blue', 'icon' => 'ruler'],
            'tampilan' => ['label' => 'Tampilan', 'color' => 'orange', 'icon' => 'eye'],
        ];

        if (!$this->ng_types || !is_array($this->ng_types)) {
            return [];
        }

        return collect($this->ng_types)->map(function($type) use ($configs) {
            return $configs[$type] ?? ['label' => 'Unknown', 'color' => 'gray', 'icon' => 'alert-circle'];
        })->toArray();
    }

    public function getNgTypeLabelsAttribute()
    {
        $labels = [
            'fungsi' => 'Fungsi',
            'dimensi' => 'Dimensi',
            'tampilan' => 'Tampilan',
        ];

        if (!$this->ng_types || !is_array($this->ng_types)) {
            return [];
        }

        return collect($this->ng_types)->map(function($type) use ($labels) {
            return $labels[$type] ?? 'Unknown';
        })->toArray();
    }

    // Scope untuk filter berdasarkan NG Type (support multiple)
    public function scopeByNgType($query, $ngType)
    {
        if ($ngType && $ngType !== 'all') {
            return $query->whereJsonContains('ng_types', $ngType);
        }
        return $query;
    }

    // Scope untuk filter berdasarkan Status
    public function scopeByStatus($query, $status)
    {
        if ($status && $status !== 'all') {
            return $query->where('status', $status);
        }
        return $query;
    }

    // Helper method untuk check apakah memiliki NG type tertentu
    public function hasNgType($type)
    {
        return in_array($type, $this->ng_types ?? []);
    }
}
