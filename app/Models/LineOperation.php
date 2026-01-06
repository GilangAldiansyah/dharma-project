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
        'duration_minutes',
        'mtbf_hours',
        'status',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'mtbf_hours' => 'decimal:2',
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

    public function calculateMetrics(): void
    {
        if ($this->started_at && $this->stopped_at) {
            $this->duration_minutes = $this->started_at->diffInMinutes($this->stopped_at);
            $this->mtbf_hours = round($this->duration_minutes / 60, 2);
            $this->save();
        }
    }

    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration_minutes) {
            return null;
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }

    /**
     * Generate unique operation number
     */
    public static function generateOperationNumber(): string
    {
        $date = now()->format('Ymd');
        $count = static::whereDate('created_at', today())->count() + 1;
        return "OP-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope: Running operations
     */
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope: Stopped operations
     */
    public function scopeStopped($query)
    {
        return $query->where('status', 'stopped');
    }

    /**
     * Scope: Completed operations (stopped with both dates recorded)
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'stopped')
            ->whereNotNull('started_at')
            ->whereNotNull('stopped_at');
    }

    /**
     * Scope: By line
     */
    public function scopeByLine($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }
}
