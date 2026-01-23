<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'production_started_at' => 'datetime',
        'production_finished_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Esp32Device::class, 'device_id', 'device_id');
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
}
