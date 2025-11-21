<?php

namespace App\Http\Controllers;

use App\Models\RobotCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Export production report to CSV
     */
    public function exportCsv(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        $robots = RobotCounter::all();

        $filename = "production_report_{$date}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($robots, $date) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'Robot Name',
                'Counter',
                'Target',
                'Progress (%)',
                'Remaining',
                'Status',
                'Last Update'
            ]);

            // Data
            foreach ($robots as $robot) {
                fputcsv($file, [
                    $robot->name,
                    $robot->counter,
                    $robot->target,
                    $robot->progress,
                    $robot->remaining,
                    $robot->status,
                    $robot->updated_at->format('Y-m-d H:i:s')
                ]);
            }

            // Summary
            fputcsv($file, []);
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total Counter', array_sum($robots->pluck('counter')->toArray())]);
            fputcsv($file, ['Total Target', array_sum($robots->pluck('target')->toArray())]);
            fputcsv($file, ['Running Robots', $robots->where('status', 'running')->count()]);
            fputcsv($file, ['Idle Robots', $robots->where('status', 'idle')->count()]);
            fputcsv($file, ['Error Robots', $robots->where('status', 'error')->count()]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get daily production summary
     */
    public function dailySummary(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));

        // Ini perlu table history jika mau tracking per hari
        // Untuk sekarang return current state

        $robots = RobotCounter::all();

        return response()->json([
            'date' => $date,
            'total_counter' => $robots->sum('counter'),
            'total_target' => $robots->sum('target'),
            'overall_progress' => $robots->sum('target') > 0
                ? round(($robots->sum('counter') / $robots->sum('target')) * 100, 2)
                : 0,
            'robots' => $robots->map(function($robot) {
                return [
                    'name' => $robot->name,
                    'counter' => $robot->counter,
                    'target' => $robot->target,
                    'progress' => $robot->progress,
                    'status' => $robot->status,
                ];
            })
        ]);
    }

    /**
     * Get production history (perlu migration baru untuk log history)
     */
    public function history(Request $request)
    {
        $robotId = $request->get('robot_id');
        $startDate = $request->get('start_date', now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // TODO: Implement dengan table production_history
        // Format: id, robot_id, counter, timestamp

        return response()->json([
            'message' => 'Feature coming soon - need production_history table'
        ]);
    }
}
