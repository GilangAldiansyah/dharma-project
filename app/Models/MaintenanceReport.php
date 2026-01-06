<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'line_stopped_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the line this report belongs to
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    /**
     * Get the machine this report is about
     */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Get the line operation this report belongs to
     */
    public function lineOperation(): BelongsTo
    {
        return $this->belongsTo(LineOperation::class);
    }

    /**
     * Get repair duration in seconds (started_at -> completed_at)
     */
    public function getRepairDurationAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get repair duration formatted as HH:MM:SS
     */
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

    /**
     * Get line stop duration in seconds (line_stopped_at -> completed_at)
     */
    public function getLineStopDurationAttribute(): ?int
    {
        if (!$this->line_stopped_at || !$this->completed_at) {
            return null;
        }

        return $this->line_stopped_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get line stop duration formatted as HH:MM:SS
     */
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

    /**
     * Calculate and update repair duration when completed
     */
    public function calculateRepairDuration(): void
    {
        if ($this->started_at && $this->completed_at) {
            $this->repair_duration_minutes = $this->started_at->diffInMinutes($this->completed_at);
            $this->save();
        }
    }

    /**
     * Calculate and update line stop duration when completed
     */
    public function calculateLineStopDuration(): void
    {
        if ($this->line_stopped_at && $this->completed_at) {
            $this->line_stop_duration_minutes = $this->line_stopped_at->diffInMinutes($this->completed_at);
            $this->save();
        }
    }

    /**
     * Check if report is active
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['Dilaporkan', 'Sedang Diperbaiki']);
    }

    /**
     * Check if report is completed
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'Selesai';
    }

    /**
     * Scope: Active reports
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki']);
    }

    /**
     * Scope: Completed reports
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Selesai');
    }

    /**
     * Scope: By status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: By plant
     */
    public function scopeByPlant($query, string $plant)
    {
        return $query->whereHas('line', function ($q) use ($plant) {
            $q->where('plant', $plant);
        });
    }

    /**
     * Scope: By line ID
     */
    public function scopeByLineId($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    /**
     * Scope: By line string (legacy)
     */
    public function scopeByLine($query, string $line)
    {
        return $query->whereHas('machine', function ($q) use ($line) {
            $q->where('line', $line);
        });
    }
    public static function generateReportNumber(): string
    {
        $year  = date('y'); // 2 digit
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
