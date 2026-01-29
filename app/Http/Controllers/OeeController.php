<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\LineOeeRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OeeController extends Controller
{
    public function index(Request $request)
    {
        $lines = Line::active()
            ->with(['esp32Device', 'latestOeeRecord'])
            ->orderBy('line_name')
            ->get();

        $selectedLineId = $request->get('line_id');
        $periodType = $request->get('period_type', 'daily');
        $startDate = $request->get('start_date', now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $shiftFilter = $request->get('shift'); // ← TAMBAHAN

        $selectedLine = null;
        $oeeRecords = collect();
        $lineStopData = [];

        if ($selectedLineId) {
            $selectedLine = Line::with('esp32Device')->findOrFail($selectedLineId);

            $this->autoCalculateOEE($selectedLine, $periodType, Carbon::parse($startDate), Carbon::parse($endDate));

            $oeeQuery = LineOeeRecord::byLine($selectedLineId)
                ->byPeriodType($periodType)
                ->dateRange(Carbon::parse($startDate), Carbon::parse($endDate));

            // ← TAMBAHAN: Filter by shift
            if ($shiftFilter) {
                $oeeQuery->byShift($shiftFilter);
            }

            $oeeRecords = $oeeQuery->orderBy('period_date', 'desc')->get();

            $lineStopData = $this->getLineStopOverviewData(
                $selectedLineId,
                Carbon::parse($startDate),
                Carbon::parse($endDate),
                $shiftFilter // ← TAMBAHAN: Pass shift filter
            );
        }

        return inertia('OEE/Index', [
            'lines' => $lines,
            'selectedLine' => $selectedLine,
            'oeeRecords' => $oeeRecords,
            'lineStopData' => $lineStopData,
            'shifts' => \App\Helpers\DateHelper::getAllShifts(), // ← TAMBAHAN
            'filters' => [
                'line_id' => $selectedLineId,
                'period_type' => $periodType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'shift' => $shiftFilter, // ← TAMBAHAN
            ]
        ]);
    }


    private function autoCalculateOEE(Line $line, string $periodType, Carbon $startDate, Carbon $endDate)
    {
        $calculatedBy = Auth::check() ? Auth::user()->name : 'system';

        if ($periodType === 'daily') {
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $periodStart = $currentDate->copy()->startOfDay();
                $periodEnd = $currentDate->copy()->endOfDay();

                LineOeeRecord::calculateOEE(
                    $line,
                    $periodStart,
                    $periodEnd,
                    'daily',
                    $calculatedBy
                );

                $currentDate->addDay();
            }
        } elseif ($periodType === 'weekly') {
            $currentDate = $startDate->copy()->startOfWeek();

            while ($currentDate <= $endDate) {
                $periodStart = $currentDate->copy()->startOfWeek();
                $periodEnd = $currentDate->copy()->endOfWeek();

                if ($periodEnd > $endDate) {
                    $periodEnd = $endDate->copy()->endOfDay();
                }

                LineOeeRecord::calculateOEE(
                    $line,
                    $periodStart,
                    $periodEnd,
                    'weekly',
                    $calculatedBy
                );

                $currentDate->addWeek();
            }
        } elseif ($periodType === 'monthly') {
            $currentDate = $startDate->copy()->startOfMonth();

            while ($currentDate <= $endDate) {
                $periodStart = $currentDate->copy()->startOfMonth();
                $periodEnd = $currentDate->copy()->endOfMonth();

                if ($periodEnd > $endDate) {
                    $periodEnd = $endDate->copy()->endOfDay();
                }

                LineOeeRecord::calculateOEE(
                    $line,
                    $periodStart,
                    $periodEnd,
                    'monthly',
                    $calculatedBy
                );

                $currentDate->addMonth();
            }
        } else {
            LineOeeRecord::calculateOEE(
                $line,
                $startDate->startOfDay(),
                $endDate->endOfDay(),
                'custom',
                $calculatedBy
            );
        }
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

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'period_type' => 'required|in:daily,weekly,monthly,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $line = Line::findOrFail($validated['line_id']);
        $periodType = $validated['period_type'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $calculatedBy = Auth::check() ? Auth::user()->name : 'system';

        DB::beginTransaction();
        try {
            $oeeRecords = [];
            $skippedPeriods = 0;

            if ($periodType === 'daily') {
                $currentDate = $startDate->copy();

                while ($currentDate <= $endDate) {
                    $periodStart = $currentDate->copy()->startOfDay();
                    $periodEnd = $currentDate->copy()->endOfDay();

                    $oeeRecord = LineOeeRecord::calculateOEE(
                        $line,
                        $periodStart,
                        $periodEnd,
                        'daily',
                        $calculatedBy
                    );

                    if ($oeeRecord) {
                        $oeeRecords[] = $oeeRecord;
                    } else {
                        $skippedPeriods++;
                    }

                    $currentDate->addDay();
                }
            } elseif ($periodType === 'weekly') {
                $currentDate = $startDate->copy()->startOfWeek();

                while ($currentDate <= $endDate) {
                    $periodStart = $currentDate->copy()->startOfWeek();
                    $periodEnd = $currentDate->copy()->endOfWeek();

                    if ($periodEnd > $endDate) {
                        $periodEnd = $endDate->copy()->endOfDay();
                    }

                    $oeeRecord = LineOeeRecord::calculateOEE(
                        $line,
                        $periodStart,
                        $periodEnd,
                        'weekly',
                        $calculatedBy
                    );

                    if ($oeeRecord) {
                        $oeeRecords[] = $oeeRecord;
                    } else {
                        $skippedPeriods++;
                    }

                    $currentDate->addWeek();
                }
            } elseif ($periodType === 'monthly') {
                $currentDate = $startDate->copy()->startOfMonth();

                while ($currentDate <= $endDate) {
                    $periodStart = $currentDate->copy()->startOfMonth();
                    $periodEnd = $currentDate->copy()->endOfMonth();

                    if ($periodEnd > $endDate) {
                        $periodEnd = $endDate->copy()->endOfDay();
                    }

                    $oeeRecord = LineOeeRecord::calculateOEE(
                        $line,
                        $periodStart,
                        $periodEnd,
                        'monthly',
                        $calculatedBy
                    );

                    if ($oeeRecord) {
                        $oeeRecords[] = $oeeRecord;
                    } else {
                        $skippedPeriods++;
                    }

                    $currentDate->addMonth();
                }
            } else {
                $oeeRecord = LineOeeRecord::calculateOEE(
                    $line,
                    $startDate->startOfDay(),
                    $endDate->endOfDay(),
                    'custom',
                    $calculatedBy
                );

                if ($oeeRecord) {
                    $oeeRecords[] = $oeeRecord;
                } else {
                    $skippedPeriods++;
                }
            }

            DB::commit();

            $message = count($oeeRecords) . ' record(s) created/updated.';
            if ($skippedPeriods > 0) {
                $message .= ' ' . $skippedPeriods . ' period(s) skipped (no production data).';
            }

            return redirect()
                ->route('oee.index', [
                    'line_id' => $line->id,
                    'period_type' => $periodType,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ])
                ->with('success', 'OEE calculation completed. ' . $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to calculate OEE: ' . $e->getMessage());
        }
    }

    public function show(LineOeeRecord $oeeRecord)
    {
        $oeeRecord->load(['line.esp32Device']);

        return inertia('OEE/Show', [
            'oeeRecord' => $oeeRecord
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'period_type' => 'required|in:daily,weekly,monthly,custom',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $line = Line::findOrFail($validated['line_id']);
        $periodType = $validated['period_type'];
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $oeeRecords = LineOeeRecord::byLine($line->id)
            ->byPeriodType($periodType)
            ->dateRange($startDate, $endDate)
            ->orderBy('period_date', 'asc')
            ->get();

        $filename = "OEE_{$line->line_code}_{$periodType}_{$startDate->format('Ymd')}-{$endDate->format('Ymd')}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($oeeRecords) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Period Date',
                'Period Start',
                'Period End',
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
                    $record->period_date->format('Y-m-d'),
                    $record->period_start->format('Y-m-d H:i:s'),
                    $record->period_end->format('Y-m-d H:i:s'),
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

    public function destroy(LineOeeRecord $oeeRecord)
    {
        $lineId = $oeeRecord->line_id;
        $oeeRecord->delete();

        return redirect()
            ->route('oee.index', ['line_id' => $lineId])
            ->with('success', 'OEE record deleted successfully.');
    }

    public function chartData(Request $request)
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'period_type' => 'required|in:daily,weekly,monthly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $oeeRecords = LineOeeRecord::byLine($validated['line_id'])
            ->byPeriodType($validated['period_type'])
            ->dateRange(
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date'])
            )
            ->orderBy('period_date', 'asc')
            ->get();

        return response()->json([
            'labels' => $oeeRecords->pluck('period_date')->map(fn($d) => $d->format('Y-m-d')),
            'oee' => $oeeRecords->pluck('oee'),
            'availability' => $oeeRecords->pluck('availability'),
            'performance' => $oeeRecords->pluck('performance'),
            'quality' => $oeeRecords->pluck('quality'),
            'achievement_rate' => $oeeRecords->pluck('achievement_rate'),
        ]);
    }
}
