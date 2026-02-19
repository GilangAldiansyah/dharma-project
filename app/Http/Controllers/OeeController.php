<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Esp32Device;
use App\Models\Esp32ProductionHistory;
use App\Models\LineOperation;
use App\Models\MaintenanceReport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OeeController extends Controller
{
    public function index(Request $request)
    {
        $lines = Line::active()
            ->with(['esp32Device', 'latestOeeRecord'])
            ->orderBy('line_name')
            ->get();

        $selectedLineId = $request->get('line_id');
        $startDate = $request->get('start_date', now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $shiftFilter = $request->get('shift');

        $selectedLine = null;
        $oeeRecords = collect();
        $lineStopData = [];

        if ($selectedLineId) {
            $selectedLine = Line::with('esp32Device')->findOrFail($selectedLineId);

            $oeeRecords = $this->calculateOeeRecords(
                $selectedLine,
                Carbon::parse($startDate),
                Carbon::parse($endDate),
                $shiftFilter
            );

            $lineStopData = $this->getLineStopOverviewData(
                $selectedLineId,
                Carbon::parse($startDate),
                Carbon::parse($endDate),
                $shiftFilter
            );
        } else {
            $oeeRecords = $this->calculateOeeRecordsAllLines(
                $lines,
                Carbon::parse($startDate),
                Carbon::parse($endDate),
                $shiftFilter
            );

            $lineStopData = $this->getLineStopOverviewDataAllLines(
                $lines,
                Carbon::parse($startDate),
                Carbon::parse($endDate),
                $shiftFilter
            );
        }

        return inertia('OEE/Index', [
            'lines' => $lines,
            'selectedLine' => $selectedLine,
            'oeeRecords' => $oeeRecords,
            'lineStopData' => $lineStopData,
            'shifts' => \App\Helpers\DateHelper::getAllShifts(),
            'filters' => [
                'line_id' => $selectedLineId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'shift' => $shiftFilter,
            ]
        ]);
    }

    private function calculateOeeRecordsAllLines(
        $lines,
        Carbon $startDate,
        Carbon $endDate,
        ?int $shiftFilter = null
    ) {
        $allOeeRecords = collect();

        foreach ($lines as $line) {
            $lineRecords = $this->calculateOeeRecords(
                $line,
                $startDate,
                $endDate,
                $shiftFilter
            );

            foreach ($lineRecords as $record) {
                $record->line_name = $line->line_name;
                $record->line_code = $line->line_code;
            }

            $allOeeRecords = $allOeeRecords->merge($lineRecords);
        }

        return $allOeeRecords->sortByDesc('period_date')->values();
    }

    private function getLineStopOverviewDataAllLines($lines, Carbon $startDate, Carbon $endDate, $shiftFilter = null)
    {
        $lineIds = $lines->pluck('id')->toArray();

        $query = DB::table('maintenance_reports')
            ->join('machines', 'maintenance_reports.machine_id', '=', 'machines.id')
            ->whereIn('machines.line_id', $lineIds)
            ->where('machines.is_archived', false)
            ->where('maintenance_reports.status', 'Selesai')
            ->whereBetween('maintenance_reports.completed_at', [$startDate, $endDate]);

        if ($shiftFilter) {
            $query->where('maintenance_reports.shift', $shiftFilter);
        }

        $maintenanceReports = $query
            ->select(
                DB::raw('DATE(maintenance_reports.completed_at) as date'),
                DB::raw('HOUR(maintenance_reports.completed_at) as hour'),
                DB::raw('COUNT(*) as stop_count')
            )
            ->groupBy('date', 'hour')
            ->orderBy('date')
            ->orderBy('hour')
            ->get();

        $labels = [];
        $data = [];

        foreach ($maintenanceReports as $report) {
            $labels[] = $report->hour . ':00';
            $data[] = $report->stop_count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function calculateOeeRecords(
        Line $line,
        Carbon $startDate,
        Carbon $endDate,
        ?int $shiftFilter = null
    ) {
        $esp32Device = Esp32Device::where('line_id', $line->id)->first();

        if (!$esp32Device) {
            return collect();
        }

        $periods = $this->generateDailyPeriods($startDate, $endDate);
        $allShifts = \App\Helpers\DateHelper::getAllShifts();

        $shiftsToProcess = $shiftFilter
            ? collect($allShifts)->filter(fn($s) => $s['value'] == $shiftFilter)->values()
            : collect($allShifts);

        $oeeRecords = collect();

        foreach ($periods as $period) {
            foreach ($shiftsToProcess as $shiftInfo) {
                $shift = $shiftInfo['value'];

                $productionHistories = Esp32ProductionHistory::where('device_id', $esp32Device->device_id)
                    ->where('shift', $shift)
                    ->where('production_started_at', '<=', $period['end'])
                    ->where('production_finished_at', '>=', $period['start'])
                    ->get();

                if ($productionHistories->isEmpty()) {
                    continue;
                }

                $totalCounterA = $productionHistories->sum('total_counter_a');
                $totalReject = $productionHistories->sum('total_reject');
                $targetProduction = $productionHistories->sum('max_count');
                $avgCycleTime = $productionHistories->avg('cycle_time') ?? 0;

                if ($totalCounterA == 0) {
                    continue;
                }

                $goodCount = $totalCounterA - $totalReject;

                $operations = LineOperation::where('line_id', $line->id)
                    ->where('status', 'stopped')
                    ->where('shift', $shift)
                    ->where('started_at', '<=', $period['end'])
                    ->where('stopped_at', '>=', $period['start'])
                    ->get();

                $operationTimeMinutes = $operations->sum(function ($op) use ($period) {
                    $actualStart = $op->started_at->lt($period['start'])
                        ? $period['start']
                        : $op->started_at;

                    $actualEnd = $op->stopped_at->gt($period['end'])
                        ? $period['end']
                        : $op->stopped_at;

                    return $actualStart->diffInMinutes($actualEnd);
                });

                $operationTimeHours = $operationTimeMinutes / 60;

                $totalPauseMinutes = $operations->sum('total_pause_minutes');

                if ($operationTimeHours == 0 && $productionHistories->isNotEmpty()) {
                    Log::warning("No line_operations data, using production time as fallback", [
                        'line_id' => $line->id,
                        'period_date' => $period['start']->toDateString(),
                        'shift' => $shift,
                    ]);

                    foreach ($productionHistories as $prod) {
                        $prodStart = Carbon::parse($prod->production_started_at);
                        $prodEnd = Carbon::parse($prod->production_finished_at);

                        $actualStart = $prodStart->lt($period['start'])
                            ? $period['start']
                            : $prodStart;

                        $actualEnd = $prodEnd->gt($period['end'])
                            ? $period['end']
                            : $prodEnd;

                        $operationTimeMinutes += $actualStart->diffInMinutes($actualEnd);
                    }

                    $operationTimeHours = $operationTimeMinutes / 60;

                    Log::info("Using production-based operation time", [
                        'operation_hours' => $operationTimeHours,
                        'shift' => $shift,
                    ]);
                }

                if ($operationTimeHours == 0) {
                    Log::warning("Skipping record due to zero operation time", [
                        'line_id' => $line->id,
                        'period_date' => $period['start']->toDateString(),
                        'shift' => $shift,
                    ]);
                    continue;
                }

                $maintenanceReports = MaintenanceReport::query()
                    ->whereHas('machine', function ($query) use ($line) {
                        $query->where('line_id', $line->id)
                            ->where('is_archived', false);
                    })
                    ->where('status', 'Selesai')
                    ->whereNotNull('completed_at')
                    ->where('shift', $shift)
                    ->whereBetween('completed_at', [$period['start'], $period['end']])
                    ->get();

                $downtimeHours = $maintenanceReports->sum(function ($report) {
                    return round($report->repair_duration_minutes * 60) / 3600;
                });

                $pauseHours = $totalPauseMinutes / 60;
                $uptimeHours = max(0, $operationTimeHours - $downtimeHours - $pauseHours);

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

                $totalFailures = $maintenanceReports->count();

                $oeeRecords->push((object) [
                    'id' => null,
                    'line_id' => $line->id,
                    'period_date' => $period['start']->toDateString(),
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'shift' => $shift,
                    'shift_label' => \App\Helpers\DateHelper::getShiftLabel($shift),
                    'operation_time_hours' => round($operationTimeHours, 4),
                    'uptime_hours' => round($uptimeHours, 4),
                    'downtime_hours' => round($downtimeHours + $pauseHours, 4),
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
                ]);
            }
        }

        return $oeeRecords->sortByDesc('period_date')->values();
    }

    private function generateDailyPeriods(Carbon $startDate, Carbon $endDate): array
    {
        $periods = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $periodStart = $currentDate->copy()->setTime(7, 0, 0);
            $periodEnd = $currentDate->copy()->addDay()->setTime(6, 59, 59);

            $periods[] = [
                'start' => $periodStart,
                'end' => $periodEnd,
            ];

            $currentDate->addDay();
        }

        return $periods;
    }

    private function getLineStopOverviewData($lineId, Carbon $startDate, Carbon $endDate, $shiftFilter = null)
    {
        $query = DB::table('maintenance_reports')
            ->join('machines', 'maintenance_reports.machine_id', '=', 'machines.id')
            ->where('machines.line_id', $lineId)
            ->where('machines.is_archived', false)
            ->where('maintenance_reports.status', 'Selesai')
            ->whereBetween('maintenance_reports.completed_at', [$startDate, $endDate]);

        if ($shiftFilter) {
            $query->where('maintenance_reports.shift', $shiftFilter);
        }

        $maintenanceReports = $query
            ->select(
                DB::raw('DATE(maintenance_reports.completed_at) as date'),
                DB::raw('HOUR(maintenance_reports.completed_at) as hour'),
                DB::raw('COUNT(*) as stop_count')
            )
            ->groupBy('date', 'hour')
            ->orderBy('date')
            ->orderBy('hour')
            ->get();

        $labels = [];
        $data = [];

        foreach ($maintenanceReports as $report) {
            $labels[] = $report->hour . ':00';
            $data[] = $report->stop_count;
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'line_id' => 'nullable|exists:lines,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $shiftFilter = $request->get('shift');

        if (isset($validated['line_id'])) {
            $line = Line::findOrFail($validated['line_id']);
            $oeeRecords = $this->calculateOeeRecords($line, $startDate, $endDate, $shiftFilter);
            $filename = "OEE_{$line->line_code}_{$startDate->format('Ymd')}-{$endDate->format('Ymd')}.csv";
        } else {
            $lines = Line::active()->with('esp32Device')->orderBy('line_name')->get();
            $oeeRecords = $this->calculateOeeRecordsAllLines($lines, $startDate, $endDate, $shiftFilter);
            $filename = "OEE_AllLines_{$startDate->format('Ymd')}-{$endDate->format('Ymd')}.csv";
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($oeeRecords) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Line Code',
                'Line Name',
                'Period Date',
                'Shift',
                'Operation Time (hrs)',
                'Uptime (hrs)',
                'Downtime (hrs)',
                'Total Counter A',
                'Target Production',
                'Total Reject',
                'Good Count',
                'Avg Cycle Time (s)',
                'Availability (%)',
                'Performance (%)',
                'Quality (%)',
                'Achievement Rate (%)',
                'OEE (%)',
                'Total Failures',
            ]);

            foreach ($oeeRecords as $record) {
                fputcsv($file, [
                    $record->line_code ?? '',
                    $record->line_name ?? '',
                    $record->period_date,
                    $record->shift_label,
                    $record->operation_time_hours,
                    $record->uptime_hours,
                    $record->downtime_hours,
                    $record->total_counter_a,
                    $record->target_production,
                    $record->total_reject,
                    $record->good_count,
                    $record->avg_cycle_time,
                    $record->availability,
                    $record->performance,
                    $record->quality,
                    $record->achievement_rate,
                    $record->oee,
                    $record->total_failures,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
