<?php

namespace App\Http\Controllers;

use App\Models\CmReport;
use App\Models\PmReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class JigDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user     = User::with('roles')->find(Auth::id());
        $isLeader = $user->hasRole('leader') || $user->hasRole('admin');
        $isPic    = !$isLeader && $user->hasRole('pic_jig');

        $bulan = $request->filled('bulan') ? $request->bulan : now()->month;
        $tahun = $request->filled('tahun') ? (int) $request->tahun : now()->year;
        $picId = $request->filled('pic_id') ? (int) $request->pic_id : null;

        $pmBase = PmReport::query()
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->when($picId && !$isPic, fn($q) => $q->where('pic_id', $picId))
            ->whereYear('planned_week_start', $tahun)
            ->when($bulan !== 'all', fn($q) => $q->whereMonth('planned_week_start', $bulan));

        $cmBase = CmReport::query()
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->when($picId && !$isPic, fn($q) => $q->where('pic_id', $picId));

        $pmSummary = [
            'total'   => (clone $pmBase)->count(),
            'done'    => (clone $pmBase)->where('status', 'done')->count(),
            'late'    => (clone $pmBase)->where('status', 'late')->count(),
            'pending' => (clone $pmBase)->where('status', 'pending')->count(),
        ];

        $cmSummary = [
            'open'        => (clone $cmBase)->where('status', 'open')->count(),
            'in_progress' => (clone $cmBase)->where('status', 'in_progress')->count(),
            'closed'      => (clone $cmBase)->where('status', 'closed')->count(),
        ];

        $completionRate = $pmSummary['total'] > 0
            ? round(($pmSummary['done'] / $pmSummary['total']) * 100)
            : 0;

        $labelMap = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        $pmTrendRaw = PmReport::query()
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->when($picId && !$isPic, fn($q) => $q->where('pic_id', $picId))
            ->whereYear('planned_week_start', $tahun)
            ->select(
                DB::raw('MONTH(planned_week_start) as bulan'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as done"),
                DB::raw("SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending")
            )
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

        $pmTrend = array_map(fn($m) => [
            'bulan'   => $m,
            'label'   => $labelMap[$m - 1],
            'total'   => (int) ($pmTrendRaw[$m]->total   ?? 0),
            'done'    => (int) ($pmTrendRaw[$m]->done    ?? 0),
            'late'    => (int) ($pmTrendRaw[$m]->late    ?? 0),
            'pending' => (int) ($pmTrendRaw[$m]->pending ?? 0),
        ], range(1, 12));

        $cmTrendRaw = CmReport::query()
            ->when($isPic, fn($q) => $q->where('pic_id', $user->id))
            ->when($picId && !$isPic, fn($q) => $q->where('pic_id', $picId))
            ->whereYear('report_date', $tahun)
            ->select(
                DB::raw('MONTH(report_date) as bulan'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='open' THEN 1 ELSE 0 END) as open"),
                DB::raw("SUM(CASE WHEN status='in_progress' THEN 1 ELSE 0 END) as in_progress"),
                DB::raw("SUM(CASE WHEN status='closed' THEN 1 ELSE 0 END) as closed")
            )
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

        $cmTrend = array_map(fn($m) => [
            'bulan'       => $m,
            'label'       => $labelMap[$m - 1],
            'open'        => (int) ($cmTrendRaw[$m]->open        ?? 0),
            'in_progress' => (int) ($cmTrendRaw[$m]->in_progress ?? 0),
            'closed'      => (int) ($cmTrendRaw[$m]->closed      ?? 0),
            'total'       => (int) ($cmTrendRaw[$m]->total       ?? 0),
        ], range(1, 12));

        $upcomingPm = (clone $pmBase)
            ->with('pmSchedule.jig:id,name,type,line', 'pic:id,name')
            ->where('status', 'pending')
            ->orderBy('planned_week_start')
            ->take(5)->get();

        $recentCm = (clone $cmBase)
            ->with('jig:id,name', 'pic:id,name')
            ->whereIn('status', ['open', 'in_progress'])
            ->latest('report_date')
            ->take(5)->get();

        $picPerformance = [];
        $pics           = [];

        if (!$isPic) {
            $picUsers = User::whereHas('roles', fn($q) => $q->where('name', 'pic_jig'))
                ->select('id', 'name')->get();
            $pics = $picUsers->toArray();

            $picIds = $picUsers->pluck('id')->toArray();

            $pmPicRaw = PmReport::whereIn('pic_id', $picIds)
                ->whereYear('planned_week_start', $tahun)
                ->when($bulan !== 'all', fn($q) => $q->whereMonth('planned_week_start', $bulan))
                ->select(
                    'pic_id',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as done"),
                    DB::raw("SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late"),
                    DB::raw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending")
                )
                ->groupBy('pic_id')
                ->get()
                ->keyBy('pic_id');

            $cmActivePicRaw = CmReport::whereIn('pic_id', $picIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->select('pic_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('pic_id')
                ->get()
                ->keyBy('pic_id');

            $cmTotalPicRaw = CmReport::whereIn('pic_id', $picIds)
                ->whereYear('report_date', $tahun)
                ->when($bulan !== 'all', fn($q) => $q->whereMonth('report_date', $bulan))
                ->select('pic_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('pic_id')
                ->get()
                ->keyBy('pic_id');

            $pmMonthlyRaw = PmReport::whereIn('pic_id', $picIds)
                ->whereYear('planned_week_start', $tahun)
                ->select(
                    'pic_id',
                    DB::raw('MONTH(planned_week_start) as bulan'),
                    DB::raw("SUM(CASE WHEN status='done' THEN 1 ELSE 0 END) as done"),
                    DB::raw("SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late"),
                    DB::raw("SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending")
                )
                ->groupBy('pic_id', 'bulan')
                ->get()
                ->groupBy('pic_id');

            $shortMonth = ['J','F','M','A','M','J','J','A','S','O','N','D'];

            foreach ($picUsers as $pic) {
                $pm      = $pmPicRaw[$pic->id]     ?? null;
                $total   = $pm ? (int) $pm->total   : 0;
                $done    = $pm ? (int) $pm->done    : 0;
                $late    = $pm ? (int) $pm->late    : 0;
                $pending = $pm ? (int) $pm->pending : 0;

                $monthRows = ($pmMonthlyRaw[$pic->id] ?? collect())->keyBy('bulan');
                $monthly = array_map(fn($m) => [
                    'label'   => $shortMonth[$m - 1],
                    'done'    => (int) ($monthRows[$m]->done    ?? 0),
                    'late'    => (int) ($monthRows[$m]->late    ?? 0),
                    'pending' => (int) ($monthRows[$m]->pending ?? 0),
                ], range(1, 12));

                $picPerformance[] = [
                    'id'              => $pic->id,
                    'name'            => $pic->name,
                    'total'           => $total,
                    'done'            => $done,
                    'late'            => $late,
                    'pending'         => $pending,
                    'cm_active'       => (int) ($cmActivePicRaw[$pic->id]->cnt ?? 0),
                    'cm_total'        => (int) ($cmTotalPicRaw[$pic->id]->cnt  ?? 0),
                    'completion_rate' => $total > 0 ? round(($done / $total) * 100) : 0,
                    'monthly'         => $monthly,
                ];
            }

            usort($picPerformance, fn($a, $b) => $b['completion_rate'] - $a['completion_rate']);
        }

        return Inertia::render('Jig/Dashboard', [
            'pmSummary'      => $pmSummary,
            'cmSummary'      => $cmSummary,
            'upcomingPm'     => $upcomingPm,
            'recentCm'       => $recentCm,
            'completionRate' => $completionRate,
            'picPerformance' => $picPerformance,
            'pmTrend'        => $pmTrend,
            'cmTrend'        => $cmTrend,
            'pics'           => $pics,
            'bulan'          => $bulan,
            'tahun'          => $tahun,
            'isPic'          => $isPic,
            'filters'        => ['bulan' => $bulan, 'tahun' => $tahun, 'pic_id' => $picId],
        ]);
    }
}
