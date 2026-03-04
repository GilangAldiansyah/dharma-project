<?php

namespace App\Services;

use App\Models\PmReport;
use App\Models\PmSchedule;
use Carbon\Carbon;

class PmScheduleService
{
    public function generateReports(PmSchedule $schedule): void
    {
        $months = $this->getTargetMonths($schedule);

        foreach ($months as $month) {
            [$start, $end] = $this->getPlannedWeek($month, $schedule->tahun, $schedule->target_week);

            PmReport::firstOrCreate(
                [
                    'pm_schedule_id'     => $schedule->id,
                    'planned_week_start' => $start->toDateString(),
                ],
                [
                    'pic_id'           => $schedule->jig->pic_id,
                    'planned_week_end' => $end->toDateString(),
                    'status'           => 'pending',
                ]
            );
        }
    }

    public function generateForMonth(int $month, int $year): void
    {
        $schedules = PmSchedule::with('jig')->where('is_active', true)->where('tahun', $year)->get();

        foreach ($schedules as $schedule) {
            if (!in_array($month, $this->getTargetMonths($schedule))) continue;

            [$start, $end] = $this->getPlannedWeek($month, $year, $schedule->target_week);

            PmReport::firstOrCreate(
                [
                    'pm_schedule_id'     => $schedule->id,
                    'planned_week_start' => $start->toDateString(),
                ],
                [
                    'pic_id'           => $schedule->jig->pic_id,
                    'planned_week_end' => $end->toDateString(),
                    'status'           => 'pending',
                ]
            );
        }
    }

    private function getTargetMonths(PmSchedule $schedule): array
    {
        return $schedule->interval === '1_bulan' ? range(1, 12) : [1, 4, 7, 10];
    }

    private function getPlannedWeek(int $month, int $year, int $targetWeek): array
    {
        $lastDay    = Carbon::create($year, $month, 1)->endOfMonth()->day;
        $startDays  = [1 => 1, 2 => 8, 3 => 15, 4 => 22, 5 => 29];
        $endDays    = [1 => 7, 2 => 14, 3 => 21, 4 => 28, 5 => $lastDay];

        $startDay = $startDays[$targetWeek];

        if ($startDay > $lastDay) {
            $targetWeek = 4;
            $startDay   = $startDays[$targetWeek];
        }

        $start = Carbon::create($year, $month, $startDay)->startOfDay();
        $end   = Carbon::create($year, $month, $endDays[$targetWeek])->startOfDay();

        return [$start, $end];
    }
}
