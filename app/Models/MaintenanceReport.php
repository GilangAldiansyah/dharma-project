<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateHelper;

class MaintenanceReport extends Model
{
    protected $fillable = [
        'line_id',
        'machine_id',
        'line_operation_id',
        'report_number',
        'problem',
        'status',
        'reported_by',
        'reported_at',
        'line_stopped_at',
        'started_at',
        'completed_at',
        'repair_duration_minutes',
        'line_stop_duration_minutes',
        'shift', // ← TAMBAHAN
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'line_stopped_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'repair_duration_minutes' => 'decimal:4',
        'line_stop_duration_minutes' => 'decimal:4',
    ];

    protected $appends = ['shift_label']; // ← TAMBAHAN

    // ← TAMBAHAN: Auto-detect shift saat create
    protected static function booted()
    {
        static::creating(function ($report) {
            if ($report->reported_at) {
                $report->shift = DateHelper::getCurrentShift($report->reported_at);
            }
        });
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public function lineOperation(): BelongsTo
    {
        return $this->belongsTo(LineOperation::class);
    }

    // ← TAMBAHAN: Accessor untuk shift label
    public function getShiftLabelAttribute(): string
    {
        return $this->shift ? DateHelper::getShiftLabel($this->shift) : 'N/A';
    }

    public function getRepairDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    public function getRepairDurationFormattedAttribute(): ?string
    {
        $seconds = $this->repair_duration;

        if ($seconds === null) {
            return null;
        }

        $hours = floor($seconds / 3600);
        $mins = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
    }

    public function getLineStopDurationAttribute(): ?int
    {
        if (!$this->line_stopped_at || !$this->completed_at) {
            return null;
        }

        return $this->line_stopped_at->diffInSeconds($this->completed_at);
    }

    public function getLineStopDurationFormattedAttribute(): ?string
    {
        $seconds = $this->line_stop_duration;

        if ($seconds === null) {
            return null;
        }

        $hours = floor($seconds / 3600);
        $mins = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
    }

    public function calculateRepairDuration(): void
    {
        if ($this->started_at && $this->completed_at) {
            $seconds = $this->started_at->diffInSeconds($this->completed_at);
            $this->repair_duration_minutes = round($seconds / 60, 4);
            $this->save();
        }
    }

    public function calculateLineStopDuration(): void
    {
        if ($this->line_stopped_at && $this->completed_at) {
            $seconds = $this->line_stopped_at->diffInSeconds($this->completed_at);
            $this->line_stop_duration_minutes = round($seconds / 60, 4);
        }
    }

    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['Dilaporkan', 'Sedang Diperbaiki']);
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'Selesai';
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'Selesai');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPlant($query, string $plant)
    {
        return $query->whereHas('line', function ($q) use ($plant) {
            $q->where('plant', $plant);
        });
    }

    public function scopeByLineId($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeByLine($query, string $line)
    {
        return $query->whereHas('machine', function ($q) use ($line) {
            $q->where('line', $line);
        });
    }

    // ← TAMBAHAN: Scope untuk filter by shift
    public function scopeByShift($query, int $shift)
    {
        return $query->where('shift', $shift);
    }

    public static function generateReportNumber(): string
    {
        $year  = date('y');
        $month = date('m');

        $lastReport = self::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->latest('id')
            ->first();

        $sequence = $lastReport
            ? ((int) substr($lastReport->report_number, strrpos($lastReport->report_number, '-') + 1)) + 1
            : 1;

        return "MNT-{$year}{$month}-{$sequence}";
    }
}
