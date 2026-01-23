<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Machine;
use App\Models\MaintenanceReport;
use App\Models\LineOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = MaintenanceReport::with(['machine', 'line', 'lineOperation']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('report_number', 'like', "%{$request->search}%")
                  ->orWhere('problem', 'like', "%{$request->search}%")
                  ->orWhere('reported_by', 'like', "%{$request->search}%")
                  ->orWhereHas('machine', function ($mq) use ($request) {
                      $mq->where('machine_name', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('line', function ($lq) use ($request) {
                      $lq->where('line_name', 'like', "%{$request->search}%")
                         ->orWhere('line_code', 'like', "%{$request->search}%");
                  });
            });
        }

        // Filter by status
        if ($request->status) {
            $query->status($request->status);
        }

        // Filter by plant
        if ($request->plant) {
            $query->byPlant($request->plant);
        }

        // Filter by line
        if ($request->line_id) {
            $query->byLineId($request->line_id);
        }

        $reports = $query->orderBy('reported_at', 'desc')
                         ->paginate(20)
                         ->withQueryString();

        // Add formatted duration to each report
        $reports->getCollection()->transform(function ($report) {
            $report->repair_duration_formatted = $report->repair_duration_formatted;
            $report->line_stop_duration_formatted = $report->line_stop_duration_formatted;
            return $report;
        });

        // Statistics
        $stats = [
            'total' => MaintenanceReport::count(),
            'dilaporkan' => MaintenanceReport::status('Dilaporkan')->count(),
            'sedang_diperbaiki' => MaintenanceReport::status('Sedang Diperbaiki')->count(),
            'selesai_hari_ini' => MaintenanceReport::completed()
                ->whereDate('completed_at', today())
                ->count(),
        ];

        $completedReports = MaintenanceReport::completed()
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedReports->isNotEmpty()) {
            $totalSeconds = $completedReports->sum(function ($report) {
                return $report->started_at->diffInSeconds($report->completed_at);
            });
            $avgMttrSeconds = $totalSeconds / $completedReports->count();

            $hours = floor($avgMttrSeconds / 3600);
            $mins = floor(($avgMttrSeconds % 3600) / 60);
            $secs = floor($avgMttrSeconds % 60);

            $stats['mttr'] = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
        } else {
            $stats['mttr'] = null;
        }

        $lines = Line::all();
        $mtbfValues = $lines->map(fn($line) => $line->average_mtbf)->filter()->values();
        $stats['mtbf'] = $mtbfValues->isNotEmpty() ? round($mtbfValues->avg(), 2) : null;

        $lines = Line::orderBy('plant')->orderBy('line_code')->get();

        $plants = Line::distinct()->pluck('plant');

        return Inertia::render('Maintenance/Index', [
            'reports' => $reports,
            'stats' => $stats,
            'lines' => $lines,
            'plants' => $plants,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'plant' => $request->plant,
                'line_id' => $request->line_id,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'line_id' => 'required|exists:lines,id',
            'machine_id' => 'required|exists:machines,id',
            'problem' => 'nullable|string',
            'reported_by' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $line = Line::findOrFail($validated['line_id']);
            $currentOperation = $line->currentOperation;

            if (!$currentOperation) {
                DB::rollBack();
                return back()->with('error', 'Line tidak dalam status operasi. Mulai operasi terlebih dahulu!');
            }

            $line->update([
                'status' => 'maintenance',
                'last_line_stop' => now(),
            ]);

            $report = MaintenanceReport::create([
                'line_id' => $validated['line_id'],
                'machine_id' => $validated['machine_id'],
                'line_operation_id' => $currentOperation->id,
                'report_number' => MaintenanceReport::generateReportNumber(),
                'problem' => $validated['problem'] ?? 'Line Stop - Mesin bermasalah',
                'reported_by' => $validated['reported_by'] ?? '-',
                'status' => 'Sedang Diperbaiki',
                'reported_at' => now(),
                'line_stopped_at' => now(),
                'started_at' => now(),
            ]);

            DB::commit();

            return back()->with('success', 'Line Stop! Perbaikan otomatis dimulai dan MTTR sedang dihitung.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal membuat laporan: ' . $e->getMessage());
        }
    }

    public function startRepair(int $id)
    {
        $report = MaintenanceReport::findOrFail($id);

        if ($report->status === 'Sedang Diperbaiki') {
            return back()->with('info', 'Perbaikan sudah dimulai.');
        }

        $report->update([
            'status' => 'Sedang Diperbaiki',
            'started_at' => now(),
        ]);

        $report->line->update(['status' => 'maintenance']);

        return back()->with('success', 'Perbaikan dimulai!');
    }

    public function completeRepair(int $id)
    {
        DB::beginTransaction();
        try {
            $report = MaintenanceReport::findOrFail($id);
            $line = $report->line;
            $machine = $report->machine;

            $report->update([
                'status' => 'Selesai',
                'completed_at' => now(),
            ]);

            $report->calculateRepairDuration();
            $report->calculateLineStopDuration();

            $machine->recalculateMetrics();
            $line->recalculateMetrics();

            $remainingActiveReports = MaintenanceReport::where('line_id', $line->id)
                ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
                ->where('id', '!=', $id)
                ->count();

             if ($remainingActiveReports === 0) {
                $line->update([
                    'status' => 'operating',
                ]);
                $message = 'Perbaikan selesai! MTTR dihitung. Line kembali beroperasi.';
            } else {
                $message = "Perbaikan selesai! MTTR dihitung. Masih ada {$remainingActiveReports} laporan maintenance aktif.";
            }

            DB::commit();

            return back()->with('success', 'Perbaikan selesai! MTTR dihitung. Line otomatis beroperasi kembali.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyelesaikan perbaikan: ' . $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $report = MaintenanceReport::findOrFail($id);
        $report->delete();

        return back()->with('success', 'Laporan berhasil dihapus!');
    }

    public function scanQrCode(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $line = Line::with('machines')->where('qr_code', $validated['qr_code'])->first();

        if (!$line) {
            return response()->json([
                'success' => false,
                'message' => 'Line dengan QR Code ini tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'line' => [
                    'id' => $line->id,
                    'line_code' => $line->line_code,
                    'line_name' => $line->line_name,
                    'plant' => $line->plant,
                    'status' => $line->status,
                    'has_running_operation' => $line->currentOperation !== null,
                ],
                'machines' => $line->machines->map(function ($machine) {
                    return [
                        'id' => $machine->id,
                        'machine_name' => $machine->machine_name,
                        'machine_type' => $machine->machine_type,
                        'barcode' => $machine->barcode,
                    ];
                }),
            ],
        ]);
    }

    public function dashboard(Request $request): Response
    {
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        $plantFilter = $request->plant;

        $lineStopByLine = MaintenanceReport::with('line', 'machine')
            ->whereBetween('line_stopped_at', [$startDate, $endDate])
            ->whereNotNull('line_stopped_at')
            ->when($plantFilter, function ($q) use ($plantFilter) {
                $q->whereHas('line', fn($lq) => $lq->where('plant', $plantFilter));
            })
            ->get()
            ->groupBy('line_id')
            ->map(function ($reports, $lineId) use ($startDate, $endDate) {
                $line = $reports->first()->line;
                if (!$line) return null;

                $totalDowntimeMinutes = $reports->sum(function ($report) {
                    if ($report->line_stopped_at && $report->completed_at) {
                        return (int) $report->line_stopped_at->diffInMinutes($report->completed_at);
                    }
                    return 0;
                });

                $archivedLines = Line::where('parent_line_id', $line->id)
                    ->where('is_archived', true)
                    ->whereBetween('period_end', [$startDate, $endDate])
                    ->get();

                $totalOperationHours = $line->total_operation_hours + $archivedLines->sum('total_operation_hours');
                $totalRepairHours = $line->total_repair_hours + $archivedLines->sum('total_repair_hours');
                $totalUptimeHours = $line->uptime_hours + $archivedLines->sum('uptime_hours');
                $totalFailures = $line->total_failures + $archivedLines->sum('total_failures');

                $allMachines = collect();
                $allMachines = $allMachines->merge($line->machines()->where('is_archived', false)->get());

                foreach ($archivedLines as $archivedLine) {
                    $allMachines = $allMachines->merge($archivedLine->machines()->where('is_archived', true)->get());
                }

                $avgMttr = null;
                $machinesWithMttr = $allMachines->whereNotNull('mttr_hours');
                if ($machinesWithMttr->isNotEmpty()) {
                    $mttrHours = $machinesWithMttr->avg('mttr_hours');
                    $hours = floor($mttrHours);
                    $minutes = round(($mttrHours - $hours) * 60);
                    $avgMttr = sprintf('%02d:%02d:00', $hours, $minutes);
                }

                $avgMtbf = null;
                $machinesWithMtbf = $allMachines->whereNotNull('mtbf_hours');
                if ($machinesWithMtbf->isNotEmpty()) {
                    $avgMtbf = round($machinesWithMtbf->avg('mtbf_hours'), 2);
                }

                return [
                    'line_id' => $lineId,
                    'line_name' => $line->line_name,
                    'line_code' => $line->line_code,
                    'plant' => $line->plant,
                    'total_stops' => $reports->count(),
                    'total_downtime_minutes' => $totalDowntimeMinutes,
                    'total_operation_hours' => (float) $totalOperationHours,
                    'total_repair_hours' => (float) $totalRepairHours,
                    'uptime_hours' => (float) $totalUptimeHours,
                    'total_failures' => (int) $totalFailures,
                    'average_mttr' => $avgMttr,
                    'average_mtbf' => $avgMtbf,
                    'machines' => $reports->groupBy('machine_id')->map(function ($machineReports, $machineId) {
                        $machine = $machineReports->first()->machine;
                        if (!$machine) return null;

                        return [
                            'machine_id' => $machineId,
                            'machine_name' => $machine->machine_name,
                            'machine_type' => $machine->machine_type ?? '-',
                            'stops_count' => $machineReports->count(),
                            'downtime_minutes' => $machineReports->sum(function ($report) {
                                if ($report->line_stopped_at && $report->completed_at) {
                                    return (int) $report->line_stopped_at->diffInMinutes($report->completed_at);
                                }
                                return 0;
                            }),
                        ];
                    })->filter()->sortByDesc('stops_count')->values(),
                ];
            })
            ->filter()
            ->sortByDesc('total_stops')
            ->values();

        $lines = Line::where('is_archived', false)->get();
        $archivedLinesForMttrMtbf = Line::where('is_archived', true)
            ->whereBetween('period_end', [$startDate, $endDate])
            ->get();

        $allLinesForMttrMtbf = $lines->merge($archivedLinesForMttrMtbf)
            ->groupBy('line_code')
            ->map(function ($lineGroup) {
                $currentLine = $lineGroup->firstWhere('is_archived', false);
                if (!$currentLine) {
                    $currentLine = $lineGroup->first();
                }

                $allMachines = collect();
                foreach ($lineGroup as $line) {
                    $allMachines = $allMachines->merge($line->machines);
                }

                $machinesWithMttr = $allMachines->whereNotNull('mttr_hours');
                $mttrHours = null;
                if ($machinesWithMttr->isNotEmpty()) {
                    $avgMttrHours = $machinesWithMttr->avg('mttr_hours');
                    $mttrHours = (float) $avgMttrHours;
                }

                $machinesWithMtbf = $allMachines->whereNotNull('mtbf_hours');
                $mtbfHours = null;
                if ($machinesWithMtbf->isNotEmpty()) {
                    $mtbfHours = round($machinesWithMtbf->avg('mtbf_hours'), 2);
                }

                $totalStops = $lineGroup->sum('total_line_stops');

                return [
                    'line_id' => $currentLine->id,
                    'line_name' => $currentLine->line_name,
                    'line_code' => $currentLine->line_code,
                    'plant' => $currentLine->plant,
                    'mttr_hours' => $mttrHours,
                    'mtbf_hours' => $mtbfHours,
                    'total_stops' => (int) $totalStops,
                ];
            })
            ->filter(function ($item) {
                return $item['mttr_hours'] !== null || $item['mtbf_hours'] !== null;
            })
            ->values();

        $mttrMtbfByLine = $allLinesForMttrMtbf;

        $dailyLineStops = MaintenanceReport::whereBetween('line_stopped_at', [$startDate, $endDate])
            ->whereNotNull('line_stopped_at')
            ->when($plantFilter, function ($q) use ($plantFilter) {
                $q->whereHas('line', fn($lq) => $lq->where('plant', $plantFilter));
            })
            ->selectRaw('DATE(line_stopped_at) as date, COUNT(*) as stops_count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                $carbonDate = Carbon::parse($item->date);
                return [
                    'date' => $carbonDate->format('Y-m-d'),
                    'full_date' => $carbonDate->translatedFormat('d F Y'),
                    'stops_count' => (int) $item->stops_count,
                ];
            });

        $machineProblemFrequency = MaintenanceReport::with('machine', 'machine.lineModel')
            ->whereBetween('reported_at', [$startDate, $endDate])
            ->when($plantFilter, function ($q) use ($plantFilter) {
                $q->whereHas('machine', fn($mq) => $mq->where('plant', $plantFilter));
            })
            ->get()
            ->groupBy('machine_id')
            ->map(function ($reports, $machineId) {
                $machine = $reports->first()->machine;
                if (!$machine) return null;

                return [
                    'machine_id' => $machineId,
                    'machine_name' => $machine->machine_name,
                    'line_name' => $machine->lineModel->line_name ?? '-',
                    'plant' => $machine->plant,
                    'problem_count' => $reports->count(),
                ];
            })
            ->filter()
            ->sortByDesc('problem_count')
            ->take(10)
            ->values();

        $avgRepairTimeByStatus = MaintenanceReport::completed()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->when($plantFilter, function ($q) use ($plantFilter) {
                $q->byPlant($plantFilter);
            })
            ->get()
            ->groupBy(function ($report) {
                $minutes = $report->repair_duration_minutes ?? 0;
                if ($minutes <= 30) return '0-30 min';
                if ($minutes <= 60) return '31-60 min';
                if ($minutes <= 120) return '1-2 hours';
                if ($minutes <= 240) return '2-4 hours';
                return '4+ hours';
            })
            ->map(fn($group) => $group->count())
            ->sortKeys()
            ->toArray();

        $topProblems = MaintenanceReport::whereBetween('reported_at', [$startDate, $endDate])
            ->when($plantFilter, function ($q) use ($plantFilter) {
                $q->byPlant($plantFilter);
            })
            ->selectRaw('problem, COUNT(*) as occurrence')
            ->whereNotNull('problem')
            ->where('problem', '!=', '')
            ->groupBy('problem')
            ->orderByDesc('occurrence')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'problem' => $item->problem,
                    'occurrence' => (int) $item->occurrence,
                ];
            });

        $completedReportsForStats = MaintenanceReport::completed()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->when($plantFilter, fn($q) => $q->byPlant($plantFilter))
            ->get();

        $avgMttrHours = null;
        if ($completedReportsForStats->isNotEmpty()) {
            $totalSeconds = $completedReportsForStats->sum(function ($report) {
                return $report->started_at->diffInSeconds($report->completed_at);
            });
            $avgMttrSeconds = $totalSeconds / $completedReportsForStats->count();
            $avgMttrHours = round($avgMttrSeconds / 3600, 2);
        }

        $completedOperations = LineOperation::completed()
            ->whereBetween('started_at', [$startDate, $endDate])
            ->whereNotNull('started_at')
            ->whereNotNull('stopped_at')
            ->when($plantFilter, function($q) use ($plantFilter) {
                $q->whereHas('line', fn($lq) => $lq->where('plant', $plantFilter));
            })
            ->get();

        $avgMtbfHours = null;
        if ($completedOperations->isNotEmpty()) {
            $totalSeconds = $completedOperations->sum(function ($operation) {
                return $operation->started_at->diffInSeconds($operation->stopped_at);
            });
            $avgMtbfSeconds = $totalSeconds / $completedOperations->count();
            $avgMtbfHours = round($avgMtbfSeconds / 3600, 2);
        }

        $totalDowntimeMinutes = MaintenanceReport::completed()
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->when($plantFilter, fn($q) => $q->byPlant($plantFilter))
            ->get()
            ->sum(function ($report) {
                if ($report->line_stopped_at && $report->completed_at) {
                    return (int) $report->line_stopped_at->diffInMinutes($report->completed_at);
                }
                return 0;
            });

        $activeLines = Line::where('is_archived', false)
            ->when($plantFilter, fn($q) => $q->where('plant', $plantFilter))
            ->get();

        $archivedLinesInPeriod = Line::where('is_archived', true)
            ->whereBetween('period_end', [$startDate, $endDate])
            ->when($plantFilter, fn($q) => $q->where('plant', $plantFilter))
            ->get();

        $totalOperationHours = $activeLines->sum('total_operation_hours') + $archivedLinesInPeriod->sum('total_operation_hours');
        $totalRepairHours = $activeLines->sum('total_repair_hours') + $archivedLinesInPeriod->sum('total_repair_hours');
        $totalUptimeHours = max(0, $totalOperationHours - $totalRepairHours);

        $overallStats = [
            'total_line_stops' => MaintenanceReport::whereBetween('line_stopped_at', [$startDate, $endDate])
                ->whereNotNull('line_stopped_at')
                ->when($plantFilter, fn($q) => $q->byPlant($plantFilter))
                ->count(),
            'total_downtime_hours' => round($totalDowntimeMinutes / 60, 2),
            'total_uptime_hours' => round($totalUptimeHours, 2),
            'avg_mttr_hours' => $avgMttrHours ?? 0,
            'avg_mtbf_hours' => $avgMtbfHours ?? 0,
            'active_maintenance' => MaintenanceReport::whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
                ->when($plantFilter, fn($q) => $q->byPlant($plantFilter))
                ->count(),
        ];

        $lineStatusDistribution = Line::where('is_archived', false)
            ->when($plantFilter, fn($q) => $q->where('plant', $plantFilter))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $plants = Line::where('is_archived', false)->distinct()->pluck('plant');

        return Inertia::render('Maintenance/Dashboard', [
            'lineStopByLine' => $lineStopByLine,
            'mttrMtbfByLine' => $mttrMtbfByLine,
            'dailyLineStops' => $dailyLineStops,
            'machineProblemFrequency' => $machineProblemFrequency,
            'avgRepairTimeByStatus' => $avgRepairTimeByStatus,
            'topProblems' => $topProblems,
            'overallStats' => $overallStats,
            'lineStatusDistribution' => $lineStatusDistribution,
            'plants' => $plants,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'plant' => $plantFilter,
            ],
        ]);
    }
}
