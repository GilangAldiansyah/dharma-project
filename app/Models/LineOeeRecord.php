<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LineOeeRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'period_type',
        'period_date',
        'period_start',
        'period_end',
        'shift',
        'operation_time_hours',
        'uptime_hours',
        'downtime_hours',
        'total_counter_a',
        'target_production',
        'total_reject',
        'good_count',
        'avg_cycle_time',
        'availability',
        'performance',
        'quality',
        'achievement_rate',
        'oee',
        'total_failures',
        'notes',
        'calculated_by',
    ];

    protected $casts = [
        'period_date' => 'date',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'operation_time_hours' => 'decimal:4',
        'uptime_hours' => 'decimal:4',
        'downtime_hours' => 'decimal:4',
        'avg_cycle_time' => 'decimal:4',
        'availability' => 'decimal:2',
        'performance' => 'decimal:2',
        'quality' => 'decimal:2',
        'achievement_rate' => 'decimal:2',
        'oee' => 'decimal:2',
    ];

    protected $appends = ['shift_label'];

    public function getShiftLabelAttribute(): string
    {
        return $this->shift ? \App\Helpers\DateHelper::getShiftLabel($this->shift) : 'N/A';
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function getOeeStatusAttribute(): string
    {
        if ($this->oee >= 85) return 'excellent';
        if ($this->oee >= 70) return 'good';
        if ($this->oee >= 50) return 'fair';
        return 'poor';
    }

    public function getOeeStatusLabelAttribute(): string
    {
        return match($this->oee_status) {
            'excellent' => 'Excellent (≥85%)',
            'good' => 'Good (70-84%)',
            'fair' => 'Fair (50-69%)',
            'poor' => 'Poor (<50%)',
            default => 'Unknown',
        };
    }

    public function scopeByLine($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeByPeriodType($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('period_date', [$start, $end]);
    }

    public function scopeByShift($query, int $shift)
    {
        return $query->where('shift', $shift);
    }

    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }

    public function scopeWeekly($query)
    {
        return $query->where('period_type', 'weekly');
    }

    public function scopeMonthly($query)
    {
        return $query->where('period_type', 'monthly');
    }
}
