<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\DateHelper;

class Esp32Log extends Model
{
    protected $fillable = [
        'device_id',
        'counter_a',
        'counter_b',
        'reject',
        'cycle_time',
        'max_count',
        'max_stroke',
        'loading_time',
        'production_started_at',
        'relay_status',
        'error_b',
        'logged_at',
        'shift', // ← TAMBAHAN
    ];

    protected $casts = [
        'relay_status' => 'boolean',
        'error_b' => 'boolean',
        'logged_at' => 'datetime',
        'production_started_at' => 'datetime',
    ];

    protected $appends = ['has_counter_b', 'shift_label']; // ← TAMBAHAN shift_label

    // ← TAMBAHAN: Auto-detect shift saat create
    protected static function booted()
    {
        static::creating(function ($log) {
            if ($log->logged_at) {
                $log->shift = DateHelper::getCurrentShift($log->logged_at);
            }
        });
    }

    public function getHasCounterBAttribute(): bool
    {
        return $this->counter_b > 0 || $this->max_stroke > 0;
    }

    // ← TAMBAHAN: Accessor untuk shift label
    public function getShiftLabelAttribute(): string
    {
        return $this->shift ? DateHelper::getShiftLabel($this->shift) : 'N/A';
    }

    // ← TAMBAHAN: Scope untuk filter by shift
    public function scopeByShift($query, int $shift)
    {
        return $query->where('shift', $shift);
    }
}
