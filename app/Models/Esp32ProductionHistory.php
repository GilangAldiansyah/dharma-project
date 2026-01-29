<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateHelper;

class Esp32ProductionHistory extends Model
{
    protected $fillable = [
        'device_id',
        'total_counter_a',
        'total_counter_b',
        'total_reject',
        'cycle_time',
        'max_count',
        'max_stroke',
        'expected_time_seconds',
        'actual_time_seconds',
        'delay_seconds',
        'production_started_at',
        'production_finished_at',
        'completion_status',
        'shift', // ← TAMBAHAN
    ];

    protected $casts = [
        'production_started_at' => 'datetime',
        'production_finished_at' => 'datetime',
    ];

    protected $appends = ['shift_label']; // ← TAMBAHAN

    // ← TAMBAHAN: Auto-detect shift saat create
    protected static function booted()
    {
        static::creating(function ($history) {
            if ($history->production_started_at) {
                $history->shift = DateHelper::getCurrentShift($history->production_started_at);
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Esp32Device::class, 'device_id', 'device_id');
    }

    // ← TAMBAHAN: Accessor untuk shift label
    public function getShiftLabelAttribute(): string
    {
        return $this->shift ? DateHelper::getShiftLabel($this->shift) : 'N/A';
    }

    public function getFormattedDurationAttribute(): string
    {
        return $this->formatTime($this->actual_time_seconds);
    }

    public function getFormattedDelayAttribute(): string
    {
        return $this->formatTime(abs($this->delay_seconds));
    }

    private function formatTime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m {$secs}s";
        } elseif ($minutes > 0) {
            return "{$minutes}m {$secs}s";
        }
        return "{$secs}s";
    }

    // ← TAMBAHAN: Scope untuk filter by shift
    public function scopeByShift($query, int $shift)
    {
        return $query->where('shift', $shift);
    }
}
