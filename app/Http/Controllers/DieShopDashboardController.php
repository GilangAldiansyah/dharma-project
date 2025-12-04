<?php

namespace App\Http\Controllers;

use App\Models\DieShopReport;
use App\Models\DiePart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DieShopDashboardController extends Controller
{
    public function index(Request $request)
    {
        $selectedMonth = $request->month ?? now()->format('Y-m');
        $startDate = Carbon::parse($selectedMonth)->startOfMonth();
        $endDate = Carbon::parse($selectedMonth)->endOfMonth();

        // Statistics
        $statistics = [
            'totalReports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])->count(),
            'pendingReports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('status', 'pending')->count(),
            'inProgressReports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('status', 'in_progress')->count(),
            'completedReports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('status', 'completed')->count(),
            'correctiveReports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('activity_type', 'corrective')->count(),
            'preventiveReports' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->where('activity_type', 'preventive')->count(),
            'totalSpareparts' => DieShopReport::whereBetween('report_date', [$startDate, $endDate])
                ->withCount('spareparts')
                ->get()
                ->sum('spareparts_count'),
            'activeDieParts' => DiePart::where('status', 'active')->count(),
        ];

        $recentReports = DieShopReport::with(['diePart'])
            ->whereBetween('report_date', [$startDate, $endDate])
            ->latest('report_date')
            ->limit(10)
            ->get();

        $monthlyTrend = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $corrective = DieShopReport::whereDate('report_date', $currentDate)
                ->where('activity_type', 'corrective')
                ->count();

            $preventive = DieShopReport::whereDate('report_date', $currentDate)
                ->where('activity_type', 'preventive')
                ->count();

            $monthlyTrend[] = [
                'date' => $currentDate->format('Y-m-d'),
                'corrective' => $corrective,
                'preventive' => $preventive,
            ];

            $currentDate->addDay();
        }

        $topDieParts = DieShopReport::select('die_part_id', DB::raw('count(*) as report_count'))
            ->whereBetween('report_date', [$startDate, $endDate])
            ->with('diePart')
            ->groupBy('die_part_id')
            ->orderBy('report_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'die_part' => [
                        'part_no' => $item->diePart->part_no,
                        'part_name' => $item->diePart->part_name,
                    ],
                    'report_count' => $item->report_count,
                ];
            });

        return Inertia::render('DieShop/Dashboard', [
            'statistics' => $statistics,
            'recentReports' => $recentReports,
            'monthlyTrend' => $monthlyTrend,
            'topDieParts' => $topDieParts,
            'selectedMonth' => $selectedMonth,
        ]);
    }
}
