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
            [$start, $end] = $this->getPlannedWeek($month, $schedule->tahun);

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
            if (!in_array($month, $this->getTargetMonths($schedule))) {
                continue;
            }

            [$start, $end] = $this->getPlannedWeek($month, $year);

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

    /**
     * Planned week = 7 hari terakhir bulan.
     * end   = hari terakhir bulan
     * start = end - 6 hari
     */
    private function getPlannedWeek(int $month, int $year): array
    {
        $end   = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
        $start = $end->copy()->subDays(6);

        return [$start, $end];
    }
}
