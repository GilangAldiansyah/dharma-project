<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetricsLogger
{
    /**
     * Log metrics dengan format yang mudah dibaca
     */
    public static function log(string $action, array $data): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $logMessage = "\n" . str_repeat("=", 80) . "\n";
        $logMessage .= "🔍 METRICS DEBUG LOG - {$action}\n";
        $logMessage .= "⏰ Timestamp: {$timestamp}\n";
        $logMessage .= str_repeat("-", 80) . "\n";

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $logMessage .= "📊 {$key}:\n";
                foreach ($value as $subKey => $subValue) {
                    $logMessage .= "   • {$subKey}: " . self::formatValue($subValue) . "\n";
                }
            } else {
                $logMessage .= "📌 {$key}: " . self::formatValue($value) . "\n";
            }
        }

        $logMessage .= str_repeat("=", 80) . "\n";

        Log::channel('single')->info($logMessage);
    }

    /**
     * Format value untuk display
     */
    private static function formatValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_numeric($value)) {
            return number_format($value, 2);
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * Log Line Metrics
     */
    public static function logLineMetrics(string $action, $line): void
    {
        $machines = $line->machines()->where('is_archived', false)->get();

        $machinesData = [];
        foreach ($machines as $machine) {
            $machinesData[] = [
                'name' => $machine->machine_name,
                'operation_hours' => $machine->total_operation_hours,
                'repair_hours' => $machine->total_repair_hours,
                'failures' => $machine->total_failures,
                'mttr_hours' => $machine->mttr_hours,
                'mtbf_hours' => $machine->mtbf_hours,
            ];
        }

        self::log($action, [
            'Line Info' => [
                'line_code' => $line->line_code,
                'line_name' => $line->line_name,
                'status' => $line->status,
            ],
            'Line Metrics' => [
                'total_operation_hours' => $line->total_operation_hours,
                'total_repair_hours' => $line->total_repair_hours,
                'total_failures' => $line->total_failures,
                'average_mttr' => $line->average_mttr ?? 'N/A',
                'average_mtbf' => $line->average_mtbf ?? 'N/A',
            ],
            'Machines Count' => count($machinesData),
            'Machines Detail' => json_encode($machinesData, JSON_PRETTY_PRINT),
        ]);
    }

    /**
     * Log Operation Metrics
     */
    public static function logOperationMetrics(string $action, $operation): void
    {
        if (!$operation) {
            self::log($action, ['Operation' => 'NULL - No active operation']);
            return;
        }

        $totalMinutes = 0;
        if ($operation->started_at && $operation->stopped_at) {
            $totalMinutes = $operation->started_at->diffInMinutes($operation->stopped_at);
        }

        $maintenanceReports = $operation->maintenanceReports()
            ->where('status', 'Selesai')
            ->get();

        $reportsData = [];
        foreach ($maintenanceReports as $report) {
            $reportsData[] = [
                'report_number' => $report->report_number,
                'machine' => $report->machine->machine_name ?? 'N/A',
                'repair_duration_minutes' => $report->repair_duration_minutes,
            ];
        }

        self::log($action, [
            'Operation Info' => [
                'operation_number' => $operation->operation_number,
                'status' => $operation->status,
                'started_at' => $operation->started_at,
                'stopped_at' => $operation->stopped_at ?? 'Still running',
            ],
            'Operation Metrics' => [
                'total_minutes' => $totalMinutes,
                'duration_minutes' => $operation->duration_minutes ?? 0,
                'total_pause_minutes' => $operation->total_pause_minutes ?? 0,
                'mtbf_hours' => $operation->mtbf_hours,
            ],
            'Maintenance Reports' => [
                'total_reports' => count($reportsData),
                'total_repair_minutes' => $maintenanceReports->sum('repair_duration_minutes'),
                'details' => json_encode($reportsData, JSON_PRETTY_PRINT),
            ],
            'Formula Check' => [
                'total_minutes' => $totalMinutes,
                'pause_minutes' => $operation->total_pause_minutes ?? 0,
                'repair_minutes (NOT DEDUCTED)' => $maintenanceReports->sum('repair_duration_minutes'),
                'net_operation = total - pause' => ($totalMinutes - ($operation->total_pause_minutes ?? 0)),
                'failures_count' => count($reportsData),
                'mtbf_calculation' => count($reportsData) > 0
                    ? "total_minutes / failures = {$totalMinutes} / " . count($reportsData) . " = " . round($totalMinutes / count($reportsData), 2) . " minutes"
                    : "No failures = total_minutes = {$totalMinutes} minutes",
            ],
        ]);
    }

    /**
     * Log Machine Metrics
     */
    public static function logMachineMetrics(string $action, $machine): void
    {
        $line = $machine->lineModel;
        $periodStart = $line ? ($line->current_period_start ?? $line->created_at) :
                              ($machine->current_period_start ?? $machine->created_at);

        $completedReports = $machine->maintenanceReports()
            ->where('status', 'Selesai')
            ->where('created_at', '>=', $periodStart)
            ->get();

        $reportsData = [];
        foreach ($completedReports as $report) {
            $reportsData[] = [
                'report_number' => $report->report_number,
                'repair_duration_minutes' => $report->repair_duration_minutes,
                'created_at' => $report->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $totalRepairMinutes = $completedReports->sum('repair_duration_minutes');

        self::log($action, [
            'Machine Info' => [
                'machine_name' => $machine->machine_name,
                'line' => $line ? $line->line_code : 'N/A',
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
            ],
            'Machine Metrics' => [
                'total_operation_hours' => $machine->total_operation_hours,
                'total_repair_hours' => $machine->total_repair_hours,
                'total_failures' => $machine->total_failures,
                'mttr_hours' => $machine->mttr_hours,
                'mtbf_hours' => $machine->mtbf_hours,
            ],
            'Completed Reports' => [
                'count' => count($reportsData),
                'total_repair_minutes' => $totalRepairMinutes,
                'details' => json_encode($reportsData, JSON_PRETTY_PRINT),
            ],
            'Formula Check' => [
                'MTTR Calculation' => count($reportsData) > 0
                    ? "total_repair_minutes / failures = {$totalRepairMinutes} / " . count($reportsData) . " = " . round($totalRepairMinutes / count($reportsData), 2) . " minutes = " . round($totalRepairMinutes / 60 / count($reportsData), 2) . " hours"
                    : "No failures = NULL",
                'MTBF Calculation' => $machine->total_failures > 0 && $machine->total_operation_hours > 0
                    ? "total_operation_hours / failures = {$machine->total_operation_hours} / {$machine->total_failures} = " . round($machine->total_operation_hours / $machine->total_failures, 2) . " hours"
                    : "No failures or no operation = NULL",
                'Note' => 'MTBF uses total_operation_hours (NOT deducted by repair time)',
            ],
        ]);
    }

    /**
     * Log Maintenance Report
     */
    public static function logMaintenanceReport(string $action, $report): void
    {
        $repairMinutes = $report->repair_duration_minutes ?? 0;
        $lineStopMinutes = $report->line_stop_duration_minutes ?? 0;

        self::log($action, [
            'Report Info' => [
                'report_number' => $report->report_number,
                'status' => $report->status,
                'machine' => $report->machine->machine_name ?? 'N/A',
                'line' => $report->line->line_code ?? 'N/A',
            ],
            'Timestamps' => [
                'reported_at' => $report->reported_at,
                'line_stopped_at' => $report->line_stopped_at,
                'started_at' => $report->started_at,
                'completed_at' => $report->completed_at ?? 'Not completed yet',
            ],
            'Durations' => [
                'repair_duration_minutes' => $repairMinutes,
                'repair_duration_hours' => round($repairMinutes / 60, 2),
                'line_stop_duration_minutes' => $lineStopMinutes,
                'line_stop_duration_hours' => round($lineStopMinutes / 60, 2),
            ],
        ]);
    }
}
