<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Machine extends Model
{
    protected $fillable = [
        'line_id',
        'machine_name',
        'barcode',
        'plant',
        'line',
        'machine_type',
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
            ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
            ->orderBy('reported_at', 'desc');
    }

    public function completedReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class)
            ->where('status', 'Selesai')
            ->orderBy('completed_at', 'desc');
    }

    public function getMttrAttribute(): ?float
    {
        $completedReports = $this->maintenanceReports()
            ->where('status', 'Selesai')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedReports->isEmpty()) {
            return null;
        }

        $totalMinutes = $completedReports->sum(function ($report) {
            return $report->started_at->diffInMinutes($report->completed_at);
        });

        return round($totalMinutes / $completedReports->count(), 2);
    }

    public function getMttrFormattedAttribute(): ?string
    {
        $minutes = $this->mttr;

        if ($minutes === null) {
            return null;
        }

        $hours = floor($minutes / 60);
        $mins = floor($minutes % 60);
        $secs = round(($minutes - floor($minutes)) * 60);

        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
    }

    public function getTotalDowntimeAttribute(): int
    {
        return $this->maintenanceReports()
            ->where('status', 'Selesai')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->whereMonth('reported_at', now()->month)
            ->whereYear('reported_at', now()->year)
            ->get()
            ->sum(function ($report) {
                return $report->started_at->diffInMinutes($report->completed_at);
            });
    }

    public function scopeByLineId($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }
    public function scopeByPlant($query, string $plant)
    {
        return $query->where('plant', $plant);
    }

    public function isLineOperating(): bool
    {
        return $this->lineModel && $this->lineModel->isOperating();
    }
}
