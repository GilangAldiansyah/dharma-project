<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Line extends Model
{
    use HasFactory;

    protected $fillable = [
        'line_code',
        'line_name',
        'plant',
        'qr_code',
        'status',
        'last_operation_start',
        'last_line_stop',
        'description',
        'total_operation_hours',
        'total_repair_hours',
        'uptime_hours',
        'total_failures',
        'is_archived',
        'period_start',
        'period_end',
        'parent_line_id',
        'current_period_start',
        'area_id',
    ];

    protected $casts = [
        'last_operation_start' => 'datetime',
        'last_line_stop' => 'datetime',
        'total_operation_hours' => 'decimal:4',
        'total_repair_hours' => 'decimal:4',
        'uptime_hours' => 'decimal:4',
        'is_archived' => 'boolean',
        'period_start' => 'date',
        'period_end' => 'date',
        'current_period_start' => 'datetime'
    ];

    protected $appends = ['average_mttr', 'average_mtbf'];

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(LineOperation::class);
    }

    public function currentOperation(): HasOne
    {
        return $this->hasOne(LineOperation::class)
            ->whereIn('status', ['running', 'paused'])
            ->latest('started_at');
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class);
    }

    public function archivedLines(): HasMany
    {
        return $this->hasMany(Line::class, 'parent_line_id')
            ->where('is_archived', true)
            ->orderBy('period_end', 'desc');
    }

    public function parentLine(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'parent_line_id');
    }

    public function esp32Device(): HasOne
    {
        return $this->hasOne(Esp32Device::class);
    }

    public function oeeRecords(): HasMany
    {
        return $this->hasMany(LineOeeRecord::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function latestOeeRecord(): HasOne
    {
        return $this->hasOne(LineOeeRecord::class)->latestOfMany('period_end');
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function getAverageMttrAttribute(): ?string
    {
        $periodStart = $this->current_period_start ?? $this->created_at;

        if (!$periodStart) {
            return null;
        }

        $completedReports = MaintenanceReport::query()
            ->whereHas('machine', function ($query) {
                $query->where('line_id', $this->id)
                      ->where('is_archived', false);
            })
            ->where('status', 'Selesai')
            ->whereNotNull('repair_duration_minutes')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $periodStart)
            ->get();

        if ($completedReports->isEmpty()) {
            return null;
        }

        $totalSeconds = $completedReports->sum(function($report) {
            return round($report->repair_duration_minutes * 60);
        });

        $avgSeconds = round($totalSeconds / $completedReports->count());

        $hours = floor($avgSeconds / 3600);
        $minutes = floor(($avgSeconds % 3600) / 60);
        $seconds = $avgSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getAverageMtbfAttribute(): ?float
    {
        if ($this->total_failures > 0 && $this->total_operation_hours > 0) {
            return round($this->total_operation_hours / $this->total_failures, 4);
        }

        return null;
    }

    public function getTotalLineStopsAttribute(): int
    {
        $periodStart = $this->current_period_start ?? $this->created_at;

        return $this->maintenanceReports()
            ->where('status', 'Selesai')
            ->where('created_at', '>=', $periodStart)
            ->count();
    }

    public function getActiveReportsCountAttribute(): int
    {
        return $this->maintenanceReports()
            ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
            ->count();
    }

    public function recalculateMetrics(): void
    {
        $periodStart = $this->current_period_start ?? $this->created_at;

        $totalOperationMinutes = $this->operations()
            ->where('status', 'stopped')
            ->where('created_at', '>=', $periodStart)
            ->sum('duration_minutes');

        $this->total_operation_hours = round($totalOperationMinutes / 60, 4);

        $reports = MaintenanceReport::query()
            ->whereHas('machine', function ($query) {
                $query->where('line_id', $this->id)
                    ->where('is_archived', false);
            })
            ->where('status', 'Selesai')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $periodStart)
            ->get();

        $totalRepairSeconds = $reports->sum(function($report) {
            return round($report->repair_duration_minutes * 60);
        });

        $this->total_repair_hours = round($totalRepairSeconds / 3600, 4);
        $this->uptime_hours = max(0, $this->total_operation_hours - $this->total_repair_hours);
        $this->uptime_hours = round($this->total_operation_hours - $this->total_repair_hours, 4);

        $this->total_failures = $reports->count();

        $this->save();

        $operationHours = $this->total_operation_hours;

        foreach ($this->machines()->where('is_archived', false)->get() as $machine) {
            $machine->update([
                'total_operation_hours' => $operationHours,
                'uptime_hours' => $this->uptime_hours
            ]);

            $machine->recalculateMetrics();
        }
    }

    public function resetMetrics(): void
    {
        DB::transaction(function () {
            $this->machines()->where('is_archived', false)->update([
                'total_operation_hours' => 0,
                'total_repair_hours' => 0,
                'total_failures' => 0,
                'mttr_hours' => null,
                'mtbf_hours' => null,
            ]);

            $this->update([
                'total_operation_hours' => 0,
                'total_repair_hours' => 0,
                'uptime_hours' => 0,
                'total_failures' => 0,
                'status' => 'stopped',
                'last_operation_start' => null,
                'last_line_stop' => null,
                'current_period_start' => now()
            ]);
        });
    }

    // ← TAMBAHAN: Method untuk auto archive & reset
    public function autoArchiveAndReset(string $reason = 'Auto-reset'): void
    {
        DB::transaction(function () use ($reason) {
            $now = Carbon::now();
            $timestamp = $now->format('YmdHis');

            // Archive line
            $archivedLine = $this->replicate();
            $archivedLine->is_archived = true;
            $archivedLine->period_start = $this->current_period_start ?? $this->created_at;
            $archivedLine->period_end = $now;
            $archivedLine->parent_line_id = $this->id;
            $archivedLine->line_code = $this->line_code . '_' . $timestamp;
            $archivedLine->qr_code = $this->qr_code . '_archived_' . $timestamp;
            $originalDesc = $this->description ?? '';
            $archivedLine->description = $originalDesc . "\n\n[ARCHIVED - " . $now->format('d M Y H:i:s') . "]\nAlasan: " . $reason;
            $archivedLine->save();

            // Archive machines
            foreach ($this->machines()->where('is_archived', false)->get() as $machine) {
                $archivedMachine = $machine->replicate();
                $archivedMachine->is_archived = true;
                $archivedMachine->period_start = $machine->current_period_start ?? $machine->created_at;
                $archivedMachine->period_end = $now;
                $archivedMachine->parent_machine_id = $machine->id;
                $archivedMachine->line_id = $archivedLine->id;
                $archivedMachine->barcode = $machine->barcode . '_archived_' . $timestamp;
                $archivedMachine->save();
            }

            // Reset metrics
            $this->update([
                'total_operation_hours' => 0,
                'total_repair_hours' => 0,
                'uptime_hours' => 0,
                'total_failures' => 0,
                'total_line_stops' => 0,
                'average_mtbf' => null,
                'average_mttr' => null,
                'current_period_start' => $now,
                'status' => 'stopped',
                'last_operation_start' => null,
            ]);

            $this->machines()->where('is_archived', false)->update([
                'total_operation_hours' => 0,
                'total_repair_hours' => 0,
                'total_failures' => 0,
                'mtbf_hours' => null,
                'mttr_hours' => null,
                'current_period_start' => $now
            ]);
        });
    }

    public function isOperating(): bool
    {
        return $this->status === 'operating';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    public static function generateQrCode(string $lineCode): string
    {
        return $lineCode;
    }

    public static function generateLineCode(string $plant): string
    {
        return DB::transaction(function () use ($plant) {
            $plantCode = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $plant), 0, 4));
            $lastLine = static::where('plant', $plant)
                ->where('is_archived', false)
                ->where('line_code', 'like', "L-{$plantCode}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(line_code, LENGTH(line_code) - 1) AS UNSIGNED) DESC')
                ->first();

            if ($lastLine) {
                preg_match('/L-' . preg_quote($plantCode, '/') . '-(\d+)$/', $lastLine->line_code, $matches);
                $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            $lineCode = "L-{$plantCode}-" . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
            $maxAttempts = 10;
            $attempt = 0;

            while (static::where('line_code', $lineCode)->exists() && $attempt < $maxAttempts) {
                $nextNumber++;
                $lineCode = "L-{$plantCode}-" . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
                $attempt++;
            }

            if ($attempt >= $maxAttempts) {
                throw new \Exception("Unable to generate unique line code after {$maxAttempts} attempts");
            }

            return $lineCode;
        });
    }
}
