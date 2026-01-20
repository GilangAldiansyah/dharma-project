<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Machine extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'machine_name',
        'barcode',
        'plant',
        'line',
        'machine_type',
        'total_operation_hours',
        'total_repair_hours',
        'total_failures',
        'mttr_hours',
        'mtbf_hours',
        'is_archived',
        'period_start',
        'period_end',
        'parent_machine_id',
        'current_period_start'
    ];

    protected $casts = [
        'total_operation_hours' => 'decimal:4',
        'total_repair_hours' => 'decimal:4',
        'mttr_hours' => 'decimal:4',
        'mtbf_hours' => 'decimal:4',
        'is_archived' => 'boolean',
        'period_start' => 'date',
        'period_end' => 'date',
        'current_period_start' => 'datetime'
    ];

    public function lineModel(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'line_id');
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class);
    }

    public function activeReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class)
            ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki']);
    }

    public function archivedMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'parent_machine_id')
            ->where('is_archived', true)
            ->orderBy('period_end', 'desc');
    }

    public function parentMachine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'parent_machine_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function recalculateMetrics(): void
{
    $line = $this->lineModel;
    $periodStart = $line ? ($line->current_period_start ?? $line->created_at) : ($this->current_period_start ?? $this->created_at);

    $completedReports = $this->maintenanceReports()
        ->where('status', 'Selesai')
        ->whereNotNull('started_at')
        ->whereNotNull('completed_at')
        ->where('completed_at', '>=', $periodStart)
        ->get();

    if ($completedReports->isNotEmpty()) {
        $totalRepairSeconds = $completedReports->sum(function($report) {
            return round($report->repair_duration_minutes * 60);
        });

        $this->total_repair_hours = round($totalRepairSeconds / 3600, 4);
        $this->mttr_hours = round($totalRepairSeconds / 3600 / $completedReports->count(), 4);
        $this->total_failures = $completedReports->count();
    } else {
        $this->total_repair_hours = 0;
        $this->mttr_hours = null;
        $this->total_failures = 0;
    }

    if ($this->total_failures > 0 && $this->total_operation_hours > 0) {
        $this->mtbf_hours = round($this->total_operation_hours / $this->total_failures, 4);
    } else {
        $this->mtbf_hours = null;
    }

    $this->save();
}

    public function resetMetrics(): void
    {
        $this->update([
            'total_operation_hours' => 0,
            'total_repair_hours' => 0,
            'total_failures' => 0,
            'mttr_hours' => null,
            'mtbf_hours' => null,
            'current_period_start' => now()
        ]);
    }

    public function getMttrFormattedAttribute(): ?string
    {
        if (!$this->mttr_hours) {
            return null;
        }

        // ✅ Convert dari jam desimal ke detik untuk format akurat
        $totalSeconds = $this->mttr_hours * 3600;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = floor($totalSeconds % 60);

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getMtbfFormattedAttribute(): ?string
    {
        if (!$this->mtbf_hours) {
            return null;
        }

        $totalSeconds = $this->mtbf_hours * 3600;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = floor($totalSeconds % 60);

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getUptimeHoursAttribute(): float
    {
        return max(0, $this->total_operation_hours - $this->total_repair_hours);
    }

    public function getAvailabilityPercentageAttribute(): ?float
    {
        if ($this->total_operation_hours <= 0) {
            return null;
        }

        $uptime = $this->total_operation_hours - $this->total_repair_hours;
        return round(($uptime / $this->total_operation_hours) * 100, 2);
    }
}
