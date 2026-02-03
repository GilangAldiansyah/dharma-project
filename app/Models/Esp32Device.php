<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Esp32Device extends Model
{
    protected $fillable = [
        'device_id',
        'line_id',
        'area_id',
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
        'last_update',
    ];

    protected $casts = [
        'relay_status' => 'boolean',
        'error_b' => 'boolean',
        'last_update' => 'datetime',
        'production_started_at' => 'datetime',
    ];

    protected $appends = [
        'progress_percentage',
        'expected_finish_time',
        'delay_seconds',
        'is_delayed',
        'is_completed',
        'has_counter_b'
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(Esp32Log::class, 'device_id', 'device_id');
    }

    public function productionHistories(): HasMany
    {
        return $this->hasMany(Esp32ProductionHistory::class, 'device_id', 'device_id');
    }

    public function getHasCounterBAttribute(): bool
    {
        return $this->counter_b > 0 || $this->max_stroke > 0;
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->max_count == 0) return 0;
        return min(($this->counter_a / $this->max_count) * 100, 100);
    }

    public function getExpectedFinishTimeAttribute()
    {
        if (!$this->production_started_at) return null;
        return Carbon::parse($this->production_started_at)->addSeconds($this->loading_time);
    }

    public function getDelaySecondsAttribute(): int
    {
        if (!$this->production_started_at || $this->cycle_time == 0) return 0;

        $productionStarted = Carbon::parse($this->production_started_at);
        $actualCounter = $this->counter_a;

        if ($actualCounter >= $this->max_count) {
            $completionLog = $this->logs()
                ->where('counter_a', '>=', $this->max_count)
                ->orderBy('logged_at', 'asc')
                ->first();

            if ($completionLog) {
                $completionTime = Carbon::parse($completionLog->logged_at);
            } else {
                $completionTime = Carbon::parse($this->last_update);
            }

            $actualTimeSeconds = $completionTime->timestamp - $productionStarted->timestamp;
            $expectedTimeSeconds = $this->max_count * $this->cycle_time;
            return (int)($actualTimeSeconds - $expectedTimeSeconds);
        }

        $lastUpdate = Carbon::parse($this->last_update);
        $elapsedSeconds = $lastUpdate->timestamp - $productionStarted->timestamp;
        $expectedCounter = floor($elapsedSeconds / $this->cycle_time);
        $counterDiff = $expectedCounter - $actualCounter;

        return (int)($counterDiff * $this->cycle_time);
    }

    public function getIsDelayedAttribute(): bool
    {
        return $this->delay_seconds > $this->cycle_time;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->counter_a >= $this->max_count;
    }
}
