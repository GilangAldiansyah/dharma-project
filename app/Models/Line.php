<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

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
    ];

    protected $casts = [
        'last_operation_start' => 'datetime',
        'last_line_stop' => 'datetime',
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
                    ->where('status', 'running')
                    ->latest('started_at');
    }

    public function maintenanceReports(): HasMany
    {
        return $this->hasMany(MaintenanceReport::class);
    }

    public function isOperating(): bool
    {
        return $this->status === 'operating';
    }

    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Get average MTTR (Mean Time To Repair) for this line
     * Format: HH:MM:SS
     */
    public function getAverageMttrAttribute(): ?string
    {
        $completedReports = $this->maintenanceReports()
            ->where('status', 'Selesai')
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedReports->isEmpty()) {
            return null;
        }

        // Calculate total seconds for all repairs
        $totalSeconds = $completedReports->sum(function ($report) {
            return $report->started_at->diffInSeconds($report->completed_at);
        });

        // Calculate average
        $avgSeconds = $totalSeconds / $completedReports->count();

        // Format as HH:MM:SS
        $hours = floor($avgSeconds / 3600);
        $mins = floor(($avgSeconds % 3600) / 60);
        $secs = floor($avgSeconds % 60);

        return sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
    }

    /**
     * Get average MTBF (Mean Time Between Failures) for this line
     * Returns hours as float
     */
    public function getAverageMtbfAttribute(): ?float
    {
        $completedOps = $this->operations()
                             ->where('status', 'stopped')
                             ->whereNotNull('mtbf_hours')
                             ->get();

        if ($completedOps->isEmpty()) {
            return null;
        }

        return round($completedOps->avg('mtbf_hours'), 2);
    }

    public function getTotalLineStopsAttribute(): int
    {
        return $this->maintenanceReports()->count();
    }

    public function getActiveReportsCountAttribute(): int
    {
        return $this->maintenanceReports()
                    ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
                    ->count();
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
                              ->where('line_code', 'like', "LINE-{$plantCode}-%")
                              ->lockForUpdate()
                              ->orderByRaw('CAST(SUBSTRING(line_code, LENGTH(line_code) - 1) AS UNSIGNED) DESC')
                              ->first();

            if ($lastLine) {
                preg_match('/LINE-' . preg_quote($plantCode, '/') . '-(\d+)$/', $lastLine->line_code, $matches);
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
