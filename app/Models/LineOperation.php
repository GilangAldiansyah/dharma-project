<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'operation_number',
        'started_at',
        'started_by',
        'stopped_at',
        'stopped_by',
        'paused_at',
        'resumed_at',
        'total_pause_minutes',
        'duration_minutes',
        'mtbf_hours',
        'status',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'mtbf_hours' => 'decimal:4',
        'total_pause_minutes' => 'decimal:4',
        'duration_minutes' => 'decimal:4',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function pause(string $pausedBy = 'System'): void
    {
        if ($this->status !== 'running') {
            return;
        }

        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
            'notes' => ($this->notes ? $this->notes . "\n" : '') . "Paused by {$pausedBy} at " . now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function resume(string $resumedBy = 'System'): void
    {
        if ($this->status !== 'paused') {
            return;
        }

        $pauseDurationSeconds = $this->paused_at->diffInSeconds(now());
        $pauseDurationMinutes = $pauseDurationSeconds / 60;

        $this->update([
            'status' => 'running',
            'resumed_at' => now(),
            'total_pause_minutes' => ($this->total_pause_minutes ?? 0) + $pauseDurationMinutes,
            'notes' => ($this->notes ? $this->notes . "\n" : '') .
                    "Resumed by {$resumedBy} at " . now()->format('Y-m-d H:i:s') .
                    " (paused for " . gmdate('i:s', $pauseDurationSeconds) . ")",
        ]);

        $this->paused_at = null;
        $this->save();
    }

    public function calculateMetrics(): void
    {
        if ($this->started_at && $this->stopped_at) {
            $totalSeconds = $this->started_at->diffInSeconds($this->stopped_at);
            $pauseMinutes = $this->total_pause_minutes ?? 0;
            $pauseSeconds = $pauseMinutes * 60;

            $netSeconds = max(0, $totalSeconds - $pauseSeconds);
            $netMinutes = $netSeconds / 60;

            $this->duration_minutes = $netMinutes;

            $failuresCount = $this->maintenanceReports()
                ->where('status', 'Selesai')
                ->count();

            if ($failuresCount > 0) {
                $this->mtbf_hours = $netSeconds / 3600 / $failuresCount;
            } else {
                $this->mtbf_hours = $netSeconds / 3600;
            }

            $this->save();
            $this->line->recalculateMetrics();
        }
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration_minutes) {
            return null;
        }

        $totalSeconds = $this->duration_minutes * 60;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = floor($totalSeconds % 60);

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedPauseDurationAttribute(): ?string
    {
        if (!$this->total_pause_minutes) {
            return null;
        }

        $totalSeconds = $this->total_pause_minutes * 60;
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = floor($totalSeconds % 60);

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getTotalRepairMinutesAttribute(): float
    {
        return $this->maintenanceReports()
            ->where('status', 'Selesai')
            ->sum('repair_duration_minutes') ?? 0;
    }

    public function getNetOperationMinutesAttribute(): float
    {
        if (!$this->started_at || !$this->stopped_at) {
            return 0;
        }

        $totalSeconds = $this->started_at->diffInSeconds($this->stopped_at);
        $pauseSeconds = ($this->total_pause_minutes ?? 0) * 60;

        return max(0, ($totalSeconds - $pauseSeconds) / 60);
    }

    public static function generateOperationNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return "OP-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopePaused($query)
    {
        return $query->where('status', 'paused');
    }

    public function scopeStopped($query)
    {
        return $query->where('status', 'stopped');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'stopped')
            ->whereNotNull('started_at')
            ->whereNotNull('stopped_at');
    }

    public function scopeByLine($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }
}
