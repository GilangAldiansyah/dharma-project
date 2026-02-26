<?php
namespace App\Http\Controllers;
use App\Models\Line;
use App\Models\Machine;
use App\Models\MaintenanceReport;
use App\Models\LineOperation;
use App\Services\LineScheduleService;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class LineController extends Controller
{
    protected $scheduleService;

    public function __construct(LineScheduleService $scheduleService)
    {
        $this->scheduleService = $scheduleService;
    }

    public function index(Request $request): Response
    {
        $query = Line::where('is_archived', false)
            ->with(['machines' => function($q) {
                $q->where('is_archived', false);
            }, 'currentOperation'])
            ->withCount([
                'machines' => function($q) {
                    $q->where('is_archived', false);
                },
                'maintenanceReports',
                'operations'
            ]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('line_name', 'like', "%{$request->search}%")
                ->orWhere('line_code', 'like', "%{$request->search}%")
                ->orWhere('plant', 'like', "%{$request->search}%");
            });
        }

        if ($request->plant) {
            $query->where('plant', $request->plant);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->area) {
            $query->where('area_id', $request->area);
        }

        $lines = $query->orderBy('plant')
                    ->orderBy('line_code')
                    ->paginate(20)
                    ->withQueryString();

        $lines->getCollection()->transform(function ($line) {
            $this->scheduleService->checkAndApplySchedule($line);

            $machinesCount = $line->machines_count;
            $maintenanceReportsCount = $line->maintenance_reports_count;
            $operationsCount = $line->operations_count;

            $line->refresh();
            $line->load(['machines' => function($q) {
                $q->where('is_archived', false);
            }, 'currentOperation']);

            $line->machines_count = $machinesCount;
            $line->maintenance_reports_count = $maintenanceReportsCount;
            $line->operations_count = $operationsCount;

            return [
                'id' => $line->id,
                'line_code' => $line->line_code,
                'line_name' => $line->line_name,
                'plant' => $line->plant,
                'qr_code' => $line->qr_code,
                'status' => $line->status,
                'area' => $line->area ? [
                    'id' => $line->area->id,
                    'name' => $line->area->name,
                ] : null,
                'last_operation_start' => $line->last_operation_start,
                'last_line_stop' => $line->last_line_stop,
                'description' => $line->description,
                'machines_count' => $line->machines_count,
                'maintenance_reports_count' => $line->maintenance_reports_count,
                'operations_count' => $line->operations_count,
                'average_mtbf' => $line->average_mtbf,
                'average_mttr' => $line->average_mttr,
                'total_line_stops' => $line->total_line_stops,
                'active_reports_count' => $line->active_reports_count,
                'total_operation_hours' => (float) ($line->total_operation_hours ?? 0),
                'total_repair_hours' => (float) ($line->total_repair_hours ?? 0),
                'uptime_hours' => (float) ($line->uptime_hours ?? 0),
                'total_failures' => (int) ($line->total_failures ?? 0),
                'schedule_start_time' => $line->schedule_start_time,
                'schedule_end_time' => $line->schedule_end_time,
                'schedule_breaks' => $line->schedule_breaks ?? [],
                'pending_line_stop' => (bool) $line->pending_line_stop,
                'current_operation' => $line->currentOperation ? [
                    'id' => $line->currentOperation->id,
                    'operation_number' => $line->currentOperation->operation_number,
                    'started_at' => $line->currentOperation->started_at,
                    'paused_at' => $line->currentOperation->paused_at,
                    'status' => $line->currentOperation->status,
                    'total_pause_minutes' => $line->currentOperation->total_pause_minutes ?? 0,
                    'formatted_pause_duration' => $line->currentOperation->formatted_pause_duration,
                    'shift' => $line->currentOperation->shift,
                    'shift_label' => $line->currentOperation->shift_label,
                    'is_auto_paused' => $line->currentOperation->is_auto_paused ?? false,
                ] : null,
                'machines' => $line->machines->map(function ($machine) {
                    return [
                        'id' => $machine->id,
                        'machine_name' => $machine->machine_name,
                        'barcode' => $machine->barcode,
                        'machine_type' => $machine->machine_type,
                        'total_operation_hours' => (float) ($machine->total_operation_hours ?? 0),
                        'total_repair_hours' => (float) ($machine->total_repair_hours ?? 0),
                        'total_failures' => (int) ($machine->total_failures ?? 0),
                        'mttr_hours' => $machine->mttr_hours ? (float) $machine->mttr_hours : null,
                        'mtbf_hours' => $machine->mtbf_hours ? (float) $machine->mtbf_hours : null,
                    ];
                }),
            ];
        });

        $stats = [
            'total_lines' => Line::where('is_archived', false)->count(),
            'operating' => Line::where('is_archived', false)->where('status', 'operating')->count(),
            'stopped' => Line::where('is_archived', false)->where('status', 'stopped')->count(),
            'maintenance' => Line::where('is_archived', false)->where('status', 'maintenance')->count(),
        ];

        $plants = Line::where('is_archived', false)->distinct()->pluck('plant');
        $areas = \App\Models\Area::orderBy('name')->get();

        return Inertia::render('Maintenance/Lines', [
            'lines' => $lines,
            'stats' => $stats,
            'plants' => $plants,
            'areas' => $areas,
            'filters' => [
                'search' => $request->search,
                'plant' => $request->plant,
                'status' => $request->status,
                'area' => $request->area,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'line_name' => 'required|string|max:255',
            'plant' => 'required|string|max:255',
            'description' => 'nullable|string',
            'area_id' => 'nullable|integer|exists:areas,id',
            'new_area_name' => 'nullable|string|max:255',
            'schedule_start_time' => 'nullable|date_format:H:i',
            'schedule_end_time' => 'nullable|date_format:H:i',
            'schedule_breaks' => 'nullable|array',
            'schedule_breaks.*.start' => 'required_with:schedule_breaks|date_format:H:i',
            'schedule_breaks.*.end' => 'required_with:schedule_breaks|date_format:H:i',
        ]);

        if (isset($validated['new_area_name']) && $validated['new_area_name']) {
            $area = \App\Models\Area::firstOrCreate(['name' => $validated['new_area_name']]);
            $validated['area_id'] = $area->id;
        }
        unset($validated['new_area_name']);

        $validated['line_code'] = Line::generateLineCode($validated['plant']);
        $validated['qr_code'] = $validated['line_code'];
        $validated['status'] = 'stopped';
        $validated['is_archived'] = false;

        if (isset($validated['schedule_start_time'])) {
            $validated['schedule_start_time'] = $validated['schedule_start_time'] . ':00';
        }
        if (isset($validated['schedule_end_time'])) {
            $validated['schedule_end_time'] = $validated['schedule_end_time'] . ':00';
        }

        Line::create($validated);

        return back()->with('success', 'Line berhasil ditambahkan!');
    }

    public function update(Request $request, int $id)
    {
        $line = Line::where('is_archived', false)->findOrFail($id);

        $validated = $request->validate([
            'line_name' => 'required|string|max:255',
            'plant' => 'required|string|max:255',
            'description' => 'nullable|string',
            'area_id' => 'nullable|integer|exists:areas,id',
            'new_area_name' => 'nullable|string|max:255',
        ]);

        if (isset($validated['new_area_name']) && $validated['new_area_name']) {
            $area = \App\Models\Area::firstOrCreate(['name' => $validated['new_area_name']]);
            $validated['area_id'] = $area->id;
        }
        unset($validated['new_area_name']);

        $line->update($validated);

        return back()->with('success', 'Line berhasil diupdate!');
    }

    public function updateSchedule(Request $request, int $id)
    {
        $validated = $request->validate([
            'schedule_start_time' => 'required|date_format:H:i',
            'schedule_end_time' => 'required|date_format:H:i',
            'schedule_breaks' => 'nullable|array',
            'schedule_breaks.*.start' => 'required_with:schedule_breaks|date_format:H:i',
            'schedule_breaks.*.end' => 'required_with:schedule_breaks|date_format:H:i',
        ]);

        $line = Line::where('is_archived', false)->findOrFail($id);

        $line->update([
            'schedule_start_time' => $validated['schedule_start_time'] . ':00',
            'schedule_end_time' => $validated['schedule_end_time'] . ':00',
            'schedule_breaks' => $validated['schedule_breaks'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule berhasil diupdate',
        ]);
    }

    public function destroy(int $id)
    {
        $line = Line::where('is_archived', false)->findOrFail($id);

        if ($line->machines()->where('is_archived', false)->count() > 0) {
            return back()->with('error', 'Tidak dapat menghapus line yang memiliki mesin!');
        }

        Line::where('parent_line_id', $id)->delete();

        $line->delete();

        return back()->with('success', 'Line berhasil dihapus!');
    }

    public function resetMetrics(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $line = Line::where('is_archived', false)->findOrFail($id);

            if ($line->currentOperation && in_array($line->currentOperation->status, ['running', 'paused'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat reset line yang sedang beroperasi! Hentikan operasi terlebih dahulu.'
                ], 400);
            }

            DB::transaction(function () use ($line, $request) {
                $now = Carbon::now();
                $timestamp = $now->format('YmdHis');

                $archivedLine = $line->replicate();
                $archivedLine->is_archived = true;
                $archivedLine->period_start = $line->current_period_start ?? $line->created_at;
                $archivedLine->period_end = $now;
                $archivedLine->parent_line_id = $line->id;
                $archivedLine->line_code = $line->line_code . '_' . $timestamp;
                $archivedLine->qr_code = $line->qr_code . '_archived_' . $timestamp;
                $originalDesc = $line->description ?? '';
                $archivedLine->description = $originalDesc . "\n\n[ARCHIVED - " . $now->format('d M Y H:i:s') . "]\nAlasan: " . $request->reason;
                $archivedLine->save();

                foreach ($line->machines()->where('is_archived', false)->get() as $machine) {
                    $archivedMachine = $machine->replicate();
                    $archivedMachine->is_archived = true;
                    $archivedMachine->period_start = $machine->current_period_start ?? $machine->created_at;
                    $archivedMachine->period_end = $now;
                    $archivedMachine->parent_machine_id = $machine->id;
                    $archivedMachine->line_id = $archivedLine->id;
                    $archivedMachine->barcode = $machine->barcode . '_archived_' . $timestamp;
                    $archivedMachine->save();
                }
                $line->update([
                    'total_operation_hours' => 0,
                    'total_repair_hours' => 0,
                    'uptime_hours' => 0,
                    'total_failures' => 0,
                    'total_line_stops' => 0,
                    'average_mtbf' => null,
                    'average_mttr' => null,
                    'current_period_start' => $now
                ]);

                $line->machines()->where('is_archived', false)->update([
                    'total_operation_hours' => 0,
                    'total_repair_hours' => 0,
                    'total_failures' => 0,
                    'mtbf_hours' => null,
                    'mttr_hours' => null,
                    'current_period_start' => now()
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => "Metrics berhasil direset ke 0. Data periode sebelumnya tersimpan dalam history dan dapat dilihat kapan saja untuk tracking dan analisis."
            ]);

        } catch (\Exception $e) {
            Log::error('Reset metrics error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal reset metrics: ' . $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request, int $id)
    {
        try {
            $line = Line::where('is_archived', false)->findOrFail($id);

            $query = Line::where('parent_line_id', $line->id)
                ->where('is_archived', true)
                ->with(['machines' => function($q) {
                    $q->where('is_archived', true);
                }]);

            $filterType = $request->input('filter_type', 'all');
            $filterShift = $request->input('shift');

            if ($filterType === 'today') {
                $effectiveToday = \App\Helpers\DateHelper::getEffectiveDate();
                $query->whereDate('period_end', $effectiveToday);
            } elseif ($filterType === 'week') {
                $effectiveToday = \App\Helpers\DateHelper::getEffectiveDate();
                $weekStart = $effectiveToday->copy()->startOfWeek();
                $weekEnd = $effectiveToday->copy()->endOfWeek()->endOfDay();
                $query->whereBetween('period_end', [$weekStart, $weekEnd]);
            } elseif ($filterType === 'month') {
                $effectiveToday = \App\Helpers\DateHelper::getEffectiveDate();
                $monthStart = $effectiveToday->copy()->startOfMonth();
                $monthEnd = $effectiveToday->copy()->endOfMonth()->endOfDay();
                $query->whereBetween('period_end', [$monthStart, $monthEnd]);
            }
            if ($request->input('date')) {
                $filterDate = \App\Helpers\DateHelper::getEffectiveDate(Carbon::parse($request->input('date')));
                $query->whereDate('period_end', $filterDate);
            }

            $archivedLines = $query->orderBy('period_end', 'desc')->get();

            if ($filterShift) {
                $archivedLines = $archivedLines->filter(function($archived) use ($filterShift) {
                    if (!$archived->period_end) return false;
                    $periodEnd = Carbon::parse($archived->period_end);
                    return \App\Helpers\DateHelper::getCurrentShift($periodEnd) == $filterShift;
                })->values();
            }

            return response()->json([
                'success' => true,
                'current' => [
                    'line' => $line->load(['machines' => function($q) {
                        $q->where('is_archived', false);
                    }]),
                    'period' => 'Periode Saat Ini (Aktif)'
                ],
                'history' => $archivedLines->map(function($archived) {
                    $machines = $archived->machines;
                    $avgMttr = null;
                    $avgMtbf = null;

                    if ($machines->isNotEmpty()) {
                        $machinesWithMttr = $machines->whereNotNull('mttr_hours');
                        if ($machinesWithMttr->isNotEmpty()) {
                            $mttrHours = $machinesWithMttr->avg('mttr_hours');
                            $hours = floor($mttrHours);
                            $minutes = round(($mttrHours - $hours) * 60);
                            $avgMttr = sprintf('%02d:%02d:00', $hours, $minutes);
                        }

                        $machinesWithMtbf = $machines->whereNotNull('mtbf_hours');
                        if ($machinesWithMtbf->isNotEmpty()) {
                            $avgMtbf = round($machinesWithMtbf->avg('mtbf_hours'), 2);
                        }
                    }

                    $hasData = $archived->total_operation_hours > 0
                        || $archived->total_repair_hours > 0
                        || $archived->total_failures > 0;

                    return [
                        'period' => $this->formatPeriodLabel($archived->period_start, $archived->period_end),
                        'has_data' => $hasData,
                        'line' => [
                            'id' => $archived->id,
                            'line_code' => $archived->line_code,
                            'line_name' => $archived->line_name,
                            'plant' => $archived->plant,
                            'total_operation_hours' => (float) $archived->total_operation_hours,
                            'total_repair_hours' => (float) $archived->total_repair_hours,
                            'uptime_hours' => (float) ($archived->uptime_hours ?? 0),
                            'total_failures' => (int) $archived->total_failures,
                            'total_line_stops' => (int) $archived->total_line_stops,
                            'average_mtbf' => $avgMtbf,
                            'average_mttr' => $avgMttr,
                        ],
                        'machines' => $machines->map(function($m) {
                            return [
                                'id' => $m->id,
                                'machine_name' => $m->machine_name,
                                'machine_type' => $m->machine_type,
                                'barcode' => $m->barcode,
                                'total_operation_hours' => (float) $m->total_operation_hours,
                                'total_repair_hours' => (float) $m->total_repair_hours,
                                'total_failures' => (int) $m->total_failures,
                                'mtbf_hours' => $m->mtbf_hours ? (float) $m->mtbf_hours : null,
                                'mttr_hours' => $m->mttr_hours ? (float) $m->mttr_hours : null,
                            ];
                        }),
                        'reason' => $this->extractReasonFromDescription($archived->description),
                    ];
                })->filter(fn($item) => $item['has_data'])->values()
            ]);

        } catch (\Exception $e) {
            Log::error('Get history error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data history: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatPeriodLabel($periodStart, $periodEnd): string
    {
        if (!$periodStart || !$periodEnd) return '-';

        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);

        $effectiveStartDate = \App\Helpers\DateHelper::getEffectiveDate($start);
        $effectiveEndDate = \App\Helpers\DateHelper::getEffectiveDate($end);

        if ($effectiveStartDate->isSameDay($effectiveEndDate)) {
            return $effectiveEndDate->format('d M Y') . ', ' . $start->format('H:i') . ' - ' . $end->format('H:i');
        }

        return $start->format('d M Y H:i') . ' - ' . $end->format('d M Y H:i');
    }

    public function getSummary(Request $request, int $id)
    {
        try {
            $line = Line::where('is_archived', false)->findOrFail($id);

            $filterType = $request->input('filter_type', 'all');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $archivedLinesQuery = Line::where('parent_line_id', $line->id)
                ->where('is_archived', true)
                ->with(['machines' => function($q) {
                    $q->where('is_archived', true);
                }]);

            if ($filterType === 'week') {
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                $archivedLinesQuery->whereBetween('period_end', [$startDate, $endDate]);
            } elseif ($filterType === 'month') {
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                $archivedLinesQuery->whereBetween('period_end', [$startDate, $endDate]);
            } elseif ($filterType === 'custom' && $startDate && $endDate) {
                $startDate = Carbon::parse($startDate)->startOfDay();
                $endDate = Carbon::parse($endDate)->endOfDay();
                $archivedLinesQuery->whereBetween('period_end', [$startDate, $endDate]);
            }

            $archivedLines = $archivedLinesQuery->orderBy('period_end', 'desc')->get();

            $filteredOperation = $archivedLines->sum('total_operation_hours');
            $filteredRepair = $archivedLines->sum('total_repair_hours');
            $filteredUptime = $archivedLines->sum('uptime_hours');
            $filteredFailures = $archivedLines->sum('total_failures');
            $filteredLineStops = $archivedLines->sum('total_line_stops');

            $includeCurrentPeriod = false;
            $currentPeriodStart = $line->current_period_start ?? $line->created_at;

            if ($filterType === 'all') {
                $includeCurrentPeriod = true;
            } elseif ($filterType === 'week') {
                $includeCurrentPeriod = $currentPeriodStart->between(Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek());
            } elseif ($filterType === 'month') {
                $includeCurrentPeriod = $currentPeriodStart->between(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
            } elseif ($filterType === 'custom' && $startDate && $endDate) {
                $includeCurrentPeriod = $currentPeriodStart->between($startDate, $endDate);
            }

            if ($includeCurrentPeriod) {
                $filteredOperation += $line->total_operation_hours;
                $filteredRepair += $line->total_repair_hours;
                $filteredUptime += $line->uptime_hours;
                $filteredFailures += $line->total_failures;
                $filteredLineStops += $line->total_line_stops;
            }

            $totalAllTime = [
                'operation_hours' => $filteredOperation,
                'repair_hours' => $filteredRepair,
                'uptime_hours' => $filteredUptime,
                'failures' => $filteredFailures,
                'line_stops' => $filteredLineStops,
            ];

            $currentMachines = $line->machines()->where('is_archived', false)->whereNotNull('mttr_hours')->get();
            $currentMttr = null;
            if ($currentMachines->isNotEmpty()) {
                $mttrHours = $currentMachines->avg('mttr_hours');
                $hours = floor($mttrHours);
                $minutes = round(($mttrHours - $hours) * 60);
                $currentMttr = sprintf('%02d:%02d:00', $hours, $minutes);
            }

            $currentMtbf = null;
            $machinesWithMtbf = $line->machines()->where('is_archived', false)->whereNotNull('mtbf_hours')->get();
            if ($machinesWithMtbf->isNotEmpty()) {
                $currentMtbf = round($machinesWithMtbf->avg('mtbf_hours'), 2);
            }

            return response()->json([
                'success' => true,
                'line' => [
                    'id' => $line->id,
                    'line_code' => $line->line_code,
                    'line_name' => $line->line_name,
                ],
                'current_period' => [
                    'operation_hours' => (float) $line->total_operation_hours,
                    'repair_hours' => (float) $line->total_repair_hours,
                    'uptime_hours' => (float) ($line->uptime_hours ?? 0),
                    'failures' => (int) $line->total_failures,
                    'mtbf' => $currentMtbf,
                    'mttr' => $currentMttr,
                    'included_in_filter' => $includeCurrentPeriod,
                ],
                'total_all_time' => $totalAllTime,
                'periods_count' => ($includeCurrentPeriod ? 1 : 0) + $archivedLines->count(),
                'filter_info' => [
                    'type' => $filterType,
                    'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
                    'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
                ],
                'archived_periods' => $archivedLines->map(function($a) {
                    return [
                        'period' => $a->period_start->format('d M Y') . ' - ' . $a->period_end->format('d M Y H:i'),
                        'operation_hours' => (float) $a->total_operation_hours,
                        'repair_hours' => (float) $a->total_repair_hours,
                        'uptime_hours' => (float) ($a->uptime_hours ?? 0),
                        'failures' => (int) $a->total_failures,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Get summary error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil summary: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractReasonFromDescription($description)
    {
        if (preg_match('/\[ARCHIVED.*?\]\s*Alasan:\s*(.+?)(?:\n|$)/s', $description, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    public function lineStop(Request $request)
    {
        $validated = $request->validate([
            'line_id'     => 'required|exists:lines,id',
            'machine_id'  => 'required|exists:machines,id',
            'problem'     => 'nullable|string',
            'reported_by' => 'nullable|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $line    = Line::where('is_archived', false)->findOrFail($validated['line_id']);
            $machine = Machine::where('is_archived', false)->findOrFail($validated['machine_id']);

            $currentOperation = $line->currentOperation;

            if (!$currentOperation) {
                $currentOperation = LineOperation::create([
                    'line_id'          => $validated['line_id'],
                    'operation_number' => LineOperation::generateOperationNumber(),
                    'started_at'       => now()->subMinutes(5),
                    'started_by'       => 'System (Auto-created for line stop)',
                    'stopped_at'       => now(),
                    'stopped_by'       => $validated['reported_by'] ?? 'System',
                    'status'           => 'stopped',
                    'notes'            => 'Operation otomatis dibuat saat line stop tanpa operasi aktif',
                ]);
                $currentOperation->calculateMetrics();
            }

            $lineStoppedAt = $line->pending_line_stop && $line->last_line_stop
                ? $line->last_line_stop
                : now();

            $line->update([
                'status'            => 'maintenance',
                'last_line_stop'    => $lineStoppedAt,
                'pending_line_stop' => false,
            ]);

            $report = MaintenanceReport::create([
                'line_id'           => $validated['line_id'],
                'machine_id'        => $validated['machine_id'],
                'line_operation_id' => $currentOperation->id,
                'report_number'     => MaintenanceReport::generateReportNumber(),
                'problem'           => $validated['problem'] ?? 'Line Stop - Mesin bermasalah',
                'reported_by'       => $validated['reported_by'] ?? '-',
                'status'            => 'Sedang Diperbaiki',
                'reported_at'       => now(),
                'line_stopped_at'   => $lineStoppedAt,
                'started_at'        => $lineStoppedAt,
            ]);

            DB::commit();

            try {
                AppNotification::createLineStop($report->fresh(['line', 'machine']));
            } catch (\Exception $e) {
                Log::warning('[Notif] ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => "Line Stop berhasil! Laporan maintenance dibuat: {$report->report_number}"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Line stop error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function quickComplete(int $reportId)
    {
        DB::beginTransaction();
        try {
            $report = MaintenanceReport::findOrFail($reportId);
            $line = $report->line;
            $machine = $report->machine;

            if ($report->status === 'Selesai') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Laporan sudah diselesaikan sebelumnya.'
                ], 400);
            }

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
                ->where('id', '!=', $reportId)
                ->count();

            if ($remainingActiveReports === 0) {
                $line->update([
                    'status' => 'operating',
                    'last_operation_start' => now(),
                ]);

                $message = 'Perbaikan selesai! Line otomatis beroperasi kembali.';
            } else {
                $message = "Perbaikan selesai! Masih ada {$remainingActiveReports} laporan maintenance aktif lainnya.";
            }

            DB::commit();

            try {
                AppNotification::createRepairComplete($report->fresh(['line', 'machine']));
            } catch (\Exception $e) {
                Log::warning('[Notif] ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Quick complete error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyelesaikan perbaikan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getActiveReports(int $lineId)
    {
        try {
            $line = Line::where('is_archived', false)->findOrFail($lineId);

            $reports = MaintenanceReport::with(['machine'])
                ->where('line_id', $lineId)
                ->whereIn('status', ['Dilaporkan', 'Sedang Diperbaiki'])
                ->orderBy('reported_at', 'desc')
                ->get()
                ->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'report_number' => $report->report_number,
                        'problem' => $report->problem,
                        'status' => $report->status,
                        'reported_at' => $report->reported_at->format('Y-m-d H:i'),
                        'machine' => [
                            'machine_name' => $report->machine->machine_name,
                            'machine_type' => $report->machine->machine_type,
                        ],
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data laporan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function scanQrCode(Request $request)
    {
        $validated = $request->validate([
            'qr_code' => 'required|string',
        ]);

        $line = Line::where('is_archived', false)
            ->where('qr_code', $validated['qr_code'])
            ->first();

        if (!$line) {
            return response()->json([
                'success' => false,
                'message' => 'Line dengan QR Code ini tidak ditemukan',
            ], 404);
        }

        $machines = $line->machines()->where('is_archived', false)->get();
        $currentOperation = $line->currentOperation;

        return response()->json([
            'success' => true,
            'data' => [
                'line' => [
                    'id' => $line->id,
                    'line_code' => $line->line_code,
                    'line_name' => $line->line_name,
                    'plant' => $line->plant,
                    'status' => $line->status,
                    'qr_code' => $line->qr_code,
                    'has_running_operation' => $currentOperation !== null,
                    'current_operation_id' => $currentOperation?->id,
                ],
                'machines' => $machines->map(function ($machine) {
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

    public function show(int $id): Response
    {
        $line = Line::where('is_archived', false)
            ->with(['machines' => function($q) {
                $q->where('is_archived', false);
            }, 'area'])
            ->findOrFail($id);

        return Inertia::render('Maintenance/Detail', [
            'line' => [
                'id' => $line->id,
                'line_code' => $line->line_code,
                'line_name' => $line->line_name,
                'plant' => $line->plant,
                'description' => $line->description,
                'total_operation_hours' => (float) ($line->total_operation_hours ?? 0),
                'total_repair_hours' => (float) ($line->total_repair_hours ?? 0),
                'uptime_hours' => (float) ($line->uptime_hours ?? 0),
                'total_failures' => (int) ($line->total_failures ?? 0),
                'total_line_stops' => (int) ($line->total_line_stops ?? 0),
                'average_mtbf' => $line->average_mtbf,
                'average_mttr' => $line->average_mttr,
                'schedule_start_time' => $line->schedule_start_time,
                'schedule_end_time' => $line->schedule_end_time,
                'schedule_breaks' => $line->schedule_breaks ?? [],
                'area' => $line->area ? [
                    'id' => $line->area->id,
                    'name' => $line->area->name,
                ] : null,
                'machines' => $line->machines->map(function ($machine) {
                    return [
                        'id' => $machine->id,
                        'machine_name' => $machine->machine_name,
                        'barcode' => $machine->barcode,
                        'machine_type' => $machine->machine_type,
                        'total_operation_hours' => (float) ($machine->total_operation_hours ?? 0),
                        'total_repair_hours' => (float) ($machine->total_repair_hours ?? 0),
                        'total_failures' => (int) ($machine->total_failures ?? 0),
                        'mttr_hours' => $machine->mttr_hours ? (float) $machine->mttr_hours : null,
                        'mtbf_hours' => $machine->mtbf_hours ? (float) $machine->mtbf_hours : null,
                    ];
                }),
            ],
        ]);
    }
}
