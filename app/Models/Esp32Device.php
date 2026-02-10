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
        'is_paused',
        'paused_at',
        'total_pause_seconds',
        'last_update',
    ];

    protected $casts = [
        'relay_status' => 'boolean',
        'error_b' => 'boolean',
        'is_paused' => 'boolean',
        'last_update' => 'datetime',
        'production_started_at' => 'datetime',
        'paused_at' => 'datetime',
        'total_pause_seconds' => 'integer',
        'counter_a' => 'integer',
        'counter_b' => 'integer',
        'reject' => 'integer',
        'cycle_time' => 'integer',
        'max_count' => 'integer',
        'max_stroke' => 'integer',
        'loading_time' => 'integer',
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

        $totalPauseSeconds = (int) $this->total_pause_seconds;

        if ($this->is_paused && $this->paused_at) {
            $currentPauseSeconds = Carbon::parse($this->paused_at)->diffInSeconds(now());
            $totalPauseSeconds += $currentPauseSeconds;
        }

        return Carbon::parse($this->production_started_at)
            ->addSeconds((int) $this->loading_time)
            ->addSeconds($totalPauseSeconds);
    }

    public function getDelaySecondsAttribute(): int
    {
        if (!$this->production_started_at || $this->cycle_time == 0) return 0;

        $productionStarted = Carbon::parse($this->production_started_at);
        $actualCounter = (int) $this->counter_a;

        $totalPauseSeconds = (int) $this->total_pause_seconds;

        if ($this->is_paused && $this->paused_at) {
            $currentPauseSeconds = Carbon::parse($this->paused_at)->diffInSeconds(now());
            $totalPauseSeconds += $currentPauseSeconds;
        }

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
            $netTimeSeconds = $actualTimeSeconds - $totalPauseSeconds;
            $expectedTimeSeconds = $this->max_count * $this->cycle_time;

            return (int)($netTimeSeconds - $expectedTimeSeconds);
        }

        $lastUpdate = Carbon::parse($this->last_update);
        $elapsedSeconds = $lastUpdate->timestamp - $productionStarted->timestamp;
        $netElapsedSeconds = $elapsedSeconds - $totalPauseSeconds;
        $expectedCounter = floor($netElapsedSeconds / $this->cycle_time);
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

    // ← TAMBAHAN: Method untuk auto-start operation
    public function autoStartLineOperation(): ?LineOperation
    {
        if (!$this->line_id) {
            return null;
        }

        $line = $this->line;

        // Cek apakah sudah ada operation running
        $currentOperation = $line->currentOperation;

        if ($currentOperation) {
            return $currentOperation;
        }

        // Auto-create operation
        $operation = LineOperation::create([
            'line_id' => $this->line_id,
            'operation_number' => LineOperation::generateOperationNumber(),
            'started_at' => now(),
            'started_by' => 'System (Auto-started from ESP32: ' . $this->device_id . ')',
            'status' => 'running',
            'notes' => 'Operation otomatis dibuat dari ESP32 device: ' . $this->device_id,
        ]);

        $line->update([
            'status' => 'operating',
            'last_operation_start' => now(),
        ]);

        return $operation;
    }

    // ← TAMBAHAN: Method untuk auto-stop operation
    public function autoStopLineOperation(): void
    {
        if (!$this->line_id) {
            return;
        }

        $line = $this->line;
        $currentOperation = $line->currentOperation;

        if (!$currentOperation) {
            return;
        }

        // Stop operation
        $currentOperation->update([
            'stopped_at' => now(),
            'stopped_by' => 'System (Auto-stopped from ESP32: ' . $this->device_id . ')',
            'status' => 'stopped',
            'notes' => ($currentOperation->notes ? $currentOperation->notes . "\n" : '') .
                      'Operation otomatis dihentikan dari ESP32 device (counter reset to 0)',
        ]);

        $currentOperation->calculateMetrics();

        // Auto archive & reset
        $line->autoArchiveAndReset('Auto-reset from ESP32 device: ' . $this->device_id . ' (counter reset to 0)');
    }
}
