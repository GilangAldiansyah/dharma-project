<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\Esp32ProductionHistory;
use App\Helpers\DateHelper;

class LineOeeRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_id',
        'period_type',
        'period_date',
        'period_start',
        'period_end',
        'shift',
        'operation_time_hours',
        'uptime_hours',
        'downtime_hours',
        'total_counter_a',
        'target_production',
        'total_reject',
        'good_count',
        'avg_cycle_time',
        'availability',
        'performance',
        'quality',
        'achievement_rate',
        'oee',
        'total_failures',
        'notes',
        'calculated_by',
    ];

    protected $casts = [
        'period_date' => 'date',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'operation_time_hours' => 'decimal:4',
        'uptime_hours' => 'decimal:4',
        'downtime_hours' => 'decimal:4',
        'avg_cycle_time' => 'decimal:4',
        'availability' => 'decimal:2',
        'performance' => 'decimal:2',
        'quality' => 'decimal:2',
        'achievement_rate' => 'decimal:2',
        'oee' => 'decimal:2',
    ];

    protected $appends = ['shift_label'];

    public function getShiftLabelAttribute(): string
    {
        return $this->shift ? DateHelper::getShiftLabel($this->shift) : 'N/A';
    }

    public function scopeByShift($query, int $shift)
    {
        return $query->where('shift', $shift);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public static function calculateOEE(
        Line $line,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $periodType = 'custom',
        ?string $calculatedBy = null
    ): ?self {
        $esp32Device = Esp32Device::where('line_id', $line->id)->first();

        if (!$esp32Device) {
            return null;
        }

        $productionHistories = Esp32ProductionHistory::where('device_id', $esp32Device->device_id)
            ->where('production_started_at', '>=', $periodStart)
            ->where('production_finished_at', '<=', $periodEnd)
            ->get();

        if ($productionHistories->isEmpty()) {
            return null;
        }

        $totalCounterA = $productionHistories->sum('total_counter_a');
        $totalReject = $productionHistories->sum('total_reject');
        $targetProduction = $productionHistories->sum('max_count');
        $avgCycleTime = $productionHistories->avg('cycle_time') ?? 0;

        if ($totalCounterA == 0) {
            return null;
        }

        $goodCount = $totalCounterA - $totalReject;

        $operations = LineOperation::where('line_id', $line->id)
            ->where('status', 'stopped')
            ->where('started_at', '>=', $periodStart)
            ->where('stopped_at', '<=', $periodEnd)
            ->get();

        $operationTimeMinutes = $operations->sum('duration_minutes');
        $operationTimeHours = $operationTimeMinutes / 60;

        if ($operationTimeHours == 0) {
            return null;
        }

        $downtimeHours = MaintenanceReport::query()
            ->whereHas('machine', function ($query) use ($line) {
                $query->where('line_id', $line->id)
                    ->where('is_archived', false);
            })
            ->where('status', 'Selesai')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $periodStart)
            ->where('completed_at', '<=', $periodEnd)
            ->get()
            ->sum(function($report) {
                return round($report->repair_duration_minutes * 60) / 3600;
            });

        $uptimeHours = max(0, $operationTimeHours - $downtimeHours);

        $availability = $operationTimeHours > 0
            ? ($uptimeHours / $operationTimeHours) * 100
            : 0;

        $uptimeSeconds = $uptimeHours * 3600;
        $performance = $uptimeSeconds > 0 && $avgCycleTime > 0
            ? (($avgCycleTime * $totalCounterA) / $uptimeSeconds) * 100
            : 0;

        $quality = $totalCounterA > 0
            ? ($goodCount / $totalCounterA) * 100
            : 0;

        $achievementRate = $targetProduction > 0
            ? ($totalCounterA / $targetProduction) * 100
            : 0;

        $oee = ($availability * $performance * $quality) / 10000;

        $totalFailures = MaintenanceReport::query()
            ->whereHas('machine', function ($query) use ($line) {
                $query->where('line_id', $line->id)
                    ->where('is_archived', false);
            })
            ->where('status', 'Selesai')
            ->where('completed_at', '>=', $periodStart)
            ->where('completed_at', '<=', $periodEnd)
            ->count();

        $periodDate = $periodStart->toDateString();

        $firstProduction = $productionHistories->sortBy('production_started_at')->first();
        $shift = $firstProduction->shift ?? DateHelper::getCurrentShift($firstProduction->production_started_at);

        if (!$shift) {
            $operation = LineOperation::where('line_id', $line->id)
                ->whereBetween('started_at', [$periodStart, $periodEnd])
                ->orderBy('started_at')
                ->first();
            $shift = $operation ? $operation->shift : DateHelper::getCurrentShift($periodStart);
        }

        return self::updateOrCreate(
            [
                'line_id' => $line->id,
                'period_type' => $periodType,
                'period_date' => $periodDate,
            ],
            [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'shift' => $shift,
                'operation_time_hours' => round($operationTimeHours, 4),
                'uptime_hours' => round($uptimeHours, 4),
                'downtime_hours' => round($downtimeHours, 4),
                'total_counter_a' => $totalCounterA,
                'target_production' => $targetProduction,
                'total_reject' => $totalReject,
                'good_count' => $goodCount,
                'avg_cycle_time' => round($avgCycleTime, 4),
                'availability' => round($availability, 2),
                'performance' => round($performance, 2),
                'quality' => round($quality, 2),
                'achievement_rate' => round($achievementRate, 2),
                'oee' => round($oee, 2),
                'total_failures' => $totalFailures,
                'calculated_by' => $calculatedBy ?? 'system',
            ]
        );
    }

    public function getOeeStatusAttribute(): string
    {
        if ($this->oee >= 85) return 'excellent';
        if ($this->oee >= 70) return 'good';
        if ($this->oee >= 50) return 'fair';
        return 'poor';
    }

    public function getOeeStatusLabelAttribute(): string
    {
        return match($this->oee_status) {
            'excellent' => 'Excellent (≥85%)',
            'good' => 'Good (70-84%)',
            'fair' => 'Fair (50-69%)',
            'poor' => 'Poor (<50%)',
            default => 'Unknown',
        };
    }

    public function scopeByLine($query, int $lineId)
    {
        return $query->where('line_id', $lineId);
    }

    public function scopeByPeriodType($query, string $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    public function scopeDateRange($query, Carbon $start, Carbon $end)
    {
        return $query->whereBetween('period_date', [$start, $end]);
    }

    public function scopeDaily($query)
    {
        return $query->where('period_type', 'daily');
    }

    public function scopeWeekly($query)
    {
        return $query->where('period_type', 'weekly');
    }

    public function scopeMonthly($query)
    {
        return $query->where('period_type', 'monthly');
    }
}
