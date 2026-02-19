<?php

namespace App\Services;

use App\Models\Line;
use App\Models\LineOperation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LineScheduleService
{
    public function checkAndApplySchedule(Line $line): void
    {
        $operation = $line->currentOperation;

        if (!$operation || $operation->status === 'stopped') {
            return;
        }

        $now = Carbon::now();
        $isInBreakTime = $this->isInBreakTime($line, $now);

        if ($isInBreakTime && $operation->status === 'running') {
            $this->autoPause($operation, $line);
        } elseif (!$isInBreakTime && $operation->status === 'paused' && $operation->is_auto_paused) {
            $this->autoResume($operation, $line);
        }
    }

    public function checkAllRunningOperations(): void
    {
        $runningOperations = LineOperation::whereIn('status', ['running', 'paused'])
            ->with('line')
            ->get();

        foreach ($runningOperations as $operation) {
            if ($operation->line) {
                $this->checkAndApplySchedule($operation->line);
            }
        }
    }

    public function isInBreakTime(Line $line, Carbon $time = null): bool
    {
        $time = $time ?? Carbon::now();

        if (!$line->schedule_breaks || empty($line->schedule_breaks)) {
            return false;
        }

        $currentTime = $time->format('H:i:s');

        foreach ($line->schedule_breaks as $break) {
            $breakStart = $break['start'];
            $breakEnd = $break['end'];

            if ($currentTime >= $breakStart && $currentTime < $breakEnd) {
                return true;
            }
        }

        return false;
    }

    public function getNextBreakInfo(Line $line, Carbon $time = null): ?array
    {
        $time = $time ?? Carbon::now();

        if (!$line->schedule_breaks || empty($line->schedule_breaks)) {
            return null;
        }

        $currentTime = $time->format('H:i:s');
        $nextBreak = null;
        $smallestDiff = PHP_INT_MAX;

        foreach ($line->schedule_breaks as $break) {
            $breakStart = $break['start'];

            if ($breakStart > $currentTime) {
                $startTime = Carbon::parse($breakStart);
                $diff = $time->diffInMinutes($startTime, false);

                if ($diff < $smallestDiff) {
                    $smallestDiff = $diff;
                    $nextBreak = [
                        'start' => $breakStart,
                        'end' => $break['end'],
                        'minutes_until' => abs($diff),
                    ];
                }
            }
        }

        return $nextBreak;
    }

    public function getCurrentBreakInfo(Line $line, Carbon $time = null): ?array
    {
        $time = $time ?? Carbon::now();

        if (!$this->isInBreakTime($line, $time)) {
            return null;
        }

        $currentTime = $time->format('H:i:s');

        foreach ($line->schedule_breaks as $break) {
            $breakStart = $break['start'];
            $breakEnd = $break['end'];

            if ($currentTime >= $breakStart && $currentTime < $breakEnd) {
                $endTime = Carbon::parse($breakEnd);
                $minutesRemaining = $time->diffInMinutes($endTime, false);

                return [
                    'start' => $breakStart,
                    'end' => $breakEnd,
                    'minutes_remaining' => abs($minutesRemaining),
                ];
            }
        }

        return null;
    }

    protected function autoPause(LineOperation $operation, Line $line): void
    {
        try {
            $pauseHistory = $operation->pause_history ?? [];

            $pauseHistory[] = [
                'type' => 'auto_pause',
                'paused_at' => now()->toIso8601String(),
                'reason' => 'Scheduled break time',
            ];

            $operation->update([
                'status' => 'paused',
                'paused_at' => now(),
                'is_auto_paused' => true,
                'pause_history' => $pauseHistory,
                'notes' => ($operation->notes ? $operation->notes . "\n" : '') .
                          "Auto-paused at " . now()->format('Y-m-d H:i:s') . " (Scheduled break)",
            ]);

            $line->update(['status' => 'paused']);

            $esp32Device = $line->esp32Device;
            if ($esp32Device && !$esp32Device->is_paused) {
                $esp32Device->update([
                    'is_paused' => true,
                    'paused_at' => now(),
                ]);

                \App\Models\Esp32Log::create([
                    'device_id' => $esp32Device->device_id,
                    'counter_a' => $esp32Device->counter_a,
                    'counter_b' => $esp32Device->counter_b,
                    'reject' => $esp32Device->reject,
                    'cycle_time' => $esp32Device->cycle_time,
                    'max_count' => $esp32Device->max_count,
                    'max_stroke' => $esp32Device->max_stroke,
                    'loading_time' => $esp32Device->loading_time,
                    'production_started_at' => $esp32Device->production_started_at,
                    'relay_status' => $esp32Device->relay_status,
                    'error_b' => $esp32Device->error_b,
                    'is_paused' => true,
                    'paused_at' => now(),
                    'total_pause_seconds' => $esp32Device->total_pause_seconds,
                    'logged_at' => now(),
                ]);
            }

            Log::info("Auto-paused operation {$operation->operation_number} for line {$line->line_code}");
        } catch (\Exception $e) {
            Log::error("Auto-pause failed for operation {$operation->id}: " . $e->getMessage());
        }
    }

    protected function autoResume(LineOperation $operation, Line $line): void
    {
        try {
            if (!$operation->paused_at) {
                return;
            }

            $pauseDurationSeconds = $operation->paused_at->diffInSeconds(now());
            $pauseDurationMinutes = $pauseDurationSeconds / 60;

            $pauseHistory = $operation->pause_history ?? [];

            if (!empty($pauseHistory)) {
                $lastPauseIndex = count($pauseHistory) - 1;
                $pauseHistory[$lastPauseIndex]['resumed_at'] = now()->toIso8601String();
                $pauseHistory[$lastPauseIndex]['duration_minutes'] = round($pauseDurationMinutes, 2);
            }

            $operation->update([
                'status' => 'running',
                'resumed_at' => now(),
                'total_pause_minutes' => ($operation->total_pause_minutes ?? 0) + $pauseDurationMinutes,
                'is_auto_paused' => false,
                'pause_history' => $pauseHistory,
                'notes' => ($operation->notes ? $operation->notes . "\n" : '') .
                          "Auto-resumed at " . now()->format('Y-m-d H:i:s') .
                          " (Break ended, paused for " . gmdate('i:s', $pauseDurationSeconds) . ")",
            ]);

            $operation->paused_at = null;
            $operation->save();

            $line->update(['status' => 'operating']);

            $esp32Device = $line->esp32Device;
            if ($esp32Device && $esp32Device->is_paused && $esp32Device->paused_at) {
                $pausedAt = Carbon::parse($esp32Device->paused_at);
                $pauseDuration = $pausedAt->diffInSeconds(now());

                $esp32Device->update([
                    'is_paused' => false,
                    'total_pause_seconds' => $esp32Device->total_pause_seconds + $pauseDuration,
                    'paused_at' => null,
                ]);

                \App\Models\Esp32Log::create([
                    'device_id' => $esp32Device->device_id,
                    'counter_a' => $esp32Device->counter_a,
                    'counter_b' => $esp32Device->counter_b,
                    'reject' => $esp32Device->reject,
                    'cycle_time' => $esp32Device->cycle_time,
                    'max_count' => $esp32Device->max_count,
                    'max_stroke' => $esp32Device->max_stroke,
                    'loading_time' => $esp32Device->loading_time,
                    'production_started_at' => $esp32Device->production_started_at,
                    'relay_status' => $esp32Device->relay_status,
                    'error_b' => $esp32Device->error_b,
                    'is_paused' => false,
                    'paused_at' => null,
                    'total_pause_seconds' => $esp32Device->total_pause_seconds + $pauseDuration,
                    'logged_at' => now(),
                ]);
            }

            Log::info("Auto-resumed operation {$operation->operation_number} for line {$line->line_code}");
        } catch (\Exception $e) {
            Log::error("Auto-resume failed for operation {$operation->id}: " . $e->getMessage());
        }
    }
}
